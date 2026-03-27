<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../lib/session-helper.php';
requireLogin();

$pageTitle = isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Stammbaum';

// Compute URL path to app/layout/ for CSS/JS links
$_docRoot = rtrim(str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'])), '/');
$_layoutUrl = str_replace($_docRoot, '', str_replace('\\', '/', realpath(__DIR__)));

// Compute URL path to project root for navigation links (used by sidebar-menu.php)
$_projectUrl = rtrim(str_replace($_docRoot, '', str_replace('\\', '/', realpath(dirname(dirname(__DIR__))))), '/');
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link rel="stylesheet" href="<?= htmlspecialchars($_layoutUrl) ?>/style-menu.css">
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