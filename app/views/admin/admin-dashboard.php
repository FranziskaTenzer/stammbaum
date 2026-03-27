<?php
$pageTitle = "Admin Dashboard";
require_once dirname(__DIR__, 2) . '/layout/header.php';
require_once dirname(__DIR__, 2) . '/lib/include.php';

if (!isAdmin()) {
    die('❌ Zugriff verweigert! Diese Seite ist nur für Administratoren zugänglich.');
}

$pdo = getPDO();

// Statistiken laden
$stats = [];
try {
    $stats['personen'] = $pdo->query("SELECT COUNT(*) FROM person")->fetchColumn();
    $stats['ehen'] = $pdo->query("SELECT COUNT(*) FROM ehe")->fetchColumn();
    $stats['traubuecher'] = $pdo->query("SELECT COUNT(DISTINCT traubuch) FROM ehe WHERE traubuch IS NOT NULL AND traubuch != ''")->fetchColumn();
} catch (Exception $e) {
    $stats = ['personen' => '—', 'ehen' => '—', 'traubuecher' => '—'];
}

$baseUrl = getBaseUrl();
?>

<div class="page-header">
    <h1>⚙️ Admin Dashboard</h1>
    <p class="subtitle">Verwaltung und Übersicht</p>
</div>

<div class="content-grid">
    <div class="content-card admin-card">
        <h3>📊 Datenbank-Statistik</h3>
        <ul style="list-style:none; padding:0; margin:10px 0;">
            <li>👤 Personen: <strong><?= htmlspecialchars($stats['personen']) ?></strong></li>
            <li>💍 Ehen: <strong><?= htmlspecialchars($stats['ehen']) ?></strong></li>
            <li>📚 Traubücher: <strong><?= htmlspecialchars($stats['traubuecher']) ?></strong></li>
        </ul>
    </div>

    <div class="content-card admin-card">
        <h3>📥 Daten importieren</h3>
        <p>Importieren Sie neue Ortsdaten aus Traubüchern</p>
        <a href="<?= htmlspecialchars($baseUrl) ?>/app/views/admin/import-orte.php" class="btn btn-warning">➕ Orte importieren</a>
    </div>

    <div class="content-card admin-card">
        <h3>🔄 Kompletter Neustart</h3>
        <p>Datenbank leeren und alle Daten neu importieren</p>
        <a href="<?= htmlspecialchars($baseUrl) ?>/app/views/admin/re-create-all.php"
           onclick="return confirm('⚠️ WARNUNG: Dies löscht ALLE Daten! Fortfahren?');"
           class="btn btn-danger">🔄 Neu erstellen</a>
    </div>

    <div class="content-card admin-card">
        <h3>🗃️ Datenbank initialisieren</h3>
        <p>Tabellen löschen und neu erstellen</p>
        <a href="<?= htmlspecialchars($baseUrl) ?>/app/views/admin/db-init.php"
           onclick="return confirm('⚠️ WARNUNG: Dies löscht die Datenbankstruktur! Fortfahren?');"
           class="btn btn-danger">🗃️ DB initialisieren</a>
    </div>

    <div class="content-card admin-card">
        <h3>👨≈👨 Ähnliche Vornamen</h3>
        <p>Vornamen im Stammbaum auf Ähnlichkeiten prüfen</p>
        <a href="<?= htmlspecialchars($baseUrl) ?>/app/views/admin/vornamen-aehnlich.php" class="btn btn-primary">Vornamen prüfen</a>
    </div>

    <div class="content-card admin-card">
        <h3>👤≈👤 Ähnliche Nachnamen</h3>
        <p>Nachnamen im Stammbaum auf Ähnlichkeiten prüfen</p>
        <a href="<?= htmlspecialchars($baseUrl) ?>/app/views/admin/nachnamen-aehnlich.php" class="btn btn-primary">Nachnamen prüfen</a>
    </div>
</div>

<?php require_once dirname(__DIR__, 2) . '/layout/footer.php'; ?>
