<?php
// Footer für alle Seiten
if (!function_exists('getBaseUrl')) {
    require_once dirname(__DIR__) . '/lib/session-helper.php';
}
?>
    </main>

    <script src="<?= htmlspecialchars(getBaseUrl()) ?>/app/layout/script-menu.js"></script>
</body>
</html>
