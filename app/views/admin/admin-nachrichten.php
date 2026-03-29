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
        padding: 16px 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
    }
    
    .nachricht-header h4 {
        margin: 0;
        font-size: 1.05em;
        line-height: 1.4;
    }
    
    .nachricht-header .zeitstempel {
        font-size: 0.85em;
        opacity: 0.85;
    }
    
    .nachricht-body {
        padding: 24px 20px;
        border-bottom: 1px solid #eee;
    }
    
    .nachricht-body p {
        margin: 0;
        line-height: 1.8;
        white-space: pre-wrap;
        word-break: break-word;
        font-size: 0.95em;
    }
    
    .nachricht-antwort {
        padding: 20px 20px;
        background: #f0f4ff;
        border-left: 4px solid var(--primary-color);
    }
    
    .nachricht-antwort .antwort-label {
        font-weight: 700;
        color: var(--primary-color);
        margin-bottom: 12px;
        font-size: 0.9em;
    }
    
    .nachricht-antwort p {
        margin: 0 0 12px 0;
        line-height: 1.8;
        white-space: pre-wrap;
        word-break: break-word;
        font-size: 0.95em;
    }
    
    .nachricht-antwort .antwort-zeit {
        font-size: 0.8em;
        color: #888;
        margin-top: 12px;
    }
    
    .no-messages {
        color: var(--text-secondary);
        font-style: italic;
        padding: 20px 0;
    }
    
    .neue-nachricht-form {
        background: white;
        padding: 30px;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        max-width: 600px;
    }
    
    .form-group {
        display: flex;
        flex-direction: column;
        margin-bottom: 24px;
    }
    
    .form-group label {
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 10px;
        font-size: 0.95em;
    }
    
    .form-group input,
    .form-group textarea {
        padding: 12px 14px;
        border: 2px solid var(--border-color);
        border-radius: 4px;
        font-size: 1em;
        line-height: 1.6;
        transition: var(--transition);
        font-family: inherit;
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
        line-height: 1.6;
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
                            <span>🕐 <?= formatDatum($n['zeitstempel']);  ?></span>
                        </div>
                    </div>
 					<br />
                    <div class="nachricht-body">
                        <p><?= htmlspecialchars($n['nachricht']) ?></p>
                    </div>
					<br /><br/>
                    <div class="nachricht-antwort-bereich">
                        <?php if ($n['antwort'] !== null): ?>
                            <div class="antwort-vorhanden">
                                <div class="antwort-label">💬 Deine Antwort:</div>
                                <p><?= htmlspecialchars($n['antwort']) ?></p>
                                <?php if ($n['antwort_zeitstempel']): ?>
                                    <div class="antwort-zeit">🕐 <?= formatDatum($n['antwort_zeitstempel']);  ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <form method="post" class="reply-form">
                            <input type="hidden" name="nachricht_id" value="<?= (int) $n['id'] ?>">
                            <label for="antwort_<?= (int) $n['id'] ?>">
                                <?= $n['antwort'] !== null ? '✏️ Antwort bearbeiten:' : '💬 Antwort schreiben:' ?>
                            </label><br /><br />
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
                
                            <br /><hr /><br/>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<br>
<br>

<?php require_once '../../layout/footer.php'; ?>
