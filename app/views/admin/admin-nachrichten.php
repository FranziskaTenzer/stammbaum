<?php
$pageTitle = "Admin – Nachrichten";
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'offen';
$typ = isset($_GET['typ']) ? $_GET['typ'] : 'Nachricht';
if (!in_array($typ, ['Nachricht', 'Recherche'], true)) {
    $typ = 'Nachricht';
}

$extraHead = '<style>
.search-box {
    background: white;
    padding: 25px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    margin: 20px 0;
}

.type-filter-buttons {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 22px;
}

.type-filter-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 11px 18px;
    border-radius: 6px;
    text-decoration: none;
    font-weight: 600;
    border: 2px solid var(--border-color);
    color: var(--text-primary);
    background: white;
    transition: all 0.2s ease;
}

.type-filter-btn:hover {
    border-color: var(--primary-color);
    background: rgba(102, 126, 234, 0.05);
}

.type-filter-btn.active {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-color: transparent;
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.35);
}

.overview-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 24px;
}

.overview-table thead {
    background: var(--primary-color);
    color: white;
}

.overview-table th,
.overview-table td {
    padding: 14px 16px;
    text-align: left;
    border-bottom: 1px solid var(--border-color);
    vertical-align: top;
}

.overview-table tbody tr:hover {
    background: #f8faff;
}

.overview-table tbody tr {
    cursor: pointer;
}

.overview-table tbody tr.selected {
    background: #eef3ff;
}

.overview-link {
    color: inherit;
    text-decoration: none;
    display: block;
}

.overview-link strong {
    color: var(--primary-color);
}

.detail-heading {
    margin: 0 0 16px 0;
    color: var(--text-primary);
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
    width: 100%;
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

// Nachrichten laden (gefiltert)
if ($filter !== 'beantwortet') {
    $filter = 'offen';
}

$stmt = null;
if ($filter === 'beantwortet') {
    $stmt = $pdo->prepare(
        "SELECT id, user, typ, betreff, nachricht, person_id, person_name, zeitstempel, antwort, antwort_zeitstempel
         FROM nachrichten
         WHERE antwort IS NOT NULL
           AND typ = ?
         ORDER BY zeitstempel DESC"
    );
    $page_title = $typ === 'Recherche' ? "✅ beantwortete Recherche-Anfragen" : "✅ beantwortete Nachrichten";
} else {
    $stmt = $pdo->prepare(
        "SELECT id, user, typ, betreff, nachricht, person_id, person_name, zeitstempel, antwort, antwort_zeitstempel
         FROM nachrichten
         WHERE antwort IS NULL
           AND typ = ?
         ORDER BY zeitstempel DESC"
    );
    $page_title = $typ === 'Recherche' ? "🔎 offene Recherche-Anfragen" : "📬 offene Nachrichten";
}

$stmt->execute([$typ]);
$nachrichten = $stmt->fetchAll();

$selectedId = isset($_GET['selected_id']) ? (int) $_GET['selected_id'] : 0;
$selectedNachricht = null;

if (!empty($nachrichten)) {
    foreach ($nachrichten as $nachricht) {
        if ((int) $nachricht['id'] === $selectedId) {
            $selectedNachricht = $nachricht;
            break;
        }
    }

    if ($selectedNachricht === null) {
        $selectedNachricht = $nachrichten[0];
        $selectedId = (int) $selectedNachricht['id'];
    }
}

function adminNachrichtenDisplayBetreff(array $nachricht): string
{
    if (($nachricht['typ'] ?? 'Nachricht') !== 'Recherche') {
        return (string) ($nachricht['betreff'] ?? '');
    }

    $teile = [];
    if (!empty($nachricht['person_id'])) {
        $teile[] = 'ID ' . (int) $nachricht['person_id'];
    }
    if (!empty($nachricht['person_name'])) {
        $teile[] = (string) $nachricht['person_name'];
    }

    if (!empty($teile)) {
        return implode(' - ', $teile);
    }

    return (string) ($nachricht['betreff'] ?? 'Recherche-Anfrage');
}

function adminNachrichtenPreview(string $text, int $limit = 90): string
{
    $normalized = trim(preg_replace('/\s+/', ' ', $text));
    if ($normalized === '') {
        return '—';
    }

    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        return mb_strlen($normalized) > $limit ? mb_substr($normalized, 0, $limit - 1) . '…' : $normalized;
    }

    return strlen($normalized) > $limit ? substr($normalized, 0, $limit - 1) . '...' : $normalized;
}
?>

<div class="page-header">
    <h1><?= $page_title ?></h1>
    <p class="subtitle">
        <?php if ($filter === 'beantwortet'): ?>
            Hier werden beantwortete <?= $typ === 'Recherche' ? 'Recherche-Anfragen' : 'Nachrichten' ?> angezeigt.
        <?php else: ?>
            Hier werden offene <?= $typ === 'Recherche' ? 'Recherche-Anfragen' : 'Nachrichten' ?> angezeigt.
        <?php endif; ?>
    </p>
</div>

