<?php
$pageTitle = "Orte Importieren";
require_once dirname(__DIR__, 2) . '/layout/header.php';
require_once dirname(__DIR__, 2) . '/lib/include.php';

if (!isAdmin()) {
    die('❌ Zugriff verweigert! Nur für Administratoren.');
}

$pdo = getPDO();

$SKIP_AUTO_IMPORT = true;
require_once dirname(__DIR__, 2) . '/lib/importOrte.php';

runOrteImport();
?>

<?php require_once dirname(__DIR__, 2) . '/layout/footer.php'; ?>
