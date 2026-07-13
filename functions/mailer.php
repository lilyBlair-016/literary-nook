<?php
/**
 * functions/mailer.php
 * -----------------------------------------------------------------------------
 * Outgoing mail. Every message is:
 *   1. Archived to storage/mail/ as an .html file, plus a line in mail.log, and
 *   2. Delivered through Brevo's REST API over HTTPS.
 *
 * Why an API and not SMTP: shared hosting blocks the outgoing SMTP ports, and a
 * blocked mail() call does not fail fast — it waits on a connection that never
 * opens, hanging the page behind it. HTTPS on port 443 is not blocked, so the
 * API works both locally and in production.
 *
 * The API key and sender address live in config/credentials.php. With no key
 * set, messages are archived but not sent, and nothing blocks.
 * -----------------------------------------------------------------------------
 */

define('MAIL_DIR', ROOT_PATH . '/storage/mail');
define('MAIL_FROM_NAME', SITE_NAME);
define('MAIL_ENABLED', MAIL_API_KEY !== '');

/**
 * Archive a message and send it.
 *
 * @return string path of the archived copy.
 */
function send_app_mail(string $toEmail, string $subject, string $htmlBody): string
{
    if (!is_dir(MAIL_DIR)) {
        @mkdir(MAIL_DIR, 0777, true);
    }

    $filename = MAIL_DIR . '/' . date('Ymd_His') . '_'
              . preg_replace('/[^a-z0-9@._-]/i', '_', $toEmail) . '.html';

    file_put_contents($filename,
        "<!-- To: {$toEmail} | Subject: {$subject} | " . date('c') . " -->\n"
      . "<h2>{$subject}</h2>\n" . $htmlBody);

    $fp = @fopen(MAIL_DIR . '/mail.log', 'a');
    if ($fp) {
        fwrite($fp, date('c') . " | {$toEmail} | {$subject} | " . basename($filename) . "\n");
        fclose($fp);
    }

    if (MAIL_ENABLED) {
        send_via_api($toEmail, $subject, $htmlBody);
    }

    return $filename;
}

/** Append a line to storage/mail/mail.log — the only record of send failures. */
function mail_log(string $message): void
{
    $fp = @fopen(MAIL_DIR . '/mail.log', 'a');
    if ($fp) {
        fwrite($fp, date('c') . " | {$message}\n");
        fclose($fp);
    }
}

/**
 * Deliver through the Brevo API. Synchronous but fast (~0.5s) and hard-capped by
 * a timeout, so a page can never hang on mail. Failures are logged, never fatal.
 *
 * @return bool true when Brevo accepted the message.
 */
function send_via_api(string $toEmail, string $subject, string $htmlBody): bool
{
    if (!function_exists('curl_init')) {
        mail_log('MAIL FAIL: cURL extension not available');
        return false;
    }

    $payload = [
        'sender'      => ['name' => MAIL_FROM_NAME, 'email' => MAIL_FROM],
        'to'          => [['email' => $toEmail]],
        'subject'     => $subject,
        'htmlContent' => '<html><body>' . $htmlBody . '</body></html>',
    ];

    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            'accept: application/json',
            'content-type: application/json',
            'api-key: ' . MAIL_API_KEY,
        ],
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => 10,
    ]);
    $response = curl_exec($ch);
    $status   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);
    curl_close($ch);

    if ($status === 201) {                 // Brevo returns 201 Created on success
        return true;
    }

    mail_log(sprintf('MAIL FAIL to %s | HTTP %d | %s',
        $toEmail, $status, $error ?: substr((string) $response, 0, 300)));
    return false;
}

/** Record an in-app notification for a user (bell icon / notifications page). */
function notify(int $userId, string $type, string $subject, string $message): void
{
    db_exec(
        'INSERT INTO notifications (user_id, type, subject, message)
         VALUES (?, ?, ?, ?)',
        [$userId, $type, $subject, $message]
    );
}
