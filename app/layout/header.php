<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__) . '/lib/session-helper.php';
requireLogin();

$pageTitle = isset($pageTitle) ? $pageTitle : 'Stammbaum';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?= htmlspecialchars(getBaseUrl()) ?>/app/layout/style-menu.css">
    <title><?= htmlspecialchars($pageTitle) ?> - Stammbaum</title>
</head>
<body>
<?php require_once dirname(__DIR__) . '/layout/sidebar-menu.php'; ?>
<main class="main-content">
