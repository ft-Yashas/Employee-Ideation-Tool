# IFQM — Production Deployment & Security Runbook

This is the handover document. The application will hold **real employee names,
emails, reporting lines, and their submitted ideas and attachments** — treat it
as a system of record for personal data, not a demo.

Work through §1–§7 **before** letting anyone log in. §8 lists what is still
outstanding; read it, because it is the honest part.

---

## 0. TL;DR — the five things that must not be skipped

| # | Thing | Why |
|---|---|---|
| 1 | Generate a real `JWT_SECRET` | With the old placeholder, anyone could mint an admin token for any organisation. |
| 2 | Create the `ifqm_app` DB user; stop using `root` | One SQL flaw as root = every tenant's data. |
| 3 | Terminate TLS (`https`) in front of the app | Session tokens travel in the `Authorization` header. |
| 4 | Run the migrations in `db/migrations/` | Session revocation and lockout depend on new columns. |
| 5 | Set up database backups **and test a restore** | An untested backup is not a backup. |

The server will now **refuse to boot** in production if 1–3 are wrong. That is on
purpose.

---

## 1. Provision the database

MySQL 8.0+ (or MariaDB 10.6+). Create the schemas:

```bash
mysql -u root -p < db/master.sql          # tenant registry  -> ifqm_master
mysql -u root -p -e "CREATE DATABASE ifqm_acme CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p ifqm_acme < db/schema.sql   # one schema per organisation
```

### Create the least-privilege application user

The application used to connect to every tenant as **root**, with the password
stored in plaintext in `ifqm_master.tenants`. It now uses one restricted account
supplied via the environment. Create it:

```sql
CREATE USER 'ifqm_app'@'localhost' IDENTIFIED BY '<a long random password>';

-- Only the product's own schemas, and only DML — no DROP, no GRANT, no FILE.
GRANT SELECT, INSERT, UPDATE, DELETE ON `ifqm\_%`.* TO 'ifqm_app'@'localhost';

FLUSH PRIVILEGES;
```

> **Provisioning new organisations from the platform-admin UI needs `CREATE`**
> (it creates a schema and loads `schema.sql`). Options, in order of preference:
>
> 1. Leave `ifqm_app` without `CREATE`, and provision new orgs out-of-band with
>    `backend/scripts/provision-tenant.js` run as an admin DB user. **Recommended.**
> 2. Grant `CREATE` on `` `ifqm\_%`.* `` too, accepting that a compromise of the
>    app can create schemas (it still cannot read anything outside `ifqm_%`).

Then in `backend/.env`:

```ini
APP_DB_USER=ifqm_app
APP_DB_PASS=<the password you just set>
MASTER_DB_USER=ifqm_app
MASTER_DB_PASS=<same>
```

MySQL itself must not be reachable from the internet — bind it to localhost or a
private network, and firewall port 3306.

---

## 2. Run the migrations

Existing installs need these. They are idempotent; re-running is safe.

```bash
# Once, against the registry:
mysql -u root -p ifqm_master < db/migrations/001_production_hardening_master.sql

# Once per organisation schema:
mysql -u root -p ifqm_acme < db/migrations/001_production_hardening.sql
```

This adds:
- `users.password_changed_at` — lets a password reset actually **kill existing
  sessions**. Without it, a stolen token stays valid for its full 8 hours even
  after the victim resets their password.
- `users.deactivated_at` — offboarding audit trail.
- `password_reset_tokens.selector` — reset tokens are now looked up by an indexed
  selector, so verification runs **one** bcrypt compare instead of one per row in
  the table (that was a free way to burn all the server's CPU).
- `ifqm_master.login_attempts` — the brute-force lockout, which previously lived
  in process memory and reset on every restart.
- Indexes on the login and idea-listing hot paths.

---

## 3. Configure the backend

```bash
cd backend
cp .env.example .env
```

Generate the signing key — **a fresh one per environment**:

```bash
node -e "console.log(require('crypto').randomBytes(48).toString('base64url'))"
```

Anyone holding `JWT_SECRET` can forge a session token for **any user in any
organisation, including an admin**. Guard it like a root password. Rotating it
immediately logs everybody out — that is your "revoke all sessions" lever.

Minimum production `.env`:

```ini
NODE_ENV=production
JWT_SECRET=<the 64-char value you just generated>
APP_DB_USER=ifqm_app
APP_DB_PASS=<db password>
MASTER_DB_USER=ifqm_app
MASTER_DB_PASS=<db password>
CORS_ORIGIN=https://ideas.yourcompany.com
FRONTEND_BASE_URL=https://ideas.yourcompany.com
FORCE_HTTPS=true
```

**The server exits on startup** if the secret is missing/placeholder, the DB
password is empty, the DB user is `root`, or CORS/base-URL still point at
localhost. If it won't start, read the error — it names the exact problem.

---

## 4. Build and serve the frontend

```bash
cd frontend
npm ci
npm run build        # -> frontend/dist
```

Serve `frontend/dist` as static files and reverse-proxy `/api` to the Node
process. Nginx:

```nginx
server {
  listen 443 ssl http2;
  server_name ideas.yourcompany.com;

  ssl_certificate     /etc/letsencrypt/live/ideas.yourcompany.com/fullchain.pem;
  ssl_certificate_key /etc/letsencrypt/live/ideas.yourcompany.com/privkey.pem;

  root /var/www/ifqm/dist;
  index index.html;

  # The API sets its own security headers; these are for the HTML app.
  add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
  add_header X-Content-Type-Options nosniff always;
  add_header X-Frame-Options DENY always;
  add_header Referrer-Policy no-referrer always;
  add_header Content-Security-Policy
    "default-src 'self'; img-src 'self' data: blob:; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; connect-src 'self'; object-src 'none'; frame-ancestors 'none'; base-uri 'self'" always;

  client_max_body_size 12m;   # must exceed MAX_FILE_MB

  location /api/ {
    proxy_pass http://127.0.0.1:4000;
    proxy_set_header Host              $host;
    proxy_set_header X-Real-IP         $remote_addr;
    proxy_set_header X-Forwarded-For   $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;   # required: the app trusts this
  }

  location / { try_files $uri $uri/ /index.html; }  # SPA routing
}

server {                      # http -> https
  listen 80;
  server_name ideas.yourcompany.com;
  return 308 https://$host$request_uri;
}
```

`X-Forwarded-Proto` matters: the app trusts it to decide whether a request
arrived over TLS.

---

## 5. Run the backend as a service

Never `node server.js` in a terminal. systemd:

```ini
# /etc/systemd/system/ifqm.service
[Unit]
Description=IFQM backend
After=network.target mysql.service

[Service]
Type=simple
User=ifqm                      # NOT root
WorkingDirectory=/opt/ifqm/backend
ExecStart=/usr/bin/node server.js
Restart=always
RestartSec=5
Environment=NODE_ENV=production

# The app only ever needs to write to its uploads dir.
NoNewPrivileges=true
PrivateTmp=true
ProtectSystem=strict
ProtectHome=true
ReadWritePaths=/opt/ifqm/backend/uploads

[Install]
WantedBy=multi-user.target
```

```bash
npm ci --omit=dev
systemctl enable --now ifqm
```

Health endpoints:
- `GET /api/health` — liveness ("the process is up").
- `GET /api/ready` — readiness; **pings the database** and returns 503 if it is
  unreachable. Point the load balancer at this one, so an instance that can't
  reach MySQL is pulled from rotation instead of 500-ing every request.

---

## 6. Backups (do not skip)

Employee ideas are the entire value of the product. Two things must be backed up:

1. **The databases** — `ifqm_master` *and* every `ifqm_<org>` schema.
2. **`backend/uploads/`** — the attachments. These live on disk, not in MySQL.
   A database-only backup silently loses every attached document.

```bash
#!/bin/bash
# /opt/ifqm/backup.sh — run nightly from cron
set -euo pipefail
D=$(date +%F)
DEST=/var/backups/ifqm/$D
mkdir -p "$DEST"

mysqldump --single-transaction --routines --events \
          --databases $(mysql -N -e "SHOW DATABASES LIKE 'ifqm%'" | tr '\n' ' ') \
          | gzip > "$DEST/ifqm-all.sql.gz"

tar czf "$DEST/uploads.tar.gz" -C /opt/ifqm/backend uploads

find /var/backups/ifqm -maxdepth 1 -type d -mtime +30 -exec rm -rf {} +
```

Copy them **off the machine**. Then actually restore one into a scratch database
and log in against it. An untested backup is not a backup.

---

## 7. Post-deploy verification

```bash
# 1. Attachments must NOT be publicly readable (this was the worst bug found).
curl -i https://ideas.yourcompany.com/api/uploads/anything.png     # expect 404
curl -i https://ideas.yourcompany.com/api/upload/1/download        # expect 401

# 2. API rejects anonymous callers.
curl -i https://ideas.yourcompany.com/api/ideas                    # expect 401

# 3. TLS + HSTS.
curl -I http://ideas.yourcompany.com                               # expect 308 -> https
curl -sI https://ideas.yourcompany.com/api/health | grep -i strict-transport-security

# 4. A wrong org code must NOT silently log you into another org.
curl -s -X POST https://ideas.yourcompany.com/api/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"a@b.com","password":"x","org_slug":"nope"}'        # expect "Unknown organization code."

# 5. Readiness reports the DB.
curl -s https://ideas.yourcompany.com/api/ready
```

Then, in the UI: log in, submit an idea with an attachment, review it, and check
that deactivating a user in Admin logs them out on their next click.

---

## 8. Known gaps — read this

Being straight about what this hardening pass did **not** cover:

1. **No automated test suite.** There are no unit or integration tests. Every fix
   here was verified by driving the running system by hand. A regression will not
   be caught automatically. This is the biggest gap for long-term maintenance.
2. **JWTs are stored in `localStorage`.** Any cross-site-scripting flaw in the
   React app can read a token. The CSP in §4 is the mitigation; a
   `httpOnly` cookie plus CSRF tokens would be strictly better and is the natural
   next change.
3. **Session revocation costs one DB read per request.** That is what makes
   instant deactivation work. Fine at this scale; if it ever hurts, cache the
   user row for a few seconds rather than reverting to trusting the token.
4. **No audit log for security events.** Logins, lockouts and password resets are
   written to the app log, not to a tamper-evident store. The *workflow* audit
   trail (who approved what) is in the DB and is complete.
5. **Uploads are validated by extension, not by content.** A file renamed to
   `.pdf` is accepted. It is stored under a generated name, served as
   `Content-Disposition: attachment` with `nosniff` and a sandbox CSP, so it
   cannot execute in the app's origin — but it is not virus-scanned. If staff
   will open these routinely, put a scanner (e.g. ClamAV) in front.
6. **Rate limits are per-process, in memory.** Running more than one Node worker
   multiplies the effective limit. Move to a Redis-backed store before scaling
   out. (The *account lockout* is in the DB and is safe across workers.)
7. **GDPR/DPDP:** there is no "export my data" or "delete my account" flow.
   Deleting a user who has submitted ideas deactivates them instead, to preserve
   authorship. Confirm this matches the company's retention policy.
