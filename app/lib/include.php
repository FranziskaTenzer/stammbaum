<?php


function getPDO() {
    
    $host = 'localhost';       // oder IP-Adresse
    $benutzer = 'franziska';
    $passwort = 'Rychp27g!';
    $datenbank = 'stammbaum';
    $charset = 'utf8mb4';
    
    $dsn =null;
    $options = null;
    $pdo = null;
    
    $dsn = "mysql:host=$host;dbname=$datenbank;charset=$charset";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];
    
    try {
        $pdo = new PDO($dsn, $benutzer, $passwort, $options);
    } catch (\PDOException $e) {
        die("DB-Verbindung fehlgeschlagen: " . $e->getMessage());
    }
    
    return $pdo;
}

function parseFlexibleDate($dateStr) {
    if (!$dateStr) return null;

    // Already in ISO format: YYYY-MM-DD, YYYY-MM-xx, YYYY-xx-xx
    if (preg_match('/^\d{4}-(0[1-9]|1[0-2]|xx)-(0[1-9]|[12]\d|3[01]|xx)$/', $dateStr)) {
        return $dateStr;
    }

    // German format with optional xx or 00: DD.MM.YYYY, xx.MM.YYYY, xx.xx.YYYY, 00.00.YYYY
    if (preg_match('/^(0[1-9]|[12]\d|3[01]|xx|00)\.(0[1-9]|1[0-2]|xx|00)\.(\d{4})$/', $dateStr, $m)) {
        $day   = ($m[1] === '00') ? 'xx' : $m[1];
        $month = ($m[2] === '00') ? 'xx' : $m[2];
        $year  = $m[3];
        return "$year-$month-$day";
    }

    // Standard date string (fallback)
    $timestamp = strtotime(str_replace('.', '-', $dateStr));
    if ($timestamp !== false) {
        return date('Y-m-d', $timestamp);
    }

    return null;
}

function formatFlexibleDate($dateStr, $toFormat = 'd.m.Y') {
    if (!$dateStr) return null;

    if (preg_match('/^(\d{4})-(0[1-9]|1[0-2]|xx)-(0[1-9]|[12]\d|3[01]|xx)$/', $dateStr, $m)) {
        $year  = $m[1];
        $month = $m[2];
        $day   = $m[3];

        if ($toFormat === 'd.m.Y') {
            return "$day.$month.$year";
        } elseif ($toFormat === 'Y-m-d') {
            return "$year-$month-$day";
        }
    }

    return null;
}

function formatDBDateOrNull($dateStr, $fromFormat = 'Y-m-d', $toFormat = 'd.m.Y') {
    if ($dateStr == null){
        return null;
    }

    // Handle flexible dates containing xx (unknown day/month)
    if (strpos($dateStr, 'xx') !== false) {
        return formatFlexibleDate($dateStr, $toFormat);
    }

    $dt = DateTime::createFromFormat($fromFormat, $dateStr);
    return $dt ? $dt->format($toFormat) : null;
}

function formatDatum($datetime, $format = 'd.m.Y H:i') {
    return htmlspecialchars((new DateTime($datetime))->format($format), ENT_QUOTES, 'UTF-8');
}

// Helper function to sanitize input
function sanitizeInput($input) {
    return htmlspecialchars(strip_tags($input));
}

// Ensures the nachrichten table exists (safe to call multiple times)
function ensureNachrichtenTable($pdo) {
    $sql = file_get_contents(__DIR__ . '/nachrichten.sql');
    $pdo->exec($sql);
}

// Ensures the email_log table exists (safe to call multiple times)
function ensureEmailLogTable($pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS email_log (
        id              INT AUTO_INCREMENT PRIMARY KEY,
        email_type      ENUM('registration', 'password_reset', 'account_deleted') NOT NULL,
        recipient_email VARCHAR(255) NOT NULL,
        sent_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        status          ENUM('pending', 'sent', 'failed') NOT NULL DEFAULT 'pending',
        metadata        JSON NULL
    )");
}

// Ensures that the email-verification and password-reset columns exist in user_profile.
// When the columns are added for the first time all existing users are marked as verified
// so they can continue to log in without having to re-verify their e-mail address.
function ensureEmailVerificationColumns($pdo) {
    $columns = [
        'email_verified'               => "TINYINT(1) NOT NULL DEFAULT 0",
        'verification_token'           => "VARCHAR(255) NULL",
        'verification_token_expires'   => "TIMESTAMP NULL",
        'password_reset_token'         => "VARCHAR(255) NULL",
        'password_reset_token_expires' => "TIMESTAMP NULL",
    ];

    $emailVerifiedJustCreated = false;

    foreach ($columns as $col => $definition) {
        try {
            $check = $pdo->query(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME   = 'user_profile'
                   AND COLUMN_NAME  = " . $pdo->quote($col)
            );
            if ($check->fetchColumn() == 0) {
                $pdo->exec("ALTER TABLE user_profile ADD COLUMN $col $definition");
                if ($col === 'email_verified') {
                    $emailVerifiedJustCreated = true;
                }
            }
        } catch (PDOException $e) {
            error_log("ensureEmailVerificationColumns failed for $col: " . $e->getMessage());
        }
    }

    // Mark all previously existing users as verified so they can still log in.
    if ($emailVerifiedJustCreated) {
        $pdo->exec("UPDATE user_profile SET email_verified = 1");
    }
}

// Other helper functions can be added here
