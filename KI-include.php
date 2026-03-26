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
    if ($dateStr == null){
        return null;
    }
    $dt = DateTime::createFromFormat($fromFormat, $dateStr);
    return $dt ? $dt->format($toFormat) : null;
}

// Helper function to sanitize input
function sanitizeInput($input) {
    return htmlspecialchars(strip_tags($input));
}

// Other helper functions can be added here
?>