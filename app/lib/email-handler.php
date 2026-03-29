<?php

/**
 * Email-Handler für Stammbaum
 *
 * Zentrale Verwaltung für das Generieren von Tokens, Versenden von E-Mails
 * und Protokollieren in der email_log-Tabelle.
 */

require_once __DIR__ . '/include.php';
require_once __DIR__ . '/email-templates.php';

/**
 * Generiert einen kryptografisch sicheren Zufalls-Token (64 Hex-Zeichen).
 */
function generateToken(): string
{
    return bin2hex(random_bytes(32));
}

/**
 * Hasht einen Token mit SHA-256 zur sicheren Speicherung in der Datenbank.
 */
function hashToken(string $token): string
{
    return hash('sha256', $token);
}

/**
 * Ermittelt die Basis-URL der Anwendung.
 */
function getBaseUrl(): string
{
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $protocol . '://' . $host;
}

/**
 * Versendet eine HTML-E-Mail.
 *
 * Der Absender kann hier angepasst werden, wenn eine eigene Domain verfügbar ist.
 *
 * @return bool  true bei Erfolg, false bei Fehler
 */
function sendEmail(string $recipientEmail, string $subject, string $htmlBody): bool
{
    // TODO: Absender-E-Mail an die eigene Domain anpassen
    $senderEmail = defined('STAMMBAUM_MAIL_FROM') ? STAMMBAUM_MAIL_FROM : 'noreply@stammbaum.example';
    $headers = implode("\r\n", [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: Stammbaum <' . $senderEmail . '>',
        'X-Mailer: PHP/' . phpversion(),
    ]);
    return mail($recipientEmail, $subject, $htmlBody, $headers);
}

/**
 * Protokolliert eine gesendete (oder fehlgeschlagene) E-Mail in email_log.
 */
function logEmail(PDO $pdo, string $emailType, string $recipientEmail, string $status = 'sent', ?array $metadata = null): void
{
    ensureEmailLogTable($pdo);
    $stmt = $pdo->prepare(
        "INSERT INTO email_log (email_type, recipient_email, status, metadata)
         VALUES (?, ?, ?, ?)"
    );
    $stmt->execute([
        $emailType,
        $recipientEmail,
        $status,
        $metadata !== null ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null,
    ]);
}

/**
 * Sendet die Registrierungs-Bestätigungsemail und protokolliert den Versand.
 *
 * @param string $token  Klartexttoken (wird in der URL verwendet; im DB steht der Hash)
 */
function sendRegistrationConfirmation(PDO $pdo, string $username, string $recipientEmail, string $token): bool
{
    $link     = getBaseUrl() . '/stammbaum/public/verify-email.php?token=' . urlencode($token);
    $template = getRegistrationConfirmationTemplate($username, $link);
    $ok       = sendEmail($recipientEmail, $template['subject'], $template['body']);
    $status   = $ok ? 'sent' : 'failed';
    logEmail($pdo, 'registration', $recipientEmail, $status, ['username' => $username]);
    return $ok;
}

/**
 * Sendet die Passwort-Zurücksetzen-Email und protokolliert den Versand.
 *
 * @param string $token  Klartexttoken
 */
function sendPasswordReset(PDO $pdo, string $username, string $recipientEmail, string $token): bool
{
    $link     = getBaseUrl() . '/stammbaum/public/reset-password.php?token=' . urlencode($token);
    $template = getPasswordResetTemplate($username, $link);
    $ok       = sendEmail($recipientEmail, $template['subject'], $template['body']);
    $status   = $ok ? 'sent' : 'failed';
    logEmail($pdo, 'password_reset', $recipientEmail, $status, ['username' => $username]);
    return $ok;
}

/**
 * Sendet die Bestätigungsemail nach Account-Löschung und protokolliert den Versand.
 */
function sendAccountDeleted(PDO $pdo, string $username, string $recipientEmail): bool
{
    $template = getAccountDeletedTemplate($username, $recipientEmail);
    $ok       = sendEmail($recipientEmail, $template['subject'], $template['body']);
    $status   = $ok ? 'sent' : 'failed';
    logEmail($pdo, 'account_deleted', $recipientEmail, $status, ['username' => $username]);
    return $ok;
}
