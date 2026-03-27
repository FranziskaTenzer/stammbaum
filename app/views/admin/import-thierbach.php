<?php
$pageTitle = "Thierbach Importieren";
require_once '../../layout/header.php';
require_once '../../lib/include.php';

if (!isAdmin()) {
    die('❌ Zugriff verweigert! Nur für Administratoren.');
}

$pdo = getPDO();

$SKIP_AUTO_IMPORT = true;
require_once '../../lib/importThierbach.php';

runOrteImport();
?>

<?php require_once '../../layout/footer.php'; ?>
