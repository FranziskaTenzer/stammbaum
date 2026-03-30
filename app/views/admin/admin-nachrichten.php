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

// Filter aus URL-Parameter
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'offen';

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

// Nachrichten laden (gefiltert)
if ($filter === 'beantwortet') {
    $nachrichten = $pdo->query(
        "SELECT id, user, betreff, nachricht, zeitstempel, antwort, antwort_zeitstempel
         FROM nachrichten
         WHERE antwort IS NOT NULL
         ORDER BY zeitstempel DESC"
        )->fetchAll();
        $page_title = "✅ beantwortete Nachrichten";
} else {
    $nachrichten = $pdo->query(
        "SELECT id, user, betreff, nachricht, zeitstempel, antwort, antwort_zeitstempel
         FROM nachrichten
         WHERE antwort IS NULL
         ORDER BY zeitstempel DESC"
        )->fetchAll();
        $page_title = "📬 offene Nachrichten";
}

$extraHead = '<style>
.search-box {
    background: white;
    padding: 25px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    margin: 20px 0;
}
    
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
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    max-width: 700px;
}
    
.nachricht-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
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
    
.meta {
    display: flex;
    gap: 20px;
    font-size: 0.9em;
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
    
.nachricht-antwort-bereich {
    padding: 20px;
}
    
.antwort-vorhanden {
    padding: 16px;
    background: #e8f5e9;
    border-left: 4px solid #4caf50;
    margin-bottom: 20px;
    border-radius: 4px;
}
    
.antwort-vorhanden .antwort-label {
    font-weight: 700;
    color: #2e7d32;
    margin-bottom: 12px;
    font-size: 0.9em;
}
    
.antwort-vorhanden p {
    margin: 0 0 10px 0;
    line-height: 1.8;
    white-space: pre-wrap;
    word-break: break-word;
}
    
.antwort-vorhanden .antwort-zeit {
    font-size: 0.8em;
    color: #666;
    margin-top: 10px;
}
    
.no-messages {
    color: var(--text-secondary);
    font-style: italic;
    padding: 40px 20px;
    text-align: center;
    background: #f9f9f9;
    border-radius: 8px;
}
    
.reply-form {
    display: flex;
    flex-direction: column;
    width: 100%;
}
    
.reply-form label {
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 10px;
    font-size: 0.95em;
}
    
.reply-form textarea {
    width: 100%;
    min-height: 600px;
    padding: 14px;
    border: 2px solid var(--border-color);
    border-radius: 4px;
    font-size: 1em;
    line-height: 1.6;
    font-family: inherit;
    transition: var(--transition);
    box-sizing: border-box;
    resize: vertical;
}
    
.reply-form textarea:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}
    
.reply-form > div {
    margin-top: 12px;
}
    
.badge-answered {
    background: #4caf50;
    color: white;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 0.75em;
    font-weight: 600;
    white-space: nowrap;
}
    
.badge-unanswered {
    background: #ff9800;
    color: white;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 0.75em;
    font-weight: 600;
    white-space: nowrap;
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
    <h1><?= $page_title ?></h1>
    <p class="subtitle">Alle <?= $page_title ?> und Antwortfunktion</p>
</div>

<?php if ($message !== ''): ?>
    <div class="alert alert-<?= $messageType ?>">
        <?= $messageType === 'success' ? '✅' : '⚠️' ?> <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<div class="search-box">
    <?php if (empty($nachrichten)): ?>
        <p class="no-messages">📭 Es sind keine Nachrichten vorhanden.</p>
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
                            <span>🕐 <?= formatDatum($n['zeitstempel']); ?></span>
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
                                <div class="antwort-label">💬 Deine Antwort:</div><br>
                                <p><?= htmlspecialchars($n['antwort']) ?></p><br>
                                <?php if ($n['antwort_zeitstempel']): ?>
                                    <div class="antwort-zeit">🕐 <?= formatDatum($n['antwort_zeitstempel']); ?></div>
                                <?php endif; ?>
                            </div>
                            <br>
                        <?php endif; ?>

                        <form method="post" class="reply-form">
                            <input type="hidden" name="nachricht_id" value="<?= (int) $n['id'] ?>">
                            <label for="antwort_<?= (int) $n['id'] ?>">
                                <?php if ($n['antwort'] !== null): ?>
                                    ✏️ Antwort bearbeiten:
                                <?php else: ?>
                                    💬 Antwort schreiben:
                                <?php endif; ?>
                            </label><br /><br />
                            <textarea id="antwort_<?= (int) $n['id'] ?>" rows="5" cols="70" name="antwort" style="padding: 10px 10px;" placeholder="Antwort eingeben..."><?= $n['antwort'] !== null ? htmlspecialchars($n['antwort']) : '' ?></textarea>
                            <div><br>
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