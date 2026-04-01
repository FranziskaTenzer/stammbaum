<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

$pageTitle = "Alle Nachnamen in Tirol (Tirol-Archiv)";
require_once '../../layout/header.php';
require_once '../../lib/include.php';
require_once '../../lib/tirol-archiv-helper.php';

if (!isAdmin()) {
    die('❌ Zugriff verweigert! Nur für Administratoren.');
}

function tirolArchivPrefixesForTirolList() {
    return [
        'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm',
        'n', 'o', 'pq', 'r', 's', 'sch', 'sp', 'st', 't', 'u', 'v', 'w', 'xyz'
    ];
}

function surnameInitialForTirolList($name) {
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

function getAllTirolSurnames($forceRefresh = false) {
    $all = [];

    foreach (tirolArchivPrefixesForTirolList() as $prefix) {
        if ($forceRefresh) {
            $cacheFile = getTirolArchivCacheFile($prefix);
            if ($cacheFile && file_exists($cacheFile)) {
                @unlink($cacheFile);
            }
        }

        $namesWithPlaces = getTirolArchivNamesWithPlaces($prefix);
        foreach ($namesWithPlaces as $surname => $places) {
            $name = trim((string)$surname);
            if ($name === '') {
                continue;
            }
            $all[$name] = true;
        }
    }

    $names = array_keys($all);
    usort($names, 'strcasecmp');

    return $names;
}

$forceRefresh = isset($_GET['refresh']) && $_GET['refresh'] === '1';
$allNames = getAllTirolSurnames($forceRefresh);
$totalNames = count($allNames);

$alphabeticGroups = [];
foreach ($allNames as $name) {
    $letter = surnameInitialForTirolList($name);
    if (!isset($alphabeticGroups[$letter])) {
        $alphabeticGroups[$letter] = [];
    }
    $alphabeticGroups[$letter][] = $name;
}

ksort($alphabeticGroups);
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
    <h1>👤 Alle Nachnamen in Tirol (Tirol-Archiv)</h1><br />
    <p>Gesamtliste aller im Tirol-Archiv gefundenen Familiennamen, alphabetisch gruppiert.</p>
    <br />

    <div class="top-actions">
        <a href="home.php" class="btn-small">← Zurück zur Admin-Startseite</a>
        <a href="nachnamen-tirol.php?refresh=1" class="btn-small" onclick="return confirm('Archiv-Cache neu laden? Das kann kurz dauern.');">↻ Tirol-Archiv Cache neu laden</a>
    </div>

    <div class="summary">
        <strong>Nachnamen gesamt:</strong> <?= $totalNames ?>
        <br><strong>Buchstaben-Gruppen:</strong> <?= count($alphabeticGroups) ?>
    </div>

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
</div>

<?php require_once '../../layout/footer.php'; ?>
