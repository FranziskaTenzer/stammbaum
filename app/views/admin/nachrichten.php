<?php
$pageTitle = "Admin – Nachrichten";
require_once '../../layout/header.php';

if (!isAdmin()) {
    die('❌ Zugriff verweigert! Nur für Administratoren.');
}

require_once '../../lib/include.php';

try {
    $pdo = getPDO();
} catch (Exception $e) {
    die("Datenbankverbindung nicht verfügbar: " . htmlspecialchars($e->getMessage()));
}

// Tabelle erstellen falls noch nicht vorhanden
ensureNachrichtenTable($pdo);

$message = '';
$messageType = '';

// Antwort speichern
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_reply'])) {
    $id = (int) $_POST['nachricht_id'];
    $antwort = trim($_POST['antwort']);

    if ($antwort === '') {
        $message = "Bitte eine Antwort eingeben.";
        $messageType = 'warning';
    } else {
        $stmt = $pdo->prepare(
            "UPDATE nachrichten SET antwort = ?, antwort_zeitstempel = NOW() WHERE id = ?"
        );
        $stmt->execute([$antwort, $id]);
        $message = "Antwort erfolgreich gespeichert!";
        $messageType = 'success';
    }
}

// Alle Nachrichten laden
$nachrichten = $pdo->query(
    "SELECT id, user, betreff, nachricht, zeitstempel, antwort, antwort_zeitstempel
     FROM nachrichten
     ORDER BY zeitstempel DESC"
)->fetchAll();

$extraHead = '<style>
    .nachrichten-list {
        display: flex;
        flex-direction: column;
        gap: 24px;
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

    .nachricht-header .meta {
        font-size: 0.85em;
        opacity: 0.85;
        display: flex;
        gap: 16px;
        flex-wrap: wrap;
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

    .nachricht-antwort-bereich {
        padding: 16px 20px;
        background: #f8f9ff;
    }

    .antwort-vorhanden {
        background: #f0f4ff;
        border-left: 4px solid var(--primary-color);
        padding: 12px 16px;
        border-radius: 4px;
        margin-bottom: 14px;
    }

    .antwort-vorhanden .antwort-label {
        font-weight: 700;
        color: var(--primary-color);
        margin-bottom: 6px;
        font-size: 0.9em;
    }

    .antwort-vorhanden p {
        margin: 0;
        line-height: 1.6;
        white-space: pre-wrap;
        word-break: break-word;
    }

    .antwort-vorhanden .antwort-zeit {
        font-size: 0.8em;
        color: #888;
        margin-top: 6px;
    }

    .reply-form {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .reply-form label {
        font-weight: 600;
        color: var(--text-primary);
        font-size: 0.95em;
    }

    .reply-form textarea {
        padding: 10px;
        border: 2px solid var(--border-color);
        border-radius: 4px;
        font-size: 1em;
        font-family: inherit;
        transition: var(--transition);
        resize: vertical;
    }

    .reply-form textarea:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .no-messages {
        color: var(--text-secondary);
        font-style: italic;
        padding: 20px 0;
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

    .badge-unanswered {
        background: #ff7043;
        color: white;
        font-size: 0.75em;
        padding: 2px 8px;
        border-radius: 12px;
        font-weight: 600;
    }

    .badge-answered {
        background: #4caf50;
        color: white;
        font-size: 0.75em;
        padding: 2px 8px;
        border-radius: 12px;
        font-weight: 600;
    }
</style>';
?>

<div class="page-header">
    <h1>✉️ Nachrichten – Admin</h1>
    <p class="subtitle">Alle Nachrichten der Benutzer und Antwortfunktion</p>
</div>

<?php if ($message !== ''): ?>
    <div class="alert alert-<?= $messageType ?>">
        <?= $messageType === 'success' ? '✅' : '⚠️' ?> <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<div class="search-box">
    <?php if (empty($nachrichten)): ?>
        <p class="no-messages">Es sind noch keine Nachrichten vorhanden.</p>
    <?php else: ?>
        <div class="nachrichten-list">
            <?php foreach ($nachrichten as $n): ?>
                <div class="nachricht-card">
                    <div class="nachricht-header">
                        <h4>
                            📌 <?= htmlspecialchars($n['betreff']) ?>
                            <?php if ($n['antwort'] === null): ?>
                                <span class="badge-unanswered">Offen</span>
                            <?php else: ?>
                                <span class="badge-answered">Beantwortet</span>
                            <?php endif; ?>
                        </h4>
                        <div class="meta">
                            <span>👤 <?= htmlspecialchars($n['user']) ?></span>
                            <span>🕐 <?= htmlspecialchars($n['zeitstempel']) ?></span>
                        </div>
                    </div>

                    <div class="nachricht-body">
                        <p><?= htmlspecialchars($n['nachricht']) ?></p>
                    </div>

                    <div class="nachricht-antwort-bereich">
                        <?php if ($n['antwort'] !== null): ?>
                            <div class="antwort-vorhanden">
                                <div class="antwort-label">💬 Deine Antwort:</div>
                                <p><?= htmlspecialchars($n['antwort']) ?></p>
                                <?php if ($n['antwort_zeitstempel']): ?>
                                    <div class="antwort-zeit">🕐 <?= htmlspecialchars($n['antwort_zeitstempel']) ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <form method="post" class="reply-form">
                            <input type="hidden" name="nachricht_id" value="<?= (int) $n['id'] ?>">
                            <label for="antwort_<?= (int) $n['id'] ?>">
                                <?= $n['antwort'] !== null ? '✏️ Antwort bearbeiten:' : '💬 Antwort schreiben:' ?>
                            </label>
                            <textarea id="antwort_<?= (int) $n['id'] ?>" name="antwort" rows="3"
                                      placeholder="Antwort eingeben..."><?= $n['antwort'] !== null ? htmlspecialchars($n['antwort']) : '' ?></textarea>
                            <div>
                                <button class="btn btn-primary" type="submit" name="send_reply">
                                    📤 Antwort speichern
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<br>
<br>

<?php require_once '../../layout/footer.php'; ?>
