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

// Helper function to sanitize input
function sanitizeInput($input) {
    return htmlspecialchars(strip_tags($input));
}

// Ensures the nachrichten table exists (safe to call multiple times)
function ensureNachrichtenTable($pdo) {
    $sql = file_get_contents(__DIR__ . '/nachrichten.sql');
    $pdo->exec($sql);
}

// Other helper functions can be added here
