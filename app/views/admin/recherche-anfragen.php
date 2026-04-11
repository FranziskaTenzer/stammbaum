<?php
$pageTitle = "Admin – Recherche-Anfragen";
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
        gap: 12px;
        flex-wrap: wrap;
    }

    .nachricht-header h4 {
        margin: 0;
    }

    .meta {
        display: flex;
        gap: 18px;
        font-size: 0.9em;
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

    .nachricht-antwort-bereich {
        padding: 20px;
    }

    .reply-form {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .reply-form textarea {
        min-height: 180px;
        padding: 12px;
        border: 2px solid var(--border-color);
        border-radius: 4px;
        font-family: inherit;
        font-size: 1em;
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
        text-align: center;
        padding: 40px 20px;
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
</style>';

require_once '../../layout/header.php';
require_once '../../lib/include.php';

if (!isAdmin()) {
    die('❌ Zugriff verweigert! Nur f&uuml;r Administratoren.');
}

try {
    $pdo = getPDO();
} catch (Exception $e) {
    die("Datenbankverbindung nicht verf&uuml;gbar: " . htmlspecialchars($e->getMessage()));
}

ensureNachrichtenTable($pdo);

$message = '';
$messageType = 'warning';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_reply'])) {
    $id = (int) ($_POST['nachricht_id'] ?? 0);
    $antwort = trim($_POST['antwort'] ?? '');

    if ($antwort === '') {
        $message = 'Bitte eine Antwort eingeben.';
    } else {
        $stmt = $pdo->prepare(
            "UPDATE nachrichten
             SET antwort = ?, antwort_zeitstempel = NOW()
             WHERE id = ? AND typ = 'Recherche'"
        );
        $stmt->execute([$antwort, $id]);
        $message = 'Antwort gespeichert. Die Anfrage ist jetzt erledigt und erscheint unter beantwortete Nachrichten.';
        $messageType = 'success';
    }
}

$stmt = $pdo->query(
    "SELECT id, user, betreff, nachricht, person_id, person_name, zeitstempel
     FROM nachrichten
     WHERE typ = 'Recherche' AND antwort IS NULL
     ORDER BY zeitstempel DESC"
);
$anfragen = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="page-header">
    <h1>🔎 Ne&uuml; Recherche-Anfragen</h1>
    <p class="subtitle">Offene Recherche-Anfragen der Benutzer</p>
</div>

<?php if ($message !== ''): ?>
    <div class="alert alert-<?= $messageType ?>">
        <?= $messageType === 'success' ? '✅' : '⚠️' ?> <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<div class="search-box">
    <?php if (empty($anfragen)): ?>
        <p class="no-messages">Akt&uuml;ll gibt es keine offenen Recherche-Anfragen.</p>
    <?php else: ?>
        <div class="nachrichten-list">
            <?php foreach ($anfragen as $n): ?>
                <div class="nachricht-card" style="max-width: 900px; margin-bottom: 20px;">
                    <div class="nachricht-header">
                        <h4>🔎 <?= htmlspecialchars($n['betreff']) ?></h4>
                        <div class="meta">
                            <span>👤 <?= htmlspecialchars($n['user']) ?></span>
                            <span>🕐 <?= formatDatum($n['zeitstempel']); ?></span>
                        </div>
                    </div>
                    <div class="nachricht-body">
                        <p><strong>Person-ID:</strong> <?= $n['person_id'] ? (int) $n['person_id'] : '—' ?></p>
                        <p><strong>Person-Name:</strong> <?= $n['person_name'] ? htmlspecialchars($n['person_name']) : '—' ?></p>
                        <br>
                        <p><?= htmlspecialchars($n['nachricht']) ?></p>
                    </div>
                    <div class="nachricht-antwort-bereich">
                        <form method="post" class="reply-form">
                            <input type="hidden" name="nachricht_id" val&uuml;="<?= (int) $n['id'] ?>">
                            <label for="antwort_<?= (int) $n['id'] ?>">💬 Antwort schreiben:</label>
                            <textarea id="antwort_<?= (int) $n['id'] ?>" rows="6" name="antwort" placeholder="Antwort eingeben..."></textarea>
                            <div style="margin-top: 10px;">
                                <button class="btn btn-primary" type="submit" name="send_reply">📤 Antwort speichern</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../../layout/footer.php'; ?>
