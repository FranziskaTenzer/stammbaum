<?php
require_once 'session-helper.php';
requireLogin();  // Erzwingt Login
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stammbaum</title>
    <link rel="stylesheet" href="style-menu.css">
</head>
<body>

    <?php include 'menu.php'; ?>

    <main class="main-content">
        <h1>🌳 Willkommen zum Stammbaum</h1>
        <p>Wählen Sie ein Menü-Element, um zu beginnen.</p>
    </main>

    <script src="script-menu.js"></script>
</body>
</html>