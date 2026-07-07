<?php
// api/mailer.php  –  Email queue helpers and SMTP sender
// Functions:
//   getOrgSettings()      – fetch all org_settings as key=>value map
//   queueEmail()          – insert an email into email_queue
//   processEmailQueue()   – send up to 5 pending emails via SMTP
//   sendSmtpEmail()       – open raw SMTP connection and send HTML email
require_once __DIR__ . '/config.php';

// ── Fetch organisation settings ───────────────────────────────────
function getOrgSettings(): array {
    static $cache = null;
    if ($cache !== null) return $cache;
    try {
        $rows  = db()->query("SELECT key_name, value FROM org_settings")->fetchAll();
        $cache = [];
        foreach ($rows as $row) {
            $cache[$row['key_name']] = $row['value'];
        }
    } catch (Exception $e) {
        error_log('getOrgSettings error: ' . $e->getMessage());
        $cache = [];
    }
    return $cache;
}

// ── Queue an outgoing email ───────────────────────────────────────
function queueEmail(string $toEmail, string $toName, string $subject, string $body): void {
    try {
        db()->prepare(
            "INSERT INTO email_queue (to_email, to_name, subject, body, status, attempts, created_at)
             VALUES (?, ?, ?, ?, 'pending', 0, NOW())"
        )->execute([$toEmail, $toName, $subject, $body]);
    } catch (Exception $e) {
        error_log('queueEmail error: ' . $e->getMessage());
    }
}

// ── Process up to 5 pending emails from the queue ────────────────
function processEmailQueue(): void {
    $settings = getOrgSettings();

    if (($settings['email_enabled'] ?? '0') !== '1') {
        return; // Email sending is disabled
    }

    $smtpHost = trim($settings['smtp_host'] ?? '');
    if ($smtpHost === '') {
        error_log('processEmailQueue: smtp_host is not configured.');
        return;
    }

    $pdo = db();

    // Lock and fetch up to 5 pending rows
    $stmt = $pdo->prepare(
        "SELECT * FROM email_queue
         WHERE status = 'pending' AND attempts < 5
         ORDER BY created_at ASC
         LIMIT 5"
    );
    $stmt->execute();
    $emails = $stmt->fetchAll();

    foreach ($emails as $email) {
        $id = (int)$email['id'];

        // Mark as 'processing' to prevent duplicate sends in concurrent runs
        $pdo->prepare("UPDATE email_queue SET status = 'processing', attempts = attempts + 1 WHERE id = ?")
            ->execute([$id]);

        try {
            $sent = sendSmtpEmail(
                $settings,
                $email['to_email'],
                $email['to_name'],
                $email['subject'],
                $email['body']
            );

            if ($sent) {
                $pdo->prepare("UPDATE email_queue SET status = 'sent', sent_at = NOW() WHERE id = ?")
                    ->execute([$id]);
            } else {
                $pdo->prepare("UPDATE email_queue SET status = 'failed' WHERE id = ?")
                    ->execute([$id]);
            }
        } catch (Exception $e) {
            error_log('processEmailQueue send error (id=' . $id . '): ' . $e->getMessage());
            $pdo->prepare("UPDATE email_queue SET status = 'failed' WHERE id = ?")
                ->execute([$id]);
        }
    }
}

// ── Low-level SMTP sender ─────────────────────────────────────────
/**
 * Send an HTML email via raw SMTP.
 *
 * Supports AUTH LOGIN and STARTTLS (port 587) / implicit TLS (port 465).
 *
 * @param  array  $settings  Org settings map (smtp_host, smtp_port, smtp_user,
 *                            smtp_pass, smtp_from, smtp_from_name)
 * @param  string $toEmail   Recipient email address
 * @param  string $toName    Recipient display name
 * @param  string $subject   Email subject
 * @param  string $bodyHtml  HTML email body
 * @return bool              true on success
 * @throws Exception         on any SMTP / connection error
 */
