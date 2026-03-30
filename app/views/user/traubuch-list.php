<?php
$pageTitle = "Traubuch-Liste";
require_once '../../layout/header.php';
?>

<div class="page-header">
    <h1>📚 Traubuch-Liste (Tirol)</h1>
</div>

<div style="max-width:700px;">
<?php 
// Lade die overview.html Datei direkt
$overviewPath = dirname(__DIR__, 4) . '/stammbaum-daten/overview.html';
if (file_exists($overviewPath)) {
    include $overviewPath;
} else {
    echo '<div class="alert alert-warning">⚠️ Traubuch-Übersicht nicht verfügbar.</div>';
}
?>
</div>

<?php require_once '../../layout/footer.php'; ?>