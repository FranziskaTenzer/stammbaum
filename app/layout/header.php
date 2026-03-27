<?php
// Error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Session starten
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Session-Helper mit relativem Pfad laden
require_once __DIR__ . '/../lib/session-helper.php';

// Login prüfen
if (!isLoggedIn()) {
    header('Location: /stammbaum/public/login.php');
    exit;
}

$pageTitle = isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Stammbaum';

// Layouts und URLs definieren
$_layoutUrl = '/stammbaum/app/layout';
$_projectUrl = '/stammbaum';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link rel="stylesheet" href="<?= $_layoutUrl ?>/style-menu.css">
    <?php if (isset($extraHead)) echo $extraHead; ?>
</head>
<body>
    <table class="layout-table">
        <tr>
            <td class="sidebar-cell">
                <?php include __DIR__ . '/sidebar-menu.php'; ?>
            </td>
            <td class="content-cell">
                <main class="main-content">