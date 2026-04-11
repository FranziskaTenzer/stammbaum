<?php
$pageTitle = "Meine Nachrichten";
$extraHead = '<style>
    .nachrichten-list {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    .nachricht-card {
        background: white;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        overflow: hidden;
    }

    .nachricht-header {
        background: var(--primary-color);
        color: white;
        padding: 16px 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }

    .nachricht-header h4 {
        margin: 0;
    }

    .zeitstempel {
        font-size: 0.85em;
        opacity: 0.9;
    }

    .nachricht-body {
        padding: 20px;
        border-bottom: 1px solid #eee;
    }

    .nachricht-body p {
        margin: 0 0 8px 0;
        line-height: 1.6;
        white-space: pre-wrap;
    }

    .nachricht-antwort {
        padding: 20px;
        background: #f0f4ff;
        border-left: 4px solid var(--primary-color);
    }

    .antwort-label {
        font-weight: 700;
        margin-bottom: 8px;
    }

    .antwort-zeit {
        font-size: 0.85em;
        color: #666;
    }

    .nachricht-footer {
        padding: 14px 20px;
        background: #f9f9f9;
        display: flex;
        justify-content: flex-end;
    }

    .neue-nachricht-form {
        max-width: 700px;
    }

    .form-group {
        display: flex;
        flex-direction: column;
        margin-bottom: 18px;
    }

    .form-group label {
        font-weight: 600;
        margin-bottom: 8px;
    }

    .form-group input,
    .form-group textarea {
        padding: 12px;
        border: 2px solid var(--border-color);
        border-radius: 4px;
        font-family: inherit;
        font-size: 1em;
    }

    .form-group textarea {
        min-height: 150px;
        resize: vertical;
    }

    .form-group input:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .alert {
        padding: 16px 18px;
        border-radius: 6px;
        margin: 20px 0;
        border-left: 4px solid;
    }

    .alert-success {
        background: #e8f5e9;
        border-left-color: #4caf50;
        color: #2e7d32;
    }

    .alert-warning {
        background: #fff3cd;
        border-left-color: #ffc107;
        color: #856404;
    }

    .no-messages {
        color: var(--text-secondary);
        font-style: italic;
        padding: 20px 0;
    }
</style>';

require_once '../../layout/header.php';

require_once '../../lib/include.php';

try {
    $pdo = getPDO();
} catch (Exception $e) {
    die("Datenbankverbindung nicht verfügbar: " . htmlspecialchars($e->getMessage()));
}

// Tabelle erstellen falls noch nicht vorhanden
ensureNachrichtenTable($pdo);

$username = $_SESSION['username'];
$message = '';
$messageType = '';

// Mark all admin replies as read when user visits this page (user "actually reads" them)
markAllRepliesAsRead($pdo, $username);

// Neue Nachricht senden
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $betreff = trim($_POST['betreff']);
    $nachricht = trim($_POST['nachricht']);
    
    if ($betreff === '' || $nachricht === '') {
        $message = "Bitte Betreff und Nachricht ausfüllen.";
        $messageType = 'warning';
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO nachrichten (user, typ, betreff, nachricht) VALUES (?, 'Nachricht', ?, ?)"
            );
        $stmt->execute([$username, $betreff, $nachricht]);
        $message = "Nachricht erfolgreich gesendet!";
        $messageType = 'success';
    }
}

// Nachricht löschen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_message'])) {
    $id = (int) $_POST['nachricht_id'];
    
    // Verifizieren, dass die Nachricht dem aktuellen User gehört
    $stmt = $pdo->prepare("SELECT user FROM nachrichten WHERE id = ?");
    $stmt->execute([$id]);
    $msg = $stmt->fetch();
    
    if ($msg && $msg['user'] === $username) {
        $stmt = $pdo->prepare("DELETE FROM nachrichten WHERE id = ?");
        $stmt->execute([$id]);
        $message = "Nachricht erfolgreich gelöscht!";
        $messageType = 'success';
    } else {
        $message = "Fehler: Nachricht konnte nicht gelöscht werden.";
        $messageType = 'warning';
    }
}

// Eigene Nachrichten laden
$stmt = $pdo->prepare(
    "SELECT id, betreff, nachricht, zeitstempel, antwort, antwort_zeitstempel
     FROM nachrichten
     WHERE user = ? AND typ = 'Nachricht'
     ORDER BY zeitstempel DESC"
    );
$stmt->execute([$username]);
$nachrichten = $stmt->fetchAll();

?>

<div class="page-header">
    <h1>✉️ Meine Nachrichten</h1>
    <p class="subtitle">Hier siehst du deine Nachrichten und Antworten vom Admin</p>
</div>

<?php if ($message !== ''): ?>
    <div class="alert alert-<?= $messageType ?>">
        <?= $messageType === 'success' ? '✅' : '⚠️' ?> <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<!-- Ne&uuml; Nachricht schreiben -->
<div class="search-box">
    <h2 style="margin-bottom:20px;">📝 Ne&uuml; Nachricht an den Admin</h2>
    <form method="post" class="ne&uuml;-nachricht-form">
        <div class="form-group">
            <label for="betreff">Betreff:</label>
            <input type="text" id="betreff" name="betreff" maxlength="255" required
                   val&uuml;="<?= isset($_POST['betreff']) ? htmlspecialchars($_POST['betreff']) : '' ?>">
        </div>

        <div class="form-group">
            <label for="nachricht">Nachricht:</label>
            <textarea id="nachricht" name="nachricht" rows="5" required><?= isset($_POST['nachricht']) ? htmlspecialchars($_POST['nachricht']) : '' ?></textarea>
        </div>

        <button class="btn btn-primary" type="submit" name="send_message">
            📤 Nachricht senden
        </button>
    </form>
</div>

<br>
<hr>
<br>

<!-- Eigene Nachrichten anzeigen -->
<div class="search-box">
    <h2 style="margin-bottom:20px;">📬 Meine gesendeten Nachrichten</h2>

    <?php if (empty($nachrichten)): ?>
        <p class="no-messages">Du hast noch keine Nachrichten gesendet.</p>
    <?php else: ?>
        <div class="nachrichten-list">
            <?php foreach ($nachrichten as $n): ?>
                <div class="nachricht-card">
                    <div class="nachricht-header">
                        <h4>📌 <?= htmlspecialchars($n['betreff']) ?></h4>
                        <span class="zeitstempel">🕐 <?= formatDatum($n['zeitstempel']); ?></span>
                    </div>

                    <div class="nachricht-body">
                        <p><?= htmlspecialchars($n['nachricht']) ?></p>
                    </div>

                    <?php if ($n['antwort'] !== null): ?>
                        <div class="nachricht-antwort">
                            <div class="antwort-label">💬 Antwort vom Admin:</div>
                            <p><?= htmlspecialchars($n['antwort']) ?></p>
                            <?php if ($n['antwort_zeitstempel']): ?>
                                <div class="antwort-zeit">🕐 <?= formatDatum($n['antwort_zeitstempel']); ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="nachricht-footer">
                        <form method="post" onsubmit="return confirm('Möchtest du diese Nachricht wirklich löschen?');">
                            <input type="hidden" name="nachricht_id" val&uuml;="<?= $n['id'] ?>">
                            <button type="submit" name="delete_message" class="delete-btn btn btn-primary">✖ Löschen</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<br>
<br>
<br>

<?php require_once dirname(__DIR__, 2) . '/layout/footer.php'; ?>