function sendSmtpEmail(
    array  $settings,
    string $toEmail,
    string $toName,
    string $subject,
    string $bodyHtml
): bool {
    $host     = trim($settings['smtp_host'] ?? '');
    $port     = (int)($settings['smtp_port'] ?? 587);
    $user     = trim($settings['smtp_user'] ?? '');
    $pass     = $settings['smtp_pass']  ?? '';
    $from     = trim($settings['smtp_from'] ?? $user);
    $fromName = trim($settings['smtp_from_name'] ?? 'IFQM Ideation');

    if ($host === '') throw new Exception('smtp_host is not configured.');

    $timeout     = 15;
    $useImplicit = ($port === 465);    // SSL/TLS from the start
    $useStartTls = ($port === 587);    // upgrade with STARTTLS

    // ── Open TCP connection ───────────────────────────────────────
    $prefix = $useImplicit ? 'ssl://' : '';
    $errno  = 0;
    $errstr = '';
    $socket = @fsockopen($prefix . $host, $port, $errno, $errstr, $timeout);
    if (!$socket) {
        throw new Exception("SMTP connect failed ({$host}:{$port}): [{$errno}] {$errstr}");
    }
    stream_set_timeout($socket, $timeout);

    $smtpRead = function () use ($socket): string {
        $response = '';
        while ($line = fgets($socket, 512)) {
            $response .= $line;
            // Last line of multi-line response: "NNN " (space after code, not dash)
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        return $response;
    };

    $smtpSend = function (string $cmd) use ($socket, $smtpRead): string {
        fwrite($socket, $cmd . "\r\n");
        return $smtpRead();
    };

    $assertCode = function (string $response, string $expected, string $ctx) {
        if (strncmp(ltrim($response), $expected, strlen($expected)) !== 0) {
            throw new Exception("SMTP {$ctx} unexpected response: " . trim($response));
        }
    };

    // Read server greeting
    $greeting = $smtpRead();
    $assertCode($greeting, '220', 'greeting');

    $ehlo = $smtpSend('EHLO ' . (gethostname() ?: 'localhost'));
    $assertCode($ehlo, '250', 'EHLO');

    // ── STARTTLS upgrade ──────────────────────────────────────────
    if ($useStartTls) {
        $tlsResp = $smtpSend('STARTTLS');
        $assertCode($tlsResp, '220', 'STARTTLS');

        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new Exception('STARTTLS crypto negotiation failed.');
        }

        // Re-send EHLO after upgrade
        $ehlo = $smtpSend('EHLO ' . (gethostname() ?: 'localhost'));
        $assertCode($ehlo, '250', 'EHLO after STARTTLS');
    }

    // ── AUTH LOGIN ────────────────────────────────────────────────
    if ($user !== '') {
        $authResp = $smtpSend('AUTH LOGIN');
        $assertCode($authResp, '334', 'AUTH LOGIN');

        $userResp = $smtpSend(base64_encode($user));
        $assertCode($userResp, '334', 'AUTH LOGIN username');

        $passResp = $smtpSend(base64_encode($pass));
        $assertCode($passResp, '235', 'AUTH LOGIN password');
    }

    // ── MAIL FROM ─────────────────────────────────────────────────
    $mailFrom = $smtpSend("MAIL FROM:<{$from}>");
    $assertCode($mailFrom, '250', 'MAIL FROM');

    // ── RCPT TO ───────────────────────────────────────────────────
    $rcptTo = $smtpSend("RCPT TO:<{$toEmail}>");
    $assertCode($rcptTo, '250', 'RCPT TO');

    // ── DATA ──────────────────────────────────────────────────────
    $dataResp = $smtpSend('DATA');
    $assertCode($dataResp, '354', 'DATA');

    $msgId    = '<' . md5(uniqid('', true)) . '@' . $host . '>';
    $date     = date('r');
    $fromEnc  = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
    $toEnc    = '=?UTF-8?B?' . base64_encode($toName)   . '?=';
    $subjEnc  = '=?UTF-8?B?' . base64_encode($subject)  . '?=';

    $headers  = "Date: {$date}\r\n";
    $headers .= "From: {$fromEnc} <{$from}>\r\n";
    $headers .= "To: {$toEnc} <{$toEmail}>\r\n";
    $headers .= "Subject: {$subjEnc}\r\n";
    $headers .= "Message-ID: {$msgId}\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "Content-Transfer-Encoding: base64\r\n";

    // Dot-stuffing: leading dots must be doubled
    $encodedBody = chunk_split(base64_encode($bodyHtml), 76, "\r\n");
    $encodedBody = preg_replace('/^\./', '..', $encodedBody);

    fwrite($socket, $headers . "\r\n" . $encodedBody . "\r\n.\r\n");
    $dotResp = $smtpRead();
    $assertCode($dotResp, '250', 'DATA end');

    // ── QUIT ──────────────────────────────────────────────────────
    fwrite($socket, "QUIT\r\n");
    fclose($socket);

    return true;
}
