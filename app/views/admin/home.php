<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$pageTitle = "Admin - Startseite";
require_once '../../layout/header.php';

if (!isAdmin()) {
    die('❌ Zugriff verweigert! Nur für Administratoren.');
}
?>

<div class="page-header">
    <h1>⚙️ Admin-Bereich</h1>
    <p class="subtitle">Verwaltung und Import-Funktionen</p>
</div>

<div class="content-grid">
    
    <div class="content-card admin-card">
        <h3>👨≈👨 Ähnliche Vornamen</h3>
        <p>Gruppen ähnlicher Vornamen anzeigen</p>
        <a href="vornamen-similar.php" class="btn btn-primary">Vornamen prüfen</a>
    </div>

    <div class="content-card admin-card">
        <h3>👤≈👤 Ähnliche Nachnamen</h3>
        <p>Gruppen ähnlicher Nachnamen anzeigen und mit Tirol-Archiv vergleichen</p>
        <a href="nachnamen-similar.php" class="btn btn-primary">Nachnamen prüfen</a>
    </div>

    <div class="content-card admin-card">
        <h3>🗺️ Nachnamen nach Ort</h3>
        <p>Tirol-Archiv-Nachnamen je Ort alphabetisch auflisten</p>
        <a href="nachnamen-orte.php" class="btn btn-primary">Ortslisten öffnen</a>
    </div>

    <div class="content-card admin-card">
        <h3>🧭 Ehen-Dubletten prüfen</h3>
        <p>Dubletten anhand von Hochzeitsdatum + vollständigen Namen von Bräutigam und Braut</p>
        <a href="ehen-dubletten.php" class="btn btn-primary">Dubletten anzeigen</a>
    </div>

    <div class="content-card admin-card">
        <h3>📚 Nachnamen Tirol (A-Z)</h3>
        <p>Alle vorhandenen Tirol-Archiv-Nachnamen alphabetisch gruppiert anzeigen</p>
        <a href="nachnamen-tirol.php" class="btn btn-primary">Gesamtliste öffnen</a>
    </div>
    
    <div class="content-card admin-card">
        <h3>👥 Benutzer-Verwaltung</h3>
        <p>Alle registrierten Benutzer und deren Verifizierungsstatus anzeigen</p>
        <a href="benutzer-verwaltung.php" class="btn btn-primary">Benutzer verwalten</a>
    </div>
    
    <div class="content-card admin-card">
        <h3>To Do List:</h3>
        <ul>
            <li>Impressum und Datenschutz - von mir überarbeiten</li>
            <li>Stammbaum so erweitern, dass jeweils beide Linie angezeigt werden (da gestern stehen geblieben)</li>
            <li>druckversion Stammbaum</li>
            <li>Speichern für welchen Namen bezahlt wurde, dieser kann sich jeder zeit wieder angezeigt werden</li>
            <li>Liebe siegt immer fragen wegen Zahlungsanbieter</li>
            <li>Vergleiche fertig für: Thierbach, Auffach</li>
            <li>Wenn vergleiche fertig sind, noch schauen was an ??? übrig ist</li>
            <li>SUCHE anpassen: c/k, b/p, u/ü a/ä Varianten mit erlauben
                Info: meist gängige Schreibweise von heute z.B. Flatscher (in den Büchern mehr als Flatschner )
Christoph Christian 
                Matthäus und Co => Matthias
            </li>
            <li>Profil: Checkbox ob Email versendet werden darf wenn neue Orte (Jahresbereiche dazu kamen)</li>
            <li>Email Benachrichtung wenn neue Orte dazu gekommen sind, 
                direkt mit Auswahl/Eingabe des Ortes auch in der Email den Namen hinzufügen</li>
            
            <li><b>Für später:</b>
                <ul>
                	<li>e2e Tests, aktuell noch nicht nötig</li>
                        <ul>
                            <li>katharina Kostenzer (generelle Suche, stimmt genaue Anzahl?)</li>
                            <li>10.01.1927 Alois Muther</li>
                            <li>29.05.1848 Andreas Eder Bemerkung!</li>
                        </ul>
                	<li>paypal Anbindung bzw. Kreditkarte</li>
                	<li>kompletten Stammbaum wenn 100€ bezahlt wurden</li>
                	<li>Stammbaum inkl. Tanten und Onkel 200€</li>
                	<li>Transkripbus für die Traubücher ohne Tabellenlayout in latein prüfen</li>
                </ul>
            </li>
        </ul>
    </div>
  
    <div class="content-card admin-card">
        <h3>⛃ Datenbank löschen und neu erstellen</h3>
        <p>Die Datenbank wird komplett gelöscht und neu erstellt</p>
     	<a href="recreate-db.php" onclick="return confirm('⚠️ WARNUNG: Die Datenbank wird komplett gelöscht und neu erstellt. Möchten Sie fortfahren?');" class="btn btn-warning">⛃ Datenbank löschen und neu erstellen</a>
    </div>
    
    <div class="content-card admin-card">
        <h3>📝 Thierbach importieren</h3>
        <p>Die Daten von Thierbach werden importiert</p>
        <a href="import-thierbach.php" onclick="return confirm('⚠️ WARNUNG: Die Thierbach-Daten werden importiert. Möchten Sie fortfahren?');" class="btn btn-warning">Thierbach importieren</a>
    </div>
    
    <div class="content-card admin-card">
        <h3>📝 Neue Orte importieren</h3>
        <p>Die Daten von allen anderen Orten werden importiert</p>
        <a href="import-orte.php" onclick="return confirm('⚠️ WARNUNG: Alle Orte-Daten werden importiert. Möchten Sie fortfahren?');" class="btn btn-warning">Orte importieren</a>
    </div>

    <div class="content-card admin-card">
        <h3>🔄 Kompletter Neustart</h3>
        <p>Datenbank zurücksetzen und alle Daten neu importieren</p>
        <a href="re-create-all.php"
           onclick="return confirm('⚠️ WARNUNG: Dies löscht ALLE Daten und importiert alles neu. Möchten Sie fortfahren?');"
           class="btn btn-warning">Neustart</a>
    </div>

</div>

<?php require_once '../../layout/footer.php'; ?>