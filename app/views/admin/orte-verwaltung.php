<?php
$pageTitle = "Orte-Verwaltung";
require_once '../../layout/header.php';
require_once '../../lib/include.php';

if (!isAdmin()) {
    die('❌ Zugriff verweigert! Nur für Administratoren.');
}

$pdo = getPDO();

// Dateiverzeichnis - eine Ebene höher (zu /home/franziska/eclipse-workspace)
$dataDir = dirname(__DIR__, 4) . '/stammbaum-daten';

// Alle .txt Dateien laden und filtern
$allowedFiles = [];
if (is_dir($dataDir)) {
    $files = scandir($dataDir);
    foreach ($files as $file) {
        // Nur .txt Dateien, die mit großem Buchstaben beginnen
        if (pathinfo($file, PATHINFO_EXTENSION) === 'txt' && ctype_upper($file[0])) {
            $allowedFiles[] = $file;
        }
    }
    sort($allowedFiles);
}

// Bereits hinterlegte Traubücher laden (DISTINCT aus ehe-Tabelle)
$usedTraubuecher = [];
try {
    $stmt = $pdo->query("SELECT DISTINCT traubuch FROM ehe WHERE traubuch IS NOT NULL AND traubuch != ''");
    $usedTraubuecher = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    // Tabelle existiert noch nicht - kein Problem, leeres Array reicht
}

// Mapping: Dateiname (ohne .txt und alles nach -) → ist bereits hinterlegt?
$fileIsUsed = [];
foreach ($allowedFiles as $file) {
    // Base name: nur der Teil vor "-" oder "." (z.B. "Auffach-komplett.txt" → "Auffach", "Alpbach.txt" → "Alpbach")
    $baseName = preg_replace('/[-\.].*$/', '', $file);
    $fileIsUsed[$file] = in_array($baseName, $usedTraubuecher, true);
}

$message = '';
$messageType = '';

// Formular verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_ort'])) {
    $ortname = trim($_POST['ortname'] ?? '');
    $file = trim($_POST['file'] ?? '');
    
    // Validierung
    if (!$ortname) {
        $message = "⚠️ Bitte gib einen Ortsnamen ein!";
        $messageType = 'warning';
    } elseif (!$file || !in_array($file, $allowedFiles)) {
        $message = "⚠️ Bitte wähle eine gültige Datei aus!";
        $messageType = 'warning';
    } else {
        // Hier könnte die Speicherlogik implementiert werden
        $message = "✅ Ort '$ortname' mit Datei '$file' gespeichert!";
        $messageType = 'success';
    }
}

$extraHead = '<style>
    .ort-form-wrapper {
        background: white;
        padding: 30px;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        max-width: 600px;
    }
    
    .form-group {
        margin-bottom: 20px;
    }
    
    .form-group label {
        display: block;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 8px;
    }
    
    .form-group input,
    .form-group select {
        width: 100%;
        padding: 12px;
        border: 2px solid var(--border-color);
        border-radius: 4px;
        font-size: 1em;
        font-family: inherit;
        transition: all 0.3s;
        box-sizing: border-box;
    }
    
    .form-group input:focus,
    .form-group select:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }
    
    .form-actions {
        display: flex;
        gap: 12px;
        margin-top: 30px;
    }
    
    .btn-save {
        padding: 12px 24px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-weight: 600;
        transition: all 0.3s;
    }
    
    .btn-save:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
    }
    
    .btn-reset {
        padding: 12px 24px;
        background: white;
        color: var(--text-primary);
        border: 2px solid var(--border-color);
        border-radius: 4px;
        cursor: pointer;
        font-weight: 600;
        transition: all 0.3s;
    }
    
    .btn-reset:hover {
        border-color: var(--primary-color);
        background: rgba(102, 126, 234, 0.05);
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
    
    .file-count {
        color: var(--text-secondary);
        font-size: 0.9em;
        margin-top: 4px;
    }
</style>';
?>

<div class="page-header">
    <h1>📍 Orte-Verwaltung</h1>
    <p class="subtitle">Verwalte Ortsdaten und ordne Dateien zu</p>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?= htmlspecialchars($messageType) ?>">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<div class="search-box">
    <div class="ort-form-wrapper">
        <form method="post">
            <div class="form-group">
                <label for="ortname">Ortsnamen:</label>
                <input 
                    type="text" 
                    id="ortname" 
                    name="ortname" 
                    placeholder="z.B. Alpbach, Thierbach, Kundl..." 
                    required
                >
            </div>
            <br />
            <div class="form-group">
                <label for="file">Datei aus stammbaum-daten:</label>
                <select id="file" name="file" required>
                    <option value="">-- Bitte Datei wählen --</option>
                    <?php foreach ($allowedFiles as $file): ?>
                        <option value="<?= htmlspecialchars($file) ?>" <?= $fileIsUsed[$file] ? 'disabled' : '' ?>>
                            <?= htmlspecialchars($file) ?>
                            <?= $fileIsUsed[$file] ? ' (bereits hinterlegt)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <br />
                <div class="file-count">
                    <?= count($allowedFiles) ?> Datei(en) verfügbar
                </div>
            </div>
            <br />
            <div class="form-actions">
                <button type="submit" name="save_ort" class="btn-small btn-save">
                    💾 Speichern
                </button>
                <button type="reset" class="btn-small btn-reset">
                    🔄 Zurücksetzen
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../../layout/footer.php'; ?>
