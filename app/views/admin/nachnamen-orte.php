<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

$pageTitle = "Nachnamen nach Ort (Tirol-Archiv)";
require_once '../../layout/header.php';
require_once '../../lib/include.php';
require_once '../../lib/tirol-archiv-helper.php';

if (!isAdmin()) {
    die('❌ Zugriff verweigert! Nur für Administratoren.');
}

function tirolArchivPrefixesForOrte() {
    return [
        'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm',
        'n', 'o', 'pq', 'r', 's', 'sch', 'sp', 'st', 't', 'u', 'v', 'w', 'xyz'
    ];
}

function normalizeOrtKey($ort) {
    $clean = trim((string)$ort);
    $clean = preg_replace('/\s+/u', ' ', $clean);
    return mb_strtolower($clean, 'UTF-8');
}

function displayOrtName($ort) {
    $clean = trim((string)$ort);
    return preg_replace('/\s+/u', ' ', $clean);
}

function isValidOrtName($ort) {
    $clean = displayOrtName($ort);
    $clean = trim($clean, " \t\n\r\0\x0B,;:");

    if ($clean === '') {
        return false;
    }

    // Verhindert Artefakte wie einzelne Buchstaben (z.B. "e") aus Parser/OCR-Rauschen.
    if (mb_strlen($clean, 'UTF-8') < 2) {
        return false;
    }

    // Ein Ort sollte mindestens einen Buchstaben enthalten.
    if (!preg_match('/\p{L}/u', $clean)) {
        return false;
    }

    return true;
}

function surnameInitial($name) {
    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
    if ($ascii === false) {
        $ascii = $name;
    }

    $ascii = strtoupper($ascii);
    $ascii = preg_replace('/[^A-Z]/', '', $ascii);

    if (!$ascii) {
        return '#';
    }
    return substr($ascii, 0, 1);
}

function getAllSurnamesGroupedByPlace($forceRefresh = false) {
    $byPlace = [];

    foreach (tirolArchivPrefixesForOrte() as $prefix) {
        if ($forceRefresh) {
            $cacheFile = getTirolArchivCacheFile($prefix);
            if ($cacheFile && file_exists($cacheFile)) {
                @unlink($cacheFile);
            }
        }

        $namesWithPlaces = getTirolArchivNamesWithPlaces($prefix);
        foreach ($namesWithPlaces as $surname => $places) {
            foreach ((array)$places as $place) {
                $display = displayOrtName($place);
                if (!isValidOrtName($display)) {
                    continue;
                }

                $key = normalizeOrtKey($display);
                if (!isset($byPlace[$key])) {
                    $byPlace[$key] = [
                        'ort' => $display,
                        'names' => [],
                    ];
                }

                $byPlace[$key]['names'][$surname] = true;
            }
        }
    }

    uasort($byPlace, function ($a, $b) {
        return strcasecmp($a['ort'], $b['ort']);
    });

    return $byPlace;
}

$forceRefresh = isset($_GET['refresh']) && $_GET['refresh'] === '1';
$selectedOrtKey = isset($_GET['ort']) ? normalizeOrtKey($_GET['ort']) : '';

$placesData = getAllSurnamesGroupedByPlace($forceRefresh);
$selectedPlace = $selectedOrtKey !== '' && isset($placesData[$selectedOrtKey])
    ? $placesData[$selectedOrtKey]
    : null;

$alphabeticGroups = [];
$selectedCount = 0;

if ($selectedPlace) {
    $names = array_keys($selectedPlace['names']);
    usort($names, 'strcasecmp');
    $selectedCount = count($names);

    foreach ($names as $name) {
        $letter = surnameInitial($name);
        if (!isset($alphabeticGroups[$letter])) {
            $alphabeticGroups[$letter] = [];
        }
        $alphabeticGroups[$letter][] = $name;
    }

    ksort($alphabeticGroups);
}
?>

<style>
    .container {
        max-width: 1200px;
        margin: 0 auto;
        background: #fff;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    }
    .top-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin-bottom: 16px;
    }
    .btn-small {
        display: inline-block;
        padding: 8px 12px;
        border-radius: 6px;
        text-decoration: none;
        font-weight: 600;
        border: 1px solid #ddd;
        background: #f5f5f5;
        color: #333;
    }
    .btn-small:hover {
        background: #ebebeb;
    }
    .summary {
        background: #f8f9ff;
        border-left: 4px solid #667eea;
        padding: 12px;
        margin: 14px 0;
        border-radius: 4px;
    }
    .filter-box {
        margin: 16px 0;
        padding: 14px;
        border: 1px solid #ddd;
        border-radius: 8px;
        background: #fafafa;
    }
    .filter-box label {
        font-weight: 700;
        display: block;
        margin-bottom: 8px;
    }
    .filter-box select {
        width: 100%;
        max-width: 500px;
        padding: 10px;
        border: 1px solid #ccc;
        border-radius: 6px;
    }
    .alphabet-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 12px;
        margin-top: 16px;
    }
    .letter-card {
        border: 1px solid #e4e4e4;
        border-radius: 8px;
        padding: 12px;
        background: #fff;
    }
    .letter-card h3 {
        margin: 0 0 10px 0;
        color: #3f51b5;
        border-bottom: 1px solid #efefef;
        padding-bottom: 6px;
    }
    .name-list {
        margin: 0;
        padding-left: 18px;
        max-height: 320px;
        overflow: auto;
    }
</style>

<div class="container">
    <h1>👤 Nachnamen nach Ort (Tirol-Archiv)</h1><br />
    <p>Wähle einen Ort aus. Danach werden alle zugehörigen Familiennamen alphabetisch gruppiert angezeigt.</p>
    <br />
    <div class="top-actions">
        <a href="home.php" class="btn-small">← Zurück zur Admin-Startseite</a>
        <a href="nachnamen-orte.php?refresh=1" class="btn-small" onclick="return confirm('Archiv-Cache neu laden? Das kann kurz dauern.');">↻ Tirol-Archiv Cache neu laden</a>
    </div>

    <div class="summary">
        <strong>Geladene Orte:</strong> <?= count($placesData) ?>
        <?php if ($selectedPlace): ?>
            <br><strong>Ausgewählter Ort:</strong> <?= htmlspecialchars($selectedPlace['ort'], ENT_QUOTES, 'UTF-8') ?>
            <br><strong>Anzahl Nachnamen:</strong> <?= $selectedCount ?>
        <?php endif; ?>
    </div>

    <form method="get" class="filter-box">
        <label for="ort">Ort auswählen</label>
        <select name="ort" id="ort" onchange="this.form.submit()">
            <option value="">-- Bitte Ort wählen --</option>
            <?php foreach ($placesData as $key => $row): ?>
                <option value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>" <?= $selectedOrtKey === $key ? 'selected' : '' ?>>
                    <?= htmlspecialchars($row['ort'], ENT_QUOTES, 'UTF-8') ?> (<?= count($row['names']) ?>)
                </option>
            <?php endforeach; ?>
        </select>
    </form>

    <?php if ($selectedPlace): ?>
        <div class="alphabet-grid">
            <?php foreach ($alphabeticGroups as $letter => $names): ?>
                <section class="letter-card">
                    <h3><?= htmlspecialchars($letter, ENT_QUOTES, 'UTF-8') ?> (<?= count($names) ?>)</h3>
                    <ul class="name-list">
                        <?php foreach ($names as $name): ?>
                            <li><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p>Bitte einen Ort auswählen, um die alphabetische Nachnamenliste zu sehen.</p>
    <?php endif; ?>
</div>

<?php require_once '../../layout/footer.php'; ?>
