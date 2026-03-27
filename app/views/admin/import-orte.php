<?php
$pageTitle = "Orte importieren";
require_once dirname(__DIR__, 2) . '/layout/header.php';

if (!isAdmin()) {
    die('❌ Zugriff verweigert! Diese Seite ist nur für Administratoren zugänglich.');
}

if (!function_exists('getPDO')) {
    require_once dirname(__DIR__, 2) . '/lib/include.php';
}

$pdo = getPDO();

$SKIP_AUTO_IMPORT = true;
require_once dirname(__DIR__, 2) . '/import/importOrte.php';
?>

<div class="page-header">
    <h1>📋 Orte importieren</h1>
    <p class="subtitle">Importieren Sie Ortsdaten aus Traubüchern</p>
</div>

<div style="background:white; padding:20px; border-radius:8px; box-shadow:0 2px 4px rgba(0,0,0,0.1);">
    <?php runOrteImport(); ?>
</div>

<?php require_once dirname(__DIR__, 2) . '/layout/footer.php'; ?>