<?php if ($message !== ''): ?>
    <div class="alert alert-<?= $messageType ?>">
        <?= $messageType === 'success' ? '✅' : '⚠️' ?> <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<div class="search-box">
    <div class="type-filter-buttons">
        <a href="?filter=<?= urlencode($filter) ?>&typ=Nachricht" class="type-filter-btn <?= $typ === 'Nachricht' ? 'active' : '' ?>">✉️ Nachrichten</a>
        <a href="?filter=<?= urlencode($filter) ?>&typ=Recherche" class="type-filter-btn <?= $typ === 'Recherche' ? 'active' : '' ?>">🔎 Recherche-Anfragen</a>
    </div>

    <?php if (empty($nachrichten)): ?>
        <p class="no-messages">
            📭 Es sind keine <?= $typ === 'Recherche' ? 'Recherche-Anfragen' : 'Nachrichten' ?> vorhanden.
        </p>
    <?php else: ?>
        <table class="overview-table">
            <thead>
                <tr>
                    <th>Betreff</th>
                    <th>Nachricht</th>
                    <th>Benutzer</th>
                    <th>Status</th>
                    <th>Zeit</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($nachrichten as $n): ?>
                    <?php $rowUrl = '?filter=' . urlencode($filter) . '&typ=' . urlencode($typ) . '&selected_id=' . (int) $n['id']; ?>
                    <tr
                        class="<?= (int) $n['id'] === $selectedId ? 'selected' : '' ?>"
                        onclick="window.location.href='<?= htmlspecialchars($rowUrl, ENT_QUOTES, 'UTF-8') ?>'"
                    >
                        <td>
                            <a class="overview-link" href="<?= htmlspecialchars($rowUrl) ?>">
                                <strong>
                                    <?= ($n['typ'] ?? 'Nachricht') === 'Recherche' ? '🔎' : '📌' ?>
                                    <?= htmlspecialchars(adminNachrichtenDisplayBetreff($n)) ?>
                                </strong>
                            </a>
                        </td>
                        <td><?= htmlspecialchars(adminNachrichtenPreview((string) $n['nachricht'])) ?></td>
                        <td><?= htmlspecialchars($n['user']) ?></td>
                        <td>
                            <?php if ($n['antwort'] === null): ?>
                                <span class="badge-unanswered">Offen</span>
                            <?php else: ?>
                                <span class="badge-answered">Beantwortet</span>
                            <?php endif; ?>
                        </td>
                        <td><?= formatDatum($n['zeitstempel']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($selectedNachricht !== null): ?>
            <h2 class="detail-heading">Ausgewählte Nachricht</h2>
            <div class="nachricht-card">
                <div class="nachricht-header">
                    <h4>
                        <?= ($selectedNachricht['typ'] ?? 'Nachricht') === 'Recherche' ? '🔎' : '📌' ?>
                        <?= htmlspecialchars(adminNachrichtenDisplayBetreff($selectedNachricht)) ?>
                        <?php if ($selectedNachricht['antwort'] === null): ?>
                            <span class="badge-unanswered">Offen</span>
                        <?php else: ?>
                            <span class="badge-answered">Beantwortet</span>
                        <?php endif; ?>
                    </h4>
                    <div class="meta">
                        <span>👤 <?= htmlspecialchars($selectedNachricht['user']) ?></span>
                        <span>🕐 <?= formatDatum($selectedNachricht['zeitstempel']); ?></span>
                    </div>
                </div>

                <div class="nachricht-body">
                    <?php if (($selectedNachricht['typ'] ?? 'Nachricht') === 'Recherche'): ?>
                        <p><strong>Typ:</strong> Recherche</p>
                        <p><strong>Person-ID:</strong> <?= !empty($selectedNachricht['person_id']) ? (int) $selectedNachricht['person_id'] : '—' ?></p>
                        <p><strong>Person-Name:</strong> <?= !empty($selectedNachricht['person_name']) ? htmlspecialchars($selectedNachricht['person_name']) : '—' ?></p>
                        <br>
                    <?php endif; ?>
                    <p><?= htmlspecialchars($selectedNachricht['nachricht']) ?></p>
                </div>

                <div class="nachricht-antwort-bereich">
                    <?php if ($selectedNachricht['antwort'] !== null): ?>
                        <div class="antwort-vorhanden">
                            <div class="antwort-label">💬 Deine Antwort:</div>
                            <p><?= htmlspecialchars($selectedNachricht['antwort']) ?></p>
                            <?php if ($selectedNachricht['antwort_zeitstempel']): ?>
                                <div class="antwort-zeit">🕐 <?= formatDatum($selectedNachricht['antwort_zeitstempel']); ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <form method="post" class="reply-form">
                        <input type="hidden" name="nachricht_id" val&uuml;="<?= (int) $selectedNachricht['id'] ?>">
                        <label for="antwort_<?= (int) $selectedNachricht['id'] ?>">
                            <?php if ($selectedNachricht['antwort'] !== null): ?>
                                ✏️ Antwort bearbeiten:
                            <?php else: ?>
                                💬 Antwort schreiben:
                            <?php endif; ?>
                        </label>
                        <textarea id="antwort_<?= (int) $selectedNachricht['id'] ?>" rows="5" cols="70" name="antwort" placeholder="Antwort eingeben..."><?= $selectedNachricht['antwort'] !== null ? htmlspecialchars($selectedNachricht['antwort']) : '' ?></textarea>
                        <div>
                            <button class="btn btn-primary" type="submit" name="send_reply">
                                📤 Antwort speichern
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<br>
<br>

<?php require_once '../../layout/footer.php'; ?>