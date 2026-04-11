<?php
$pageTitle = "Stammbaum Startseite";
require_once '../../layout/header.php';
require_once '../../lib/include.php';

// Check for unread admin-replies
$unreadCount = 0;
$headerMessage = '✉️ Nachrichten ';
$headerSubtitle = 'Deine Nachrichten und Antworten vom Admin';

if (isLoggedIn()) {
    try {
        $pdo = getPDO();
        $unreadCount = getUnreadAdminRepliesCount($pdo, $_SESSION['username']);
        if ($unreadCount > 0) {
            $headerMessage = '📬 Du hast neue Nachrichten!';
            $headerSubtitle = $unreadCount === 1 ? 'Der Admin hat 1 Nachricht beantwortet.' : 'Der Admin hat ' . $unreadCount . ' Nachrichten beantwortet.';
        }
    } catch (Exception $e) {
        // Fehler ignorieren
    }
}
?>

<div class="page-header">
    <h1>🌳 Willkommen zum Stammbaum</h1>
    <p class="subtitle">Wähle ein Menü-Element, um zu beginnen</p>
</div>

<div class="content-grid" style="grid-template-columns: repeat(2, 1fr);">
    <div class="content-card">
        <h3>👤 Personensuche</h3>
        <p>Suche nach Personen im Stammbaum nach Vor- und Nachname</p>
        <a href="stammbaum-search.php" class="btn btn-primary">Zur Suche</a>
    </div>

    <div class="content-card">
        <h3>📚 Traubuch-Liste</h3>
        <p>Verfügbare Traubücher (und deren Jahresbereiche) anzeigen</p>
        <a href="traubuch-list.php" class="btn btn-primary">Traubücher ansehen</a>
    </div>
    
    <div class="content-card">
        <h3><?= htmlspecialchars($headerMessage); ?></h3>
        <p><?= htmlspecialchars($headerSubtitle); ?></p>
        <a href="nachrichten.php" class="btn btn-primary">Zu deinen Nachrichten</a>
    </div>

    <div class="content-card">
        <h3>🔎 Recherche-Anfrage</h3>
        <p>Stelle eine Recherche-Anfrage an den Admin (mit Person-ID oder Name)</p>
        <a href="recherche-anfrage.php" class="btn btn-primary">Zur Recherche-Anfrage</a>
    </div>

    <div class="content-card">
        <h3>👤 Profil</h3>
        <p>Verwalte dein Profil und ändere dein Passwort</p>
        <a href="profil.php" class="btn btn-primary">Zum Profil</a>
    </div>
</div>

<?php require_once '../../layout/footer.php'; ?>