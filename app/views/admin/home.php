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
        <a href="vornamen-similar.php" class="btn btn-warning">Vornamen prüfen</a>
    </div>

    <div class="content-card admin-card">
        <h3>👤≈👤 Ähnliche Nachnamen</h3>
        <p>Gruppen ähnlicher Nachnamen anzeigen und mit Tirol-Archiv vergleichen</p>
        <a href="nachnamen-similar.php" class="btn btn-warning">Nachnamen prüfen</a>
    </div>
    
    <div class="content-card admin-card">
    <h3>👥 Benutzer-Verwaltung</h3>
    <p>Alle registrierten Benutzer und deren Verifizierungsstatus anzeigen</p>
    <a href="benutzer-verwaltung.php" class="btn btn-primary">Benutzer verwalten</a>
</div>
    
    <div class="content-card admin-card">
        <h3>To Do List:</h3>
        <ul>
            <li>Re-create-all überschreibt Werte, die beim einzelnen aufrufen nicht überschrieben werden</li>
            <li>Ausgabe des Stammbaumes noch mal überarbeiten (lassen)?</li>
            <li>Ähnliche Namen richtig finden/vergleichen im Tiroler Archiv</li>
            <li>Suche zeigt auch Einträge als Mutter an (als es sonst keine Daten zur Person geben sollte)</li>
            <li>Impressum und Datenschutz - von mir überarbeiten</li>
            <li>Import prüfen lassen
            MariaDB [stammbaum]> select * from person where id=7820; (ebenfalls 7883)
+------+----------------------------------------------+-------------+----------+-----------+--------------+-------------+------------+-----------+------+------+-----------+-----------------+
| id   | vorname                                      | nachname    | vater_id | mutter_id | geburtsdatum | sterbedatum | geburtsort | sterbeort | hof  | ort  | bemerkung | referenz_ehe_id |
+------+----------------------------------------------+-------------+----------+-----------+--------------+-------------+------------+-----------+------+------+-----------+-----------------+
| 7820 | Maria Gwiggner, uneheliche Tochter von Jakob | Lohrstaller |     NULL |      NULL | NULL         | NULL        | NULL       | NULL      | NULL | NULL |           |            NULL |
+------+----------------------------------------------+-------------+----------+-----------+--------------+-------------+------------+-----------+------+------+-----------+-----------------+
            
            </li>
            <li>User kann eigene Nachrichten löschen (weg für immer? Inkl. Antworten)</li>
             <li>Admin bekommt Userliste (Name und Email, registriert seit)</li>
              <li>Info Personensuche: kirchenbuch einträge bis max 1938</li>
              <li>Scheidung mit darstellen (ggfs. Importieren das Datum)</li>
           
            <li><b>Für später:</b>
                <ul>
                	<li>e2e Tests, aktuell noch nicht nötig</li>
                	<li>paypal Anbindung bzw. Kreditkarte</li>
                	<li>kompletten Stammbaum wenn 100€ bezahlt wurden</li>
                	<li>Stammbaum inkl. Tanten und Onkel 200€</li>
                	
                </ul>
            </li>
         
            </ul>
    </div>
    <br />
    <div class="content-card admin-card">
        <h3>⛃ Datenbank löschen und neu erstellen</h3>
        <p>Die Datenbank wird komplett gelöscht und neu erstellt</p>
     	<a href="recreate-db.php" onclick="return confirm('⚠️ WARNUNG: Die Datenbank wird komplett gelöscht und neu erstellt. Möchten Sie fortfahren?');">⛃ Datenbank löschen und neu erstellen</a>
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
           class="btn btn-danger">Neustart</a>
    </div>

</div>

<?php require_once '../../layout/footer.php'; ?>