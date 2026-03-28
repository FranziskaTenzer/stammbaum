<?php
$pageTitle = "Stammbaum Startseite";
require_once '../../layout/header.php';
?>

<div class="page-header">
    <h1>🌳 Willkommen zum Stammbaum</h1>
    <p class="subtitle">Wählen Sie ein Menü-Element, um zu beginnen</p>
</div>

<div class="content-grid">
    <div class="content-card">
        <h3>👤 Personensuche</h3>
        <p>Suchen Sie nach Personen im Stammbaum nach Vor- und Nachname</p>
        <a href="stammbaum-search.php" class="btn btn-primary">Zur Suche</a>
    </div>

    <div class="content-card">
        <h3>📚 Traubuch-Liste</h3>
        <p>Durchsuchen Sie alle verfügbaren Traubücher</p>
        <a href="traubuch-list.php" class="btn btn-primary">Traubücher ansehen</a>
    </div>

</div>

<?php require_once '../../layout/footer.php'; ?>
