<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/app/lib/session-helper.php';
requireLogin();

ini_set('display_errors', 1);

if (!function_exists('getPDO')) {
    include dirname(__DIR__) . '/app/lib/include.php';
}

$pdo = getPDO();


$sqlDeleteDB = " SET FOREIGN_KEY_CHECKS = 0;
    
SET @tables = (
  SELECT GROUP_CONCAT(CONCAT('`', table_name, '`'))
  FROM information_schema.tables
  WHERE table_schema = 'stammbaum'
);
    
SET @sql = CONCAT('DROP TABLE IF EXISTS ', @tables);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
    
SET FOREIGN_KEY_CHECKS = 1;
    
";

$stmt = $pdo->prepare($sqlDeleteDB);
$stmt->execute();




// =========================
// TABELLEN ERSTELLEN
// =========================


$sqlCreateEhe = "
CREATE TABLE IF NOT EXISTS ehe (
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    externe_id VARCHAR(10),
    
    vater_id INT,
    vater_alter INT,
    mutter_id INT,
    mutter_alter INT,
    
    heiratsdatum DATE NULL,
    traubuch VARCHAR(255),
    
    CONSTRAINT unique_ehe UNIQUE (vater_id, mutter_id, heiratsdatum)
);
";

$stmt = $pdo->prepare($sqlCreateEhe);
$stmt->execute();


$sqlCreatePerson = "
CREATE TABLE IF NOT EXISTS person (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vorname VARCHAR(255) NOT NULL,
    nachname VARCHAR(255) NOT NULL,
    
    vater_id INT NULL,
    mutter_id INT NULL,
    
    geburtsdatum DATE NULL,
    sterbedatum DATE NULL,
    geburtsort VARCHAR(255) NULL,
    sterbeort VARCHAR(255) NULL,
    hof VARCHAR(255) NULL,
    ort VARCHAR(255) NULL,
    bemerkung TEXT NULL,
    
    referenz_ehe_id INT NULL,
    
    CONSTRAINT fk_vater FOREIGN KEY (vater_id) REFERENCES person(id),
    CONSTRAINT fk_mutter FOREIGN KEY (mutter_id) REFERENCES person(id),
    CONSTRAINT fk_referenz_ehe FOREIGN KEY (referenz_ehe_id) REFERENCES ehe(id),
    
    CONSTRAINT unique_person_with_parents UNIQUE (vorname, nachname, geburtsdatum, vater_id, mutter_id)
);
";

$stmt = $pdo->prepare($sqlCreatePerson);
$stmt->execute();

$sqlMultiEhe = "
    
CREATE UNIQUE INDEX unique_ehe_multi
ON ehe (externe_id, vater_id, mutter_id, heiratsdatum);
";

$stmt = $pdo->prepare($sqlMultiEhe);
$stmt->execute();

echo "<h2>Datenbank erfolgreich gelöscht und neu erstellt</h2><br />";
echo "<a href='../app/views/user/index.php' style='background:#667eea; color:white; padding:10px 20px; border-radius:6px; text-decoration:none;'>← Zurück zur Startseite</a>";
?>
