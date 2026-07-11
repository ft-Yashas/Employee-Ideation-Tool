/**
 * Email service — Node/nodemailer equivalent of PHP api/mailer.php.
 *
 * Preserves the same behaviour:
 *   getOrgSettings(db)                          → all org_settings as a map
 *   queueEmail(db, to, name, subject, body)     → insert into email_queue
 *   processEmailQueue(db)                        → send up to 5 pending emails
 *   sendSmtpEmail(settings, to, name, subj, html)→ deliver one HTML email
 *
 * PHP hand-rolled a raw SMTP conversation supporting STARTTLS (port 587) and
 * implicit TLS (port 465) with AUTH LOGIN. nodemailer performs the identical
 * negotiation from the same org_settings values.
 */
import nodemailer from 'nodemailer';
import logger from '../utils/logger.js';

/** Fetch all org_settings as a key→value map (PHP getOrgSettings). */
export async function getOrgSettings(db) {
  try {
    const [rows] = await db.query('SELECT key_name, value FROM org_settings');
    const map = {};
    for (const r of rows) map[r.key_name] = r.value;
    return map;
  } catch (e) {
    logger.error('getOrgSettings error', e.message);
    return {};
  }
}

/** Build a nodemailer transport from org_settings (mirrors sendSmtpEmail setup). */
function buildTransport(settings) {
  const host = String(settings.smtp_host || '').trim();
  const port = parseInt(settings.smtp_port || '587', 10) || 587;
  const user = String(settings.smtp_user || '').trim();
  const pass = settings.smtp_pass || '';

  if (!host) throw new Error('smtp_host is not configured.');

  return nodemailer.createTransport({
    host,
    port,
    secure: port === 465, // implicit TLS; 587 upgrades via STARTTLS automatically
    auth: user ? { user, pass } : undefined,
    connectionTimeout: 15000,
    greetingTimeout: 15000,
  });
}

/**
 * Send one HTML email. Returns true on success; throws on SMTP error
 * (matching the PHP contract used by the queue processor).
 */
export async function sendSmtpEmail(settings, toEmail, toName, subject, bodyHtml) {
  const transport = buildTransport(settings);
  const from = String(settings.smtp_from || settings.smtp_user || '').trim();
  const fromName = String(settings.smtp_from_name || 'IFQM Ideation').trim();

  await transport.sendMail({
    from: `"${fromName}" <${from}>`,
    to: toName ? `"${toName}" <${toEmail}>` : toEmail,
    subject,
    html: bodyHtml,
  });
  return true;
}

/** Insert an email into the queue (PHP queueEmail). */
export async function queueEmail(db, toEmail, toName, subject, body) {
  try {
    await db.execute(
      `INSERT INTO email_queue (to_email, to_name, subject, body, status, attempts, created_at)
       VALUES (?, ?, ?, ?, 'pending', 0, NOW())`,
      [toEmail, toName, subject, body]
    );
  } catch (e) {
    logger.error('queueEmail error', e.message);
  }
}

/** Process up to 5 pending emails (PHP processEmailQueue). */
export async function processEmailQueue(db) {
  const settings = await getOrgSettings(db);
  if ((settings.email_enabled ?? '0') !== '1') return;
  if (!String(settings.smtp_host || '').trim()) {
    logger.error('processEmailQueue: smtp_host is not configured.');
    return;
  }

  const [emails] = await db.query(
    `SELECT * FROM email_queue
     WHERE status = 'pending' AND attempts < 5
     ORDER BY created_at ASC
     LIMIT 5`
  );

  for (const email of emails) {
    const id = Number(email.id);
    await db.execute(
      "UPDATE email_queue SET status = 'processing', attempts = attempts + 1 WHERE id = ?",
      [id]
    );
    try {
      const sent = await sendSmtpEmail(
        settings,
        email.to_email,
        email.to_name,
        email.subject,
        email.body
      );
      await db.execute(
        sent
          ? "UPDATE email_queue SET status = 'sent', sent_at = NOW() WHERE id = ?"
          : "UPDATE email_queue SET status = 'failed' WHERE id = ?",
        [id]
      );
    } catch (e) {
      logger.error(`processEmailQueue send error (id=${id})`, e.message);
      await db.execute("UPDATE email_queue SET status = 'failed' WHERE id = ?", [id]);
    }
  }
}

export default { getOrgSettings, sendSmtpEmail, queueEmail, processEmailQueue };
