<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(600);
ob_start();

require_once dirname(__DIR__, 2) . '/layout/header.php';

if (!isAdmin()) {
    die('❌ Zugriff verweigert! Nur für Administratoren.');
}

if (!function_exists('getPDO')) {
    include dirname(__DIR__, 2) . '/lib/include.php';
}

$pdo = getPDO();

// ===========================
// SCHRITT 1: Datenbank initialisieren (INLINE - ohne require!)
// ===========================
echo "<div style='background:#e8f5e9; padding:20px; margin:10px 0; border-radius:8px;'>";
echo "<h2>🔧 SCHRITT 1: Datenbank wird initialisiert...</h2>";
ob_flush();
flush();

try {
    // Datenbank INLINE löschen und neu erstellen
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
    
    // ehe Tabelle erstellen
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
    
    // person Tabelle erstellen
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
    
    echo "<p style='color:green;'>✅ Datenbank erfolgreich initialisiert!</p>";
} catch (Exception $e) {
    echo "<p style='color:red;'>❌ Fehler: " . htmlspecialchars($e->getMessage()) . "</p>";
    die();
}

echo "</div>";
ob_flush();
flush();

echo "<p style='color:#999; font-size:12px;'>⏳ Warte 3 Sekunden...</p>";
ob_flush();
flush();
sleep(3);

// ===========================
// SCHRITT 2: Thierbach importieren
// ===========================
echo "<div style='background:#e3f2fd; padding:20px; margin:10px 0; border-radius:8px;'>";
echo "<h2>📖 SCHRITT 2: Thierbach-Daten werden importiert...</h2>";
ob_flush();
flush();

try {
    $SKIP_AUTO_IMPORT = true;
    require_once dirname(__DIR__, 2) . '/lib/importThierbach.php';
    runThierbachImport();
    echo "<p style='color:green;'>✅ Thierbach-Import abgeschlossen!</p>";
} catch (Exception $e) {
    echo "<p style='color:red;'>❌ Fehler: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "</div>";
ob_flush();
flush();

echo "<p style='color:#999; font-size:12px;'>⏳ Warte 5 Sekunden für Datenbankoperationen...</p>";
ob_flush();
flush();
sleep(5);

// ===========================
// SCHRITT 3: Orte importieren
// ===========================
echo "<div style='background:#fff3e0; padding:20px; margin:10px 0; border-radius:8px;'>";
echo "<h2>🗺️ SCHRITT 3: Orte-Daten werden importiert...</h2>";
ob_flush();
flush();

try {
    $SKIP_AUTO_IMPORT = true;
    require_once dirname(__DIR__, 2) . '/lib/importOrte.php';
    runOrteImport();
    echo "<p style='color:green;'>✅ Orte-Import abgeschlossen!</p>";
} catch (Exception $e) {
    echo "<p style='color:red;'>❌ Fehler: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "</div>";
ob_flush();
flush();

echo "<p style='color:#999; font-size:12px;'>⏳ Warte 3 Sekunden...</p>";
ob_flush();
flush();
sleep(3);

// ===========================
// Finale Statistik
// ===========================
try {
    $result_persons = $pdo->query("SELECT COUNT(*) as cnt FROM person");
    $row_persons = $result_persons->fetch(PDO::FETCH_ASSOC);
    
    $result_ehen = $pdo->query("SELECT COUNT(*) as cnt FROM ehe");
    $row_ehen = $result_ehen->fetch(PDO::FETCH_ASSOC);
    
    echo "<div style='background:#e8f5e9; padding:15px; margin:10px 0; border-radius:8px;'>";
    echo "<h3>📊 Datenbank-Statistik:</h3>";
    echo "<ul>";
    echo "<li><strong>Personen:</strong> " . $row_persons['cnt'] . "</li>";
    echo "<li><strong>Ehen:</strong> " . $row_ehen['cnt'] . "</li>";
    echo "</ul>";
    echo "</div>";
} catch (Exception $e) {
    echo "<p style='color:orange;'>⚠️ Statistik konnte nicht gelesen werden</p>";
}

// ===========================
// FERTIG
// ===========================
echo "<div style='background:#f3e5f5; padding:20px; margin:10px 0; border-radius:8px; border:2px solid #9c27b0;'>";
echo "<h2 style='color:#9c27b0;'>🎉 ALLE IMPORTE ERFOLGREICH!</h2>";
echo "<p>Alle Daten wurden mit einer einzigen Datenbankverbindung importiert.</p>";
echo "<a href='../user/index.php' style='background:#667eea; color:white; padding:10px 20px; border-radius:6px; text-decoration:none; display:inline-block;'>← Zurück zur Startseite</a>";
echo "</div>";
ob_flush();
flush();

require_once dirname(__DIR__, 2) . '/layout/footer.php';
?>
