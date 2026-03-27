<?php
$pageTitle = "Orte Importieren";
require_once '../../layout/header.php';
require_once '../../lib/include.php';

if (!isAdmin()) {
    die('❌ Zugriff verweigert! Nur für Administratoren.');
}

$pdo = getPDO();

$SKIP_AUTO_IMPORT = true;
require_once '../../lib/db-init.php';

runOrteImport();
?>

<?php require_once '../../layout/footer.php'; ?>
