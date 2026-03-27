<?php
// Call the required initialization and import scripts in sequence.
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ===========================
// SCHRITT 1: Datenbank initialisieren
// ===========================
echo "<div style='background:#e8f5e9; padding:20px; margin:10px 0; border-radius:8px;'>";
echo "<h2>🔧 SCHRITT 1: Datenbank wird initialisiert...</h2>";
ob_flush();
flush();

require_once 'db_init.php';

echo "<p style='color:green;'>✅ Datenbank erfolgreich initialisiert!</p>";
echo "</div>";
ob_flush();
flush();

// ===========================
// SCHRITT 2: Thierbach importieren
// ===========================
echo "<div style='background:#e3f2fd; padding:20px; margin:10px 0; border-radius:8px;'>";
echo "<h2>📖 SCHRITT 2: Thierbach-Daten werden importiert...</h2>";
ob_flush();
flush();

require_once 'importThierbach.php';

echo "<p style='color:green;'>✅ Thierbach-Import erfolgreich abgeschlossen!</p>";
echo "</div>";
ob_flush();
flush();

// ===========================
// SCHRITT 3: Orte importieren
// ===========================
echo "<div style='background:#fff3e0; padding:20px; margin:10px 0; border-radius:8px;'>";
echo "<h2>🗺️ SCHRITT 3: Orte-Daten werden importiert...</h2>";
ob_flush();
flush();

require_once 'importOrte.php';

echo "<p style='color:green;'>✅ Orte-Import erfolgreich abgeschlossen!</p>";
echo "</div>";
ob_flush();
flush();

// ===========================
// FERTIG
// ===========================
echo "<div style='background:#f3e5f5; padding:20px; margin:10px 0; border-radius:8px; border:2px solid #9c27b0;'>";
echo "<h2 style='color:#9c27b0;'>🎉 ALLE IMPORTE ERFOLGREICH ABGESCHLOSSEN!</h2>";
echo "<p>Die Stammbaum-Datenbank wurde komplett neu erstellt und mit allen Daten gefüllt.</p>";
echo "<a href='stammbaum.php' style='background:#667eea; color:white; padding:10px 20px; border-radius:6px; text-decoration:none; display:inline-block; margin-top:10px;'>← Zurück zur Startseite</a>";
echo "</div>";
ob_flush();
flush();

?>