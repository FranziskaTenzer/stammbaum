<?php
$pageTitle = "Admin - Startseite";
require_once dirname(__DIR__, 2) . '/layout/header.php';

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
        <h3>➕ Neue Orte importieren</h3>
        <p>Importieren Sie neue Ortsdaten aus Textdateien</p>
        <a href="import-orte.php" class="btn btn-warning">Orte importieren</a>
    </div>

    <div class="content-card admin-card">
        <h3>🔄 Kompletter Neustart</h3>
        <p>Datenbank zurücksetzen und alle Daten neu importieren</p>
        <a href="re-create-all.php"
           onclick="return confirm('⚠️ WARNUNG: Dies löscht ALLE Daten und importiert alles neu. Möchten Sie fortfahren?');"
           class="btn btn-danger">Neustart</a>
    </div>

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
        <h3>To Do List:</h3>
        <ul>
        <li>Re-create-all überschreibt Werte, die beim einzelnen aufrufen nicht überschrieben werden</li>
        <li>DB Tabelle für User</li>
        <li>- Registrierung (Bezahlung?)</li>
        <li>- Löschen</li>
        <li>Ausgabe des Stammbaumes noch mal überarbeiten (lassen)?</li>
        <li>Ähnliche Namen richtig finden/vergleichen im Tiroler Archiv</li>
        <li>Verlinkungen nach der Umstruktierung</li>
        <li>Als Datum auch xx oder 00 erlauben wenn die Zahlen nicht richtig zu erkennen waren</li>
        <li></li>
        <li></li>
        <li></li>
        </ul>
    </div>
</div>

<?php require_once dirname(__DIR__, 2) . '/layout/footer.php'; ?>
