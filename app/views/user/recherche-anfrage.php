<?php
$pageTitle = "Recherche-Anfrage";
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
    die("Datenbankverbindung nicht verfuegbar: " . htmlspecialchars($e->getMessage()));
}

ensureNachrichtenTable($pdo);

$username = $_SESSION['username'];
$message = '';
$messageType = 'warning';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_recherche'])) {
    $personIdRaw = trim($_POST['person_id'] ?? '');
    $personName = trim($_POST['person_name'] ?? '');
    $nachricht = trim($_POST['nachricht'] ?? '');

    $personId = null;
    if ($personIdRaw !== '') {
        if (ctype_digit($personIdRaw) && (int) $personIdRaw > 0) {
            $personId = (int) $personIdRaw;
        } else {
            $message = "Bitte eine gueltige positive Person-ID eingeben.";
        }
    }

    if ($message === '' && $personId === null && $personName === '') {
        $message = "Bitte mindestens Person-ID oder Person-Name angeben.";
    }

    if ($message === '' && $nachricht === '') {
        $message = "Bitte die Anfrage-Beschreibung ausfuellen.";
    }

    if ($message === '') {
        $betreffTeile = [];
        if ($personId !== null) {
            $betreffTeile[] = 'ID ' . $personId;
        }
        if ($personName !== '') {
            $betreffTeile[] = $personName;
        }
        $betreff = implode(' - ', $betreffTeile);
        $stmt = $pdo->prepare(
            "INSERT INTO nachrichten (user, typ, betreff, nachricht, person_id, person_name)
             VALUES (?, 'Recherche', ?, ?, ?, ?)"
        );
        $stmt->execute([
            $username,
            $betreff,
            $nachricht,
            $personId,
            $personName !== '' ? $personName : null,
        ]);

        $message = "Recherche-Anfrage erfolgreich gesendet!";
        $messageType = 'success';
        $_POST = [];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_recherche'])) {
    $id = (int) ($_POST['nachricht_id'] ?? 0);

    $stmt = $pdo->prepare("SELECT user FROM nachrichten WHERE id = ? AND typ = 'Recherche'");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row && $row['user'] === $username) {
        $stmt = $pdo->prepare("DELETE FROM nachrichten WHERE id = ?");
        $stmt->execute([$id]);
        $message = "Recherche-Anfrage geloescht.";
        $messageType = 'success';
    } else {
        $message = "Anfrage konnte nicht geloescht werden.";
        $messageType = 'warning';
    }
}

$stmt = $pdo->prepare(
    "SELECT id, betreff, nachricht, person_id, person_name, zeitstempel, antwort, antwort_zeitstempel
     FROM nachrichten
     WHERE user = ? AND typ = 'Recherche'
     ORDER BY zeitstempel DESC"
);
$stmt->execute([$username]);
$anfragen = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="page-header">
    <h1>🔎 Recherche-Anfrage</h1>
    <p class="subtitle">Sende eine Recherche-Anfrage an den Admin</p>
</div>

<?php if ($message !== ''): ?>
    <div class="alert alert-<?= $messageType ?>">
        <?= $messageType === 'success' ? '✅' : '⚠️' ?> <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<div class="search-box">
    <h2 style="margin-bottom:20px;">📝 Neue Recherche-Anfrage</h2>
    <form method="post" class="neue-nachricht-form" autocomplete="off">
        <div class="form-group">
            <label for="person_id">Person-ID (Pflicht: ID oder Name):</label>
            <input type="number" min="1" id="person_id" name="person_id" value="<?= htmlspecialchars($_POST['person_id'] ?? '') ?>">
        </div>
        
        <div class="form-group">
            <label for="person_name">Person-Name (Pflicht: ID oder Name):</label>
            <input type="text" id="person_name" name="person_name" maxlength="255" value="<?= htmlspecialchars($_POST['person_name'] ?? '') ?>">
        </div>
        
        <div class="form-group">
            <label for="nachricht">Recherche-Anliegen:</label>
            <textarea id="nachricht" name="nachricht" rows="6" required><?= htmlspecialchars($_POST['nachricht'] ?? '') ?></textarea>
        </div>
        
        <p style="margin-top:-8px; margin-bottom:18px; color:#666;">Mindestens Person-ID oder Person-Name ist Pflicht.</p>

        <button class="btn btn-primary" type="submit" name="send_recherche">📤 Recherche senden</button>
    </form>
</div>

<br>
<hr>
<br>

<div class="search-box">
    <h2 style="margin-bottom:20px;">📬 Meine Recherche-Anfragen</h2>
    <?php if (empty($anfragen)): ?>
        <p class="no-messages">Du hast noch keine Recherche-Anfragen gesendet.</p>
    <?php else: ?>
        <div class="nachrichten-list">
            <?php foreach ($anfragen as $n): ?>
                <div class="nachricht-card">
                    <div class="nachricht-header">
                        <h4>🔎 <?= htmlspecialchars($n['betreff']) ?></h4>
                        <span class="zeitstempel">🕐 <?= formatDatum($n['zeitstempel']); ?></span>
                    </div>
                    <div class="nachricht-body">
                        <p><strong>Person-ID:</strong> <?= $n['person_id'] ? (int) $n['person_id'] : '—' ?></p>
                        <p><strong>Person-Name:</strong> <?= $n['person_name'] ? htmlspecialchars($n['person_name']) : '—' ?></p>
                        <br>
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
                        <form method="post" onsubmit="return confirm('Anfrage wirklich loeschen?');">
                            <input type="hidden" name="nachricht_id" value="<?= (int) $n['id'] ?>">
                            <button type="submit" name="delete_recherche" class="delete-btn btn btn-primary">✖ Loeschen</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<br>
<br>

<?php require_once dirname(__DIR__, 2) . '/layout/footer.php'; ?>
