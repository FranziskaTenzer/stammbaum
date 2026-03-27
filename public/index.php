<?php 
$pageTitle = "Stammbaum Startseite";
require_once dirname(__DIR__) . '/app/layout/header.php';

$baseUrl = getBaseUrl();
?>

<div class="page-header">
    <h1>🌳 Willkommen zum Stammbaum</h1>
    <p class="subtitle">Wählen Sie ein Menü-Element, um zu beginnen</p>
</div>

<div class="content-grid">
    <div class="content-card">
        <h3>👤 Personensuche</h3>
        <p>Suchen Sie nach Personen im Stammbaum nach Vor- und Nachname</p>
        <a href="<?= htmlspecialchars($baseUrl) ?>/app/views/user/stammbaum-search.php" class="btn btn-primary">Zur Suche</a>
    </div>

    <div class="content-card">
        <h3>📊 Stammbaum anzeigen</h3>
        <p>Zeigen Sie den Stammbaum für eine bestimmte Person an</p>
        <a href="<?= htmlspecialchars($baseUrl) ?>/app/views/user/stammbaum-display.php" class="btn btn-primary">Stammbaum öffnen</a>
    </div>

    <div class="content-card">
        <h3>📚 Traubuch-Liste</h3>
        <p>Durchsuchen Sie alle verfügbaren Traubücher</p>
        <a href="<?= htmlspecialchars($baseUrl) ?>/app/views/user/traubuch-list.php" class="btn btn-primary">Traubücher ansehen</a>
    </div>

    <?php if (isLoggedIn()): ?>
    <div class="content-card admin-card">
        <h3>⚙️ Admin-Bereich</h3>
        <p>Verwalten Sie die Daten und Importe</p>
        <a href="<?= htmlspecialchars($baseUrl) ?>/app/views/admin/admin-dashboard.php" class="btn btn-warning">Admin-Dashboard</a>
    </div>
    <?php endif; ?>
</div>

<?php require_once dirname(__DIR__) . '/app/layout/footer.php'; ?>
