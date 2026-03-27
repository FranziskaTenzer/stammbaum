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
</div>

<?php require_once dirname(__DIR__, 2) . '/layout/footer.php'; ?>
