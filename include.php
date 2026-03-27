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

function formatDBDateOrNull($dateStr, $fromFormat = 'Y-m-d', $toFormat = 'd.m.Y') {
    if ($dateStr == null) {
        return null;
    }
    // Handle uncertain dates containing 'x' placeholders (e.g. "22.xx.1816" → "22.??.1816")
    if (stripos($dateStr, 'x') !== false) {
        return str_ireplace('xx', '??', $dateStr);
    }
    // Primary storage format is DD.MM.YYYY — parse and reformat as needed
    $dt = DateTime::createFromFormat('d.m.Y', $dateStr);
    if ($dt) {
        return $dt->format($toFormat);
    }
    // Fallback: try the caller-supplied $fromFormat (e.g. 'Y-m-d' for heiratsdatum in ehe table)
    $dt = DateTime::createFromFormat($fromFormat, $dateStr);
    return $dt ? $dt->format($toFormat) : null;
}

// Helper function to sanitize input
function sanitizeInput($input) {
    return htmlspecialchars(strip_tags($input));
}

// Other helper functions can be added here
?>