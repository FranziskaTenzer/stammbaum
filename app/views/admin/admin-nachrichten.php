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
    
    // Nachrichten in Kategorien aufteilen
    $nachrichten_offen = array_filter($nachrichten, function($n) { return $n['antwort'] === null; });
    $nachrichten_beantwortet = array_filter($nachrichten, function($n) { return $n['antwort'] !== null; });
    
    // Aktiven Tab bestimmen
    $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'offen';
    
    $extraHead = '<style>
    .tabs-container {
        display: flex;
        gap: 5px;
        margin-bottom: 0px;
    }
        
    .tab-button {
        padding: 14px 28px;
        background: linear-gradient(135deg, #f5f5f5 0%, #e8e8e8 100%);
        border: none;
        cursor: pointer;
        font-size: 1em;
        font-weight: 600;
        color: var(--text-secondary);
        position: relative;
        transition: all 0.3s ease;
        border-radius: 6px 6px 0 0;
    }
        
    .tab-button:hover {
        background: linear-gradient(135deg, #e0e0e0 0%, #d0d0d0 100%);
        color: var(--primary-color);
    }
        
    .tab-button.active {
        background: linear-gradient(135deg, var(--primary-color) 0%, #667eea 100%);
        color: white;
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    }
        
    .tabs-underline {
        height: 2px;
        background: var(--border-color);
        margin-bottom: 20px;
    }
        
    .tab-content {
        display: none;
    }
        
    .tab-content.active {
        display: block;
        animation: fadeIn 0.3s ease;
    }
        
    @keyframes fadeIn {
        from {
            opacity: 0;
        }
        to {
            opacity: 1;
        }
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
        display: flex;
        align-items: center;
        gap: 10px;
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
        
    .badge-unanswered {
        background: #ff9800;
        color: white;
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 0.75em;
        font-weight: 600;
        white-space: nowrap;
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
        
    .badge-count {
        display: inline-block;
        background: rgba(255, 255, 255, 0.3);
        color: white;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 0.85em;
        font-weight: 700;
        margin-left: 8px;
    }
        
    .tab-button.active .badge-count {
        background: rgba(255, 255, 255, 0.4);
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
</style>
        
<script>
    function switchTab(tab) {
        console.log("Switching to tab: " + tab);
        
        // Alle Tab-Inhalte verstecken
        const allContents = document.querySelectorAll(".tab-content");
        allContents.forEach(function(el) {
            el.classList.remove("active");
        });
        
        // Alle Tab-Buttons deaktivieren
        const allButtons = document.querySelectorAll(".tab-button");
        allButtons.forEach(function(el) {
            el.classList.remove("active");
        });
        
        // Aktiven Tab anzeigen
        const activeContent = document.getElementById("tab-" + tab);
        if (activeContent) {
            activeContent.classList.add("active");
            console.log("Content tab-" + tab + " now active");
        }
        
        // Aktiven Tab-Button markieren
        const allTabButtons = document.querySelectorAll(".tab-button");
        allTabButtons.forEach(function(btn) {
            if (btn.getAttribute("data-tab") === tab) {
                btn.classList.add("active");
                console.log("Button with data-tab=" + tab + " now active");
            }
        });
        
        // URL aktualisieren
        window.history.pushState(null, null, "?tab=" + tab);
        
        return false;
    }
        
    // Tab beim Laden setzen
    window.addEventListener("load", function() {
        const urlParams = new URLSearchParams(window.location.search);
        const tab = urlParams.get("tab") || "offen";
        console.log("Loading page with tab: " + tab);
        switchTab(tab);
    });
</script>';
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
    <!-- Tabs Navigation -->
    <div class="tabs-container">
        <button type="button" class="tab-button" data-tab="offen" onclick="return switchTab('offen')">
            📬 Offene Nachrichten
            <span class="badge-count"><?= count($nachrichten_offen) ?></span>
        </button>
        <button type="button" class="tab-button" data-tab="beantwortet" onclick="return switchTab('beantwortet')">
            ✅ Beantwortete Nachrichten
            <span class="badge-count"><?= count($nachrichten_beantwortet) ?></span>
        </button>
    </div>
    <div class="tabs-underline"></div>

    <!-- Tab: Offene Nachrichten -->
    <div id="tab-offen" class="tab-content">
        <?php if (empty($nachrichten_offen)): ?>
            <p class="no-messages">📭 Es sind keine offenen Nachrichten vorhanden.</p>
        <?php else: ?>
            <div class="nachrichten-list">
                <?php foreach ($nachrichten_offen as $n): ?>
                    <div class="nachricht-card">
                        <div class="nachricht-header">
                            <h4>
                                📌 <?= htmlspecialchars($n['betreff']) ?>
                                <span class="badge-unanswered">Offen</span>
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
                            <form method="post" class="reply-form">
                                <input type="hidden" name="nachricht_id" value="<?= (int) $n['id'] ?>">
                                <label for="antwort_<?= (int) $n['id'] ?>">
                                    💬 Antwort schreiben:
                                </label><br /><br />
                                <textarea id="antwort_<?= (int) $n['id'] ?>" name="antwort" rows="10" cols="100"
                                          placeholder="Antwort eingeben..."></textarea>
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

    <!-- Tab: Beantwortete Nachrichten -->
    <div id="tab-beantwortet" class="tab-content">
        <?php if (empty($nachrichten_beantwortet)): ?>
            <p class="no-messages">📭 Es sind keine beantworteten Nachrichten vorhanden.</p>
        <?php else: ?>
            <div class="nachrichten-list">
                <?php foreach ($nachrichten_beantwortet as $n): ?>
                    <div class="nachricht-card">
                        <div class="nachricht-header">
                            <h4>
                                📌 <?= htmlspecialchars($n['betreff']) ?>
                                <span class="badge-answered">Beantwortet</span>
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
                            <div class="antwort-vorhanden">
                                <div class="antwort-label">💬 Deine Antwort:</div><br>
                                <p><?= htmlspecialchars($n['antwort']) ?></p><br>
                                <?php if ($n['antwort_zeitstempel']): ?>
                                    <div class="antwort-zeit">🕐 <?= formatDatum($n['antwort_zeitstempel']); ?></div>
                                <?php endif; ?>
                            </div>
                            <br>
                            <form method="post" class="reply-form">
                                <input type="hidden" name="nachricht_id" value="<?= (int) $n['id'] ?>">
                                <label for="antwort_<?= (int) $n['id'] ?>">
                                    ✏️ Antwort bearbeiten:
                                </label><br /><br />
                                <textarea id="antwort_<?= (int) $n['id'] ?>" name="antwort" rows="10" cols="100"
                                          placeholder="Antwort eingeben..."><?= htmlspecialchars($n['antwort']) ?></textarea>
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
</div>

<br>
<br>

<?php require_once '../../layout/footer.php'; ?>