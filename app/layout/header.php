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

$cssFile = __DIR__ . '/style-menu.css';
$cssVersion = file_exists($cssFile) ? filemtime($cssFile) : time();

?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    <link rel="stylesheet" href="<?= $_layoutUrl; ?>/style-menu.css?v=<?= $cssVersion; ?>">
    <?php if (isset($extraHead)) echo $extraHead; ?>
</head>
<body>
    <!-- ← Hamburger Button für Mobile (mit Close-Button X) -->
    <button class="hamburger-menu" id="hamburgerBtn" aria-label="Menu toggle">
        <span></span>
        <span></span>
        <span></span>
    </button>

    <div class="layout-wrapper">
        <!-- ← Sidebar mit Navigation -->
        <aside class="sidebar" id="sidebar">
            <?php include 'sidebar-menu.php'; ?>
        </aside>
        
        <!-- ← Hauptinhalt -->
        <div class="content-wrapper">
            <main class="main-content">