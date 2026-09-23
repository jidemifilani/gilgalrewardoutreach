<?php
/**
 * Outgoing notification mail.
 *
 * Mail is optional by design. Until real SMTP credentials are put in
 * config/config.php, mail_configured() returns false and send_notification()
 * is a safe no-op — every form still validates, still saves to the database
 * and still shows the visitor a success message. Nothing silently fails.
 */

require_once __DIR__ . '/functions.php';

function mail_configured(): bool
{
    return defined('MAIL_HOST')
        && MAIL_HOST !== ''
        && MAIL_HOST !== 'smtp.example.com';
}

/**
 * Sends a plain-text notification to the site's own inbox.
 * Returns false when mail is not configured, which callers may ignore.
 */
function send_notification(string $to, string $subject, string $body, string $replyTo = ''): bool
{
    if (!mail_configured() || !valid_email($to)) {
        return false;
    }

    $headers = [
        'From: ' . (defined('MAIL_FROM') ? MAIL_FROM : 'no-reply@localhost'),
        'Content-Type: text/plain; charset=utf-8',
        'MIME-Version: 1.0',
    ];

    if ($replyTo !== '' && valid_email($replyTo)) {
        $headers[] = 'Reply-To: ' . $replyTo;
    }

    // Header injection guard: strip anything that could start a new header.
    $subject = str_replace(["\r", "\n"], ' ', $subject);

    return @mail($to, $subject, $body, implode("\r\n", $headers));
}
