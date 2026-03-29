<?php
$pageTitle = "Meine Nachrichten";
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

// Neue Nachricht senden
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $betreff = trim($_POST['betreff']);
    $nachricht = trim($_POST['nachricht']);

    if ($betreff === '' || $nachricht === '') {
        $message = "Bitte Betreff und Nachricht ausfüllen.";
        $messageType = 'warning';
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO nachrichten (user, betreff, nachricht) VALUES (?, ?, ?)"
        );
        $stmt->execute([$username, $betreff, $nachricht]);
        $message = "Nachricht erfolgreich gesendet!";
        $messageType = 'success';
    }
}

// Eigene Nachrichten laden
$stmt = $pdo->prepare(
    "SELECT id, betreff, nachricht, zeitstempel, antwort, antwort_zeitstempel
     FROM nachrichten
     WHERE user = ?
     ORDER BY zeitstempel DESC"
);
$stmt->execute([$username]);
$nachrichten = $stmt->fetchAll();

$extraHead = '<style>
    .nachrichten-list {
        display: flex;
        flex-direction: column;
        gap: 20px;
        margin-bottom: 40px;
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
        padding: 12px 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
    }

    .nachricht-header h4 {
        margin: 0;
        font-size: 1em;
    }

    .nachricht-header .zeitstempel {
        font-size: 0.85em;
        opacity: 0.85;
    }

    .nachricht-body {
        padding: 16px 20px;
        border-bottom: 1px solid #eee;
    }

    .nachricht-body p {
        margin: 0;
        line-height: 1.6;
        white-space: pre-wrap;
        word-break: break-word;
    }

    .nachricht-antwort {
        padding: 14px 20px;
        background: #f0f4ff;
        border-left: 4px solid var(--primary-color);
    }

    .nachricht-antwort .antwort-label {
        font-weight: 700;
        color: var(--primary-color);
        margin-bottom: 6px;
        font-size: 0.9em;
    }

    .nachricht-antwort p {
        margin: 0;
        line-height: 1.6;
        white-space: pre-wrap;
        word-break: break-word;
    }

    .nachricht-antwort .antwort-zeit {
        font-size: 0.8em;
        color: #888;
        margin-top: 6px;
    }

    .no-messages {
        color: var(--text-secondary);
        font-style: italic;
        padding: 20px 0;
    }

    .neue-nachricht-form {
        background: white;
        padding: 25px;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        max-width: 600px;
    }

    .form-group {
        display: flex;
        flex-direction: column;
        margin-bottom: 16px;
    }

    .form-group label {
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 5px;
    }

    .form-group input,
    .form-group textarea {
        padding: 10px;
        border: 2px solid var(--border-color);
        border-radius: 4px;
        font-size: 1em;
        transition: var(--transition);
        font-family: inherit;
    }

    .form-group input:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .alert {
        padding: 15px;
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
</style>';
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

<!-- Neue Nachricht schreiben -->
<div class="search-box">
    <h2 style="margin-bottom:20px;">📝 Neue Nachricht an den Admin</h2>
    <form method="post" class="neue-nachricht-form">
        <div class="form-group">
            <label for="betreff">Betreff:</label>
            <input type="text" id="betreff" name="betreff" maxlength="255" required
                   value="<?= isset($_POST['betreff']) ? htmlspecialchars($_POST['betreff']) : '' ?>">
        </div><br/>
        <div class="form-group">
            <label for="nachricht">Nachricht:</label>
            <textarea id="nachricht" name="nachricht" rows="5" required><?= isset($_POST['nachricht']) ? htmlspecialchars($_POST['nachricht']) : '' ?></textarea>
        </div><br/>
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
                    </div><br>
                    <?php if ($n['antwort'] !== null): ?>
                        <div class="nachricht-antwort">
                            <div class="antwort-label">💬 Antwort vom Admin:</div>
                            <p><?= htmlspecialchars($n['antwort']) ?></p>
                            <?php if ($n['antwort_zeitstempel']): ?>
                                <div class="antwort-zeit">🕐 <?= formatDatum($n['antwort_zeitstempel']); ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <br/><hr><br/>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<br>
<br>

<?php require_once dirname(__DIR__, 2) . '/layout/footer.php'; ?>
