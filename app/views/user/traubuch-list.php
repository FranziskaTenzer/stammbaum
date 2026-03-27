<?php
$pageTitle = "Traubuch-Liste";
require_once dirname(__DIR__, 2) . '/layout/header.php';
?>

<div class="page-header">
    <h1>📚 Traubuch-Liste</h1>
    <p class="subtitle">Alle verfügbaren Traubücher im Überblick</p>
</div>

<?php 
// Lade die overview.html Datei direkt
$overviewPath = dirname(__DIR__, 4) . '/stammbaum-daten/overview.html';
if (file_exists($overviewPath)) {
    include $overviewPath;
} else {
    echo '<div class="alert alert-warning">⚠️ Traubuch-Übersicht nicht verfügbar.</div>';
}
?>

<?php require_once dirname(__DIR__, 2) . '/layout/footer.php'; ?>