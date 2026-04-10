<?php

$pageTitle = "Anzeige Stammbaum horizontal komplett";
$extraHead = '<style>
.container {
    display:flex;
    gap:20px;
    justify-content:center;
}

.action-bar {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 12px;
}

.action-bar .btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 42px;
    line-height: 1.2;
}
    
.column {
    width:30%;
    background:white;
    padding:15px;
    border-radius:10px;
    box-shadow:0 0 10px rgba(0,0,0,0.1);
    overflow:auto;
}
    
.center { text-align:center; max-width: 300px; }
    
ul { list-style:none; padding-left:20px; }
    
.person {
    background:#fff;
    padding:6px;
    margin:4px;
    border-radius:6px;
    cursor:pointer;
}
    
.node > ul { display:none; }
.node.open > ul { display:block; }
    
.person:hover { background:#e3f2fd; }
    
/* Vorfahren Layout */
.ancestor-flex {
    display: flex;
    gap: 40px;
    align-items: center;
}
    
.ancestor-col {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    height: 100%;
    min-height: 400px;
}
    
/* Linie zwischen Elternpaaren */
.ancestor-line {
    height: 2px;
    background: #999;
    margin: 6px 0;
    width: 100%;
}
    
.placeholder {
    visibility: hidden;
}
    
/* ← NEUE ZEILE: Verschmiert-Effekt für TestAccount */
.blurred-text {
    color: transparent;
    text-shadow: 0 0 8px rgba(0,0,0,0.5);
    filter: blur(4px);
    user-select: none;
}

@media print {
    @page {
        size: auto;
        margin: 12mm;
    }

    .sidebar,
    .hamburger-menu,
    .sidebar-overlay,
    .sidebar-backdrop,
    .action-bar {
        display: none !important;
    }

    .main-content,
    .content-wrapper {
        height: auto !important;
        overflow: visible !important;
        padding: 0 !important;
    }

    .container {
        display: block !important;
    }

    .column {
        width: 100% !important;
        box-shadow: none !important;
        border: 1px solid #ddd;
        border-radius: 8px;
        margin-bottom: 12mm;
        overflow: visible !important;
        page-break-inside: avoid;
    }

    .node > ul {
        display: block !important;
    }

    .person {
        page-break-inside: avoid;
    }
}
</style>
<script>
document.addEventListener("click", function(e){
    if(e.target.classList.contains("person")){
        let node = e.target.parentElement;
        node.classList.toggle("open");
    }
});
</script>';

require_once '../../layout/header.php';
require_once '../../lib/include.php';

ini_set('display_errors', 1);

$pdo = getPDO();

$startId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Username für Prüfung laden
$username = $_SESSION['username'];
$isTestAccount = ($username === 'TestAccount');


function getCommonChildren($ehe, $childrenMap) {
    
    $children = [];
    
    if (!empty($ehe['v_id']) && !empty($ehe['m_id'])) {
        
        $childrenV = $childrenMap[$ehe['v_id']] ?? [];
        $childrenM = $childrenMap[$ehe['m_id']] ?? [];
        
        $idsM = array_column($childrenM, 'id');
        
        foreach ($childrenV as $child) {
            if (in_array($child['id'], $idsM)) {
                $children[] = $child;
            }
        }
    }
    
    return $children;
}

// ← GEÄNDERT: Gibt CSS-Klasse für Verschmieren zurück
function getBlurClass($isTestAccount) {
    return '';
}

function formatBirthDeathSuffix($geburtsdatum, $sterbedatum) {
    $geb = !empty($geburtsdatum) ? " * " . formatDBDateOrNull($geburtsdatum) : "";
    $tod = !empty($sterbedatum) ? " † " . formatDBDateOrNull($sterbedatum) : "";
    return $geb . $tod;
}

function anonymizedDescendantLabel($depth, $index) {
    if ($depth <= 1) {
        return 'Kind ' . $index;
    }
    if ($depth === 2) {
        return 'Enkel ' . $index;
    }
    return str_repeat('Ur', $depth - 2) . 'enkel ' . $index;
}

function anonymizedAncestorRole($isFather, $depth) {
    if ($depth <= 1) {
        return $isFather ? 'Vater' : 'Mutter';
    }
    if ($depth === 2) {
        return $isFather ? 'Grossvater' : 'Grossmutter';
    }
    return str_repeat('Ur', $depth - 2) . ($isFather ? 'grossvater' : 'grossmutter');
}

function anonymizedAncestorSourceLabel($depth, $index, $person) {
    if ($depth <= 1) {
        return 'Kind ' . $index;
    }

    $isMale = (($person['geschlecht'] ?? '') === 'm');
    return anonymizedAncestorRole($isMale, $depth - 1);
}

function anonymizedSpouseLabel($index) {
    return 'Ehepartner ' . $index;
}

function formatSpouse($name, $geb, $tod) {
    
    $gebText = !empty($geb) ? " * " . formatDBDateOrNull($geb) : "";
    $todText = !empty($tod) ? " † " . formatDBDateOrNull($tod) : "";
    
    return $name . $gebText . $todText;
}

function formatMarriageDivorceInfo($heiratsdatum, $scheidungsdatum) {
    $parts = [];

    if (!empty($heiratsdatum)) {
        $parts[] = "⚭ " . formatDBDateOrNull($heiratsdatum);
    }

    if (!empty($scheidungsdatum)) {
        $parts[] = "geschieden " . formatDBDateOrNull($scheidungsdatum);
    }

    return implode(" | ", $parts);
}

function formatSpouseEntryFromEhe($personId, $ehe) {
    if ($ehe['v_id'] == $personId) {
        $partnerName = formatSpouse(
            $ehe['m_vorname'] . " " . $ehe['m_nachname'],
            $ehe['m_geb'],
            $ehe['m_sterb']
        );
    } else {
        $partnerName = formatSpouse(
            $ehe['v_vorname'] . " " . $ehe['v_nachname'],
            $ehe['v_geb'],
            $ehe['v_sterb']
        );
    }

    $eventInfo = formatMarriageDivorceInfo($ehe['heiratsdatum'] ?? null, $ehe['scheidungsdatum'] ?? null);
    return $eventInfo !== "" ? ($partnerName . " (" . $eventInfo . ")") : $partnerName;
}

// =========================
// RELEVANTE IDS SAMMELN
// =========================
function getRelevantIds($pdo, $startId) {
    
    $ids = [$startId];
    $queue = [$startId];
    
    while (!empty($queue)) {
        $current = array_pop($queue);
        
        // 1. Kinder laden
        $stmt = $pdo->prepare("
            SELECT id FROM person
            WHERE vater_id = :id OR mutter_id = :id
        ");
        $stmt->execute(['id' => $current]);
        
        $children = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($children as $childId) {
            if (!in_array($childId, $ids)) {
                $ids[] = $childId;
                $queue[] = $childId;
            }
        }
        
        // 2. Eltern laden (NEU)
        $stmt = $pdo->prepare("
            SELECT vater_id, mutter_id
            FROM person
            WHERE id = :id
        ");
        $stmt->execute(['id' => $current]);
        
        $parents = $stmt->fetch(PDO::FETCH_ASSOC);
        
        foreach (['vater_id', 'mutter_id'] as $parentField) {
            if (!empty($parents[$parentField])) {
                $parentId = $parents[$parentField];
                
                if (!in_array($parentId, $ids)) {
                    $ids[] = $parentId;
                    $queue[] = $parentId;
                }
            }
        }
    }
    
    return $ids;
}

// =========================
// DATEN LADEN
// =========================
function loadData($pdo, $startId) {
    
    $ids = getRelevantIds($pdo, $startId);
    
    if (empty($ids)) return [[], [], [], []];
    
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    
    // Personen laden
    $stmt = $pdo->prepare("SELECT * FROM person WHERE id IN ($placeholders)");
    $stmt->execute($ids);
    $persons = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $personsById = [];
    foreach ($persons as $p) {
        $personsById[$p['id']] = $p;
    }
    
    // Kinder-Map
    $childrenMap = [];
    foreach ($persons as $p) {
        if (!empty($p['vater_id'])) {
            $childrenMap[$p['vater_id']][] = $p;
        }
        if (!empty($p['mutter_id'])) {
            $childrenMap[$p['mutter_id']][] = $p;
        }
    }
    
    // Ehen laden
    $stmt = $pdo->prepare("
        SELECT e.*,
               v.id as v_id, v.vorname as v_vorname, v.nachname as v_nachname,
               v.geburtsdatum as v_geb, v.sterbedatum as v_sterb,
               m.id as m_id, m.vorname as m_vorname, m.nachname as m_nachname,
               m.geburtsdatum as m_geb, m.sterbedatum as m_sterb
        FROM ehe e
          LEFT JOIN person v ON v.id = e.mann_id
          LEFT JOIN person m ON m.id = e.frau_id
          WHERE e.mann_id IN ($placeholders)
              OR e.frau_id IN ($placeholders)
    ");
    
    $stmt->execute(array_merge($ids, $ids));
    $ehen = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $spouseMap = [];
    $coupleEventMap = [];
    foreach ($ehen as $ehe) {
        if (!empty($ehe['v_id']) && !empty($ehe['m_id'])) {
            $pairEvents = [
                'heiratsdatum' => $ehe['heiratsdatum'] ?? null,
                'scheidungsdatum' => $ehe['scheidungsdatum'] ?? null,
            ];
            $coupleEventMap[$ehe['v_id'] . '-' . $ehe['m_id']] = $pairEvents;
            $coupleEventMap[$ehe['m_id'] . '-' . $ehe['v_id']] = $pairEvents;
        }

        if ($ehe['v_id']) $spouseMap[$ehe['v_id']][] = $ehe;
        if ($ehe['m_id']) $spouseMap[$ehe['m_id']][] = $ehe;
    }
    
    return [$personsById, $childrenMap, $spouseMap, $coupleEventMap];
}

// =========================
// HELPER: HOCHZEITSDATUM
// =========================
function getMarriageDate($person, $spouseMap) {
    
    $ehen = $spouseMap[$person['id']] ?? [];
    
    foreach ($ehen as $ehe) {
        if (!empty($ehe['heiratsdatum'])) {
            return formatDBDateOrNull($ehe['heiratsdatum']);
        }
    }
    
    return null;
}

// =========================
// FORMAT PERSON
// =========================
function formatPerson($p, $spouseMap = []) {
    if (!$p) return "";
    
    $geb = !empty($p['geburtsdatum']) ? " * ". formatDBDateOrNull($p['geburtsdatum']) : "";
    $tod = !empty($p['sterbedatum']) ? " † ". formatDBDateOrNull($p['sterbedatum']) : "";
    
    $hochzeit = "";
    if (!empty($spouseMap)) {
        $marriageDate = getMarriageDate($p, $spouseMap);
        if ($marriageDate) {
            $hochzeit = " ⚭ ".$marriageDate;
        }
    }
    
    return "{$p['vorname']} {$p['nachname']}{$geb}{$tod}{$hochzeit}";
}

// =========================
// VORFAHREN
// =========================
function renderAncestors($id, $personsById, $spouseMap, &$visited, $depth = 0, $maxDepth = 6, $isTestAccount = false) {
    
    if ($depth > $maxDepth) return "";
    if (!isset($personsById[$id])) return "";
    
    if (isset($visited[$id])) return "";
    $visited[$id] = true;
    
    $p = $personsById[$id];
    
    $html = "<ul>";
    
    // Vater + Mutter als Paar (eine Generation)
    if (!empty($p['vater_id']) || !empty($p['mutter_id'])) {
        
        $html .= "<li class='node open'>";
        // Vater
        if (!empty($p['vater_id']) && isset($personsById[$p['vater_id']])) {
            $father = $personsById[$p['vater_id']];
            
            $fatherText = formatSpouse(
                $father['vorname'] . " " . $father['nachname'],
                $father['geburtsdatum'],
                $father['sterbedatum']
                );
            
            // ← GEÄNDERT: Blur-Klasse korrekt kombiniert
            $blurClass = getBlurClass($isTestAccount);
            $classes = "person" . ($blurClass ? " " . $blurClass : "");
            
            $html .= "<li class='node open'>";
            $html .= "<div class='{$classes}' style='margin-left:" . ($depth * 20) . "px'>
                👨 {$fatherText}
             </div>";
            
            // ✅ Rekursion Vater
            $html .= renderAncestors($father['id'], $personsById, $spouseMap, $visited, $depth + 1, $maxDepth, $isTestAccount);
            
            $html .= "</li>";
        }
        
        // Mutter
        if (!empty($p['mutter_id']) && isset($personsById[$p['mutter_id']])) {
            $mother = $personsById[$p['mutter_id']];
            
            $motherText = formatSpouse(
                $mother['vorname'] . " " . $mother['nachname'],
                $mother['geburtsdatum'],
                $mother['sterbedatum']
                );
            
            // ← GEÄNDERT: Blur-Klasse korrekt kombiniert
            $blurClass = getBlurClass($isTestAccount);
            $classes = "person" . ($blurClass ? " " . $blurClass : "");
            
            $html .= "<li class='node open'>";
            $html .= "<div class='{$classes}' style='margin-left:" . ($depth * 20) . "px'>
                👩 {$motherText}
             </div>";
            
            // ✅ Rekursion Mutter
            $html .= renderAncestors($mother['id'], $personsById, $spouseMap, $visited, $depth + 1, $maxDepth, $isTestAccount);
            
            $html .= "</li>";
        }
        
        $html .= "</li>";
    }
    
    $html .= "</ul>";
    
    return $html;
}

// =========================
// NACHKOMMEN
// =========================
function renderDescendantsTree($personId, $personsById, $childrenMap, $spouseMap, &$visited = [], $depth = 0, $maxDepth = 6, $isTestAccount = false) {
    
    if ($depth > $maxDepth) return "";
    if (!isset($personsById[$personId])) return "";
    
    $html = "<ul>";
    
    // Kinder holen
    $children = $childrenMap[$personId] ?? [];
    
    $childIndex = 0;
    foreach ($children as $child) {
        
        if (isset($visited[$child['id']])) continue;
        $visited[$child['id']] = true;
        $childIndex++;
        
        $html .= "<li class='node open'>";
        
        // ← GEÄNDERT: Blur-Klasse korrekt kombiniert
        $blurClass = getBlurClass($isTestAccount);
        $classes = "person" . ($blurClass ? " " . $blurClass : "");
        
        $childLabel = $isTestAccount
            ? anonymizedDescendantLabel($depth + 1, $childIndex)
            : trim($child['vorname'] . ' ' . $child['nachname']);
        $html .= "<div class='{$classes}' style='margin-left:".($depth * 20)."px'>
                    👶 {$childLabel}" . formatBirthDeathSuffix($child['geburtsdatum'] ?? null, $child['sterbedatum'] ?? null) . "
                 </div>";
        
        // Ehen des Kindes anzeigen
        $spouseIndex = 0;
        foreach ($spouseMap[$child['id']] ?? [] as $ehe) {
            $spouseIndex++;
            
            if ($isTestAccount) {
                $partnerName = anonymizedSpouseLabel($spouseIndex);
            } elseif ($ehe['v_id'] == $child['id']) {
                $partnerName = formatSpouse(
                    $ehe['m_vorname'] . " " . $ehe['m_nachname'],
                    $ehe['m_geb'],
                    $ehe['m_sterb']
                    );
            } else {
                $partnerName = formatSpouse(
                    $ehe['v_vorname'] . " " . $ehe['v_nachname'],
                    $ehe['v_geb'],
                    $ehe['v_sterb']
                    );
            }
            
            $eheInfo = formatMarriageDivorceInfo($ehe['heiratsdatum'] ?? null, $ehe['scheidungsdatum'] ?? null);
            
            // ← GEÄNDERT: Blur-Klasse korrekt kombiniert
            $blurClass = getBlurClass($isTestAccount);
            $classes = "ehe" . ($blurClass ? " " . $blurClass : "");
            
            $html .= "<div class='{$classes}' style='margin-left:".(($depth * 20) + 20)."px'>
                                💍 {$partnerName}" . ($eheInfo !== "" ? " ({$eheInfo})" : "") . "
                     </div>";
        }
        
        // Rekursion (Enkel etc.)
        $html .= renderDescendantsTree(
            $child['id'],
            $personsById,
            $childrenMap,
            $spouseMap,
            $visited,
            $depth + 1,
            $maxDepth,
            $isTestAccount
            );
        
        $html .= "</li>";
    }
    
    $html .= "</ul>";
    
    return $html;
}

// =========================
// 🆕 VORFAHREN ALS SPALTEN
// =========================
function parentUnitForPerson($personId, $personsById) {
    if (!isset($personsById[$personId])) {
        return null;
    }

    $person = $personsById[$personId];
    $fatherId = !empty($person['vater_id']) ? (int)$person['vater_id'] : 0;
    $motherId = !empty($person['mutter_id']) ? (int)$person['mutter_id'] : 0;

    if ($fatherId <= 0 && $motherId <= 0) {
        return null;
    }

    return ['father_id' => $fatherId, 'mother_id' => $motherId];
}

function parentUnitKey($fatherId, $motherId) {
    return $fatherId . '-' . $motherId;
}

function collectAncestorUnitsByLevel($startId, $personsById, $maxDepth = 6) {
    if (!isset($personsById[$startId])) {
        return [];
    }

    $rootUnit = parentUnitForPerson($startId, $personsById);
    if (!$rootUnit) {
        return [];
    }

    $levels = [];
    $currentLevel = [[
        'father_id' => $rootUnit['father_id'],
        'mother_id' => $rootUnit['mother_id'],
        'from_ids' => [$startId],
    ]];

    $depth = 1;
    while (!empty($currentLevel) && $depth <= $maxDepth) {
        $levels[$depth] = $currentLevel;

        $nextMap = [];
        foreach ($currentLevel as $unit) {
            $memberIds = [];
            if (!empty($unit['father_id'])) {
                $memberIds[] = (int)$unit['father_id'];
            }
            if (!empty($unit['mother_id'])) {
                $memberIds[] = (int)$unit['mother_id'];
            }

            foreach ($memberIds as $memberId) {
                $parentUnit = parentUnitForPerson($memberId, $personsById);
                if (!$parentUnit) {
                    continue;
                }

                $fatherId = (int)$parentUnit['father_id'];
                $motherId = (int)$parentUnit['mother_id'];
                $key = parentUnitKey($fatherId, $motherId);

                if (!isset($nextMap[$key])) {
                    $nextMap[$key] = [
                        'father_id' => $fatherId,
                        'mother_id' => $motherId,
                        'from_ids' => [],
                    ];
                }

                $nextMap[$key]['from_ids'][$memberId] = true;
            }
        }

        $nextLevel = [];
        foreach ($nextMap as $unit) {
            $fromIds = array_map('intval', array_keys($unit['from_ids']));
            sort($fromIds);
            $unit['from_ids'] = $fromIds;
            $nextLevel[] = $unit;
        }

        $currentLevel = $nextLevel;
        $depth++;
    }

    return $levels;
}

function collectAncestorPeopleByLevel($startId, $personsById, $maxDepth = 6) {
    if (!isset($personsById[$startId])) {
        return [];
    }

    $rootUnit = parentUnitForPerson($startId, $personsById);
    if (!$rootUnit) {
        return [];
    }

    $currentLevel = [];
    if (!empty($rootUnit['father_id'])) {
        $currentLevel[(int)$rootUnit['father_id']] = true;
    }
    if (!empty($rootUnit['mother_id'])) {
        $currentLevel[(int)$rootUnit['mother_id']] = true;
    }

    $levels = [];
    $visited = [$startId => true];
    $depth = 1;

    while (!empty($currentLevel) && $depth <= $maxDepth) {
        $ids = array_map('intval', array_keys($currentLevel));
        sort($ids);
        $levels[$depth] = $ids;

        $nextLevel = [];
        foreach ($ids as $personId) {
            $visited[$personId] = true;
            $parentUnit = parentUnitForPerson($personId, $personsById);
            if (!$parentUnit) {
                continue;
            }

            $fatherId = (int)($parentUnit['father_id'] ?? 0);
            $motherId = (int)($parentUnit['mother_id'] ?? 0);

            if ($fatherId > 0 && !isset($visited[$fatherId])) {
                $nextLevel[$fatherId] = true;
            }
            if ($motherId > 0 && !isset($visited[$motherId])) {
                $nextLevel[$motherId] = true;
            }
        }

        $currentLevel = $nextLevel;
        $depth++;
    }

    return $levels;
}

function collectSiblingIds($personId, $personsById, $childrenMap) {
    if (!isset($personsById[$personId])) {
        return [];
    }

    $person = $personsById[$personId];
    $fatherId = !empty($person['vater_id']) ? (int)$person['vater_id'] : 0;
    $motherId = !empty($person['mutter_id']) ? (int)$person['mutter_id'] : 0;

    if ($fatherId <= 0 && $motherId <= 0) {
        return [];
    }

    $candidateIds = [];
    if ($fatherId > 0 && $motherId > 0) {
        $fatherChildren = array_map(static function ($p) { return (int)$p['id']; }, $childrenMap[$fatherId] ?? []);
        $motherChildren = array_map(static function ($p) { return (int)$p['id']; }, $childrenMap[$motherId] ?? []);
        $candidateIds = array_values(array_intersect($fatherChildren, $motherChildren));
    } elseif ($fatherId > 0) {
        $candidateIds = array_map(static function ($p) { return (int)$p['id']; }, $childrenMap[$fatherId] ?? []);
    } else {
        $candidateIds = array_map(static function ($p) { return (int)$p['id']; }, $childrenMap[$motherId] ?? []);
    }

    $result = [];
    foreach ($candidateIds as $candidateId) {
        $candidateId = (int)$candidateId;
        if ($candidateId > 0 && $candidateId !== (int)$personId && isset($personsById[$candidateId])) {
            $result[$candidateId] = true;
        }
    }

    $ids = array_map('intval', array_keys($result));
    sort($ids);
    return $ids;
}

function collectCollateralAncestorsByLevel($ancestorPeopleByLevel, $personsById, $childrenMap) {
    $collateral = [];

    foreach ($ancestorPeopleByLevel as $depth => $personIds) {
        $itemsById = [];

        foreach ($personIds as $personId) {
            $siblings = collectSiblingIds((int)$personId, $personsById, $childrenMap);
            if (empty($siblings)) {
                continue;
            }

            $originPerson = $personsById[(int)$personId] ?? null;
            $originName = $originPerson ? trim(($originPerson['vorname'] ?? '') . ' ' . ($originPerson['nachname'] ?? '')) : 'Unbekannt';

            foreach ($siblings as $siblingId) {
                if (!isset($itemsById[$siblingId])) {
                    $itemsById[$siblingId] = [
                        'id' => (int)$siblingId,
                        'origin_names' => [],
                    ];
                }
                $itemsById[$siblingId]['origin_names'][$originName] = true;
            }
        }

        if (!empty($itemsById)) {
            $items = [];
            foreach ($itemsById as $item) {
                $originNames = array_keys($item['origin_names']);
                sort($originNames);
                $item['origin_names'] = $originNames;
                $items[] = $item;
            }

            usort($items, static function ($a, $b) use ($personsById) {
                $aPerson = $personsById[(int)$a['id']] ?? null;
                $bPerson = $personsById[(int)$b['id']] ?? null;
                $aName = $aPerson ? trim(($aPerson['vorname'] ?? '') . ' ' . ($aPerson['nachname'] ?? '')) : '';
                $bName = $bPerson ? trim(($bPerson['vorname'] ?? '') . ' ' . ($bPerson['nachname'] ?? '')) : '';
                return strcmp($aName, $bName);
            });

            $collateral[(int)$depth] = $items;
        }
    }

    return $collateral;
}

function formatAncestorCoupleLine($fatherId, $motherId, $personsById, $depth = 1, $isTestAccount = false) {
    if ($isTestAccount) {
        $fatherLabel = $fatherId > 0 ? anonymizedAncestorRole(true, $depth) : '';
        $motherLabel = $motherId > 0 ? anonymizedAncestorRole(false, $depth) : '';

        if ($fatherLabel !== '' && $motherLabel !== '') {
            return $fatherLabel . ' + ' . $motherLabel;
        }
        if ($fatherLabel !== '') {
            return $fatherLabel;
        }
        if ($motherLabel !== '') {
            return $motherLabel;
        }

        return 'Unbekannt';
    }

    $fatherName = '';
    $motherName = '';

    if ($fatherId > 0 && isset($personsById[$fatherId])) {
        $fatherName = trim($personsById[$fatherId]['vorname'] . ' ' . $personsById[$fatherId]['nachname']);
    }

    if ($motherId > 0 && isset($personsById[$motherId])) {
        $motherName = trim($personsById[$motherId]['vorname'] . ' ' . $personsById[$motherId]['nachname']);
    }

    if ($fatherName !== '' && $motherName !== '') {
        return $fatherName . ' + ' . $motherName;
    }
    if ($fatherName !== '') {
        return $fatherName;
    }
    if ($motherName !== '') {
        return $motherName;
    }

    return 'Unbekannt';
}

function formatCoupleEventsForAncestors($fatherId, $motherId, $coupleEventMap) {
    if ($fatherId <= 0 || $motherId <= 0) {
        return '';
    }

    $events = $coupleEventMap[$fatherId . '-' . $motherId] ?? null;
    if (!$events) {
        return '';
    }

    $parts = [];
    if (!empty($events['heiratsdatum'])) {
        $parts[] = '⚭ ' . formatDBDateOrNull($events['heiratsdatum']);
    }
    if (!empty($events['scheidungsdatum'])) {
        $parts[] = 'geschieden ' . formatDBDateOrNull($events['scheidungsdatum']);
    }

    return implode(' | ', $parts);
}

function renderAncestorUnits($levels, $personsById, $coupleEventMap, $isTestAccount = false) {
    if (empty($levels)) {
        return '<p>Keine Vorfahren gefunden.</p>';
    }

    krsort($levels);

    $html = "<div class='ancestor-flex'><div class='ancestor-col'>";
    foreach ($levels as $depth => $units) {
        $html .= "<div style='margin-bottom:12px;'>";
        $html .= "<div style='font-weight:600; margin-bottom:6px;'>Generation {$depth}</div>";

        foreach ($units as $unit) {
            $fatherId = (int)($unit['father_id'] ?? 0);
            $motherId = (int)($unit['mother_id'] ?? 0);

            $blurClass = getBlurClass($isTestAccount);
            $classes = "person" . ($blurClass ? " " . $blurClass : "");

            $line = formatAncestorCoupleLine($fatherId, $motherId, $personsById, (int)$depth, $isTestAccount);
            $events = formatCoupleEventsForAncestors($fatherId, $motherId, $coupleEventMap);

            $fromIds = $unit['from_ids'] ?? [];
            $fromNames = [];
            $fromIndex = 0;
            foreach ($fromIds as $fromId) {
                $fromIndex++;
                $fp = $personsById[(int)$fromId] ?? null;
                if ($fp) {
                    $fromNames[] = $isTestAccount
                        ? anonymizedAncestorSourceLabel((int)$depth, $fromIndex, $fp)
                        : trim($fp['vorname'] . ' ' . $fp['nachname']);
                }
            }
            $fromNames = array_values(array_unique($fromNames));
            sort($fromNames);
            $elternVon = !empty($fromNames) ? 'Eltern von: ' . implode(', ', $fromNames) : '';

            $html .= "<div class='{$classes}'>👤 {$line}";
            if ($events !== '') {
                $html .= "<div style='margin-top:4px; font-size:0.9em; color:#555;'>💍 {$events}</div>";
            }
            if ($elternVon !== '') {
                $html .= "<div style='margin-top:2px; font-size:0.85em; color:#888;'>{$elternVon}</div>";
            }
            $html .= "</div>";
        }

        $html .= "</div>";
    }
    $html .= "</div></div>";

    return $html;
}

function renderPersonLineWithOptionalAnonymous($person, $isTestAccount, $depth, $index) {
    if ($isTestAccount) {
        return anonymizedDescendantLabel($depth, $index) . formatBirthDeathSuffix($person['geburtsdatum'] ?? null, $person['sterbedatum'] ?? null);
    }

    return formatPerson($person);
}

function renderAncestorUnitsWithCollateral($levels, $collateralLevels, $personsById, $spouseMap, $coupleEventMap, $isTestAccount = false) {
    if (empty($levels)) {
        return '<p>Keine Vorfahren gefunden.</p>';
    }

    krsort($levels);

    $html = "<div class='ancestor-flex'><div class='ancestor-col'>";
    foreach ($levels as $depth => $units) {
        $html .= "<div style='margin-bottom:14px;'>";
        $html .= "<div style='font-weight:600; margin-bottom:6px;'>Generation {$depth}</div>";

        foreach ($units as $unit) {
            $fatherId = (int)($unit['father_id'] ?? 0);
            $motherId = (int)($unit['mother_id'] ?? 0);

            $blurClass = getBlurClass($isTestAccount);
            $classes = "person" . ($blurClass ? " " . $blurClass : "");

            $line = formatAncestorCoupleLine($fatherId, $motherId, $personsById, (int)$depth, $isTestAccount);
            $events = formatCoupleEventsForAncestors($fatherId, $motherId, $coupleEventMap);

            $fromIds = $unit['from_ids'] ?? [];
            $fromNames = [];
            $fromIndex = 0;
            foreach ($fromIds as $fromId) {
                $fromIndex++;
                $fp = $personsById[(int)$fromId] ?? null;
                if ($fp) {
                    $fromNames[] = $isTestAccount
                        ? anonymizedAncestorSourceLabel((int)$depth, $fromIndex, $fp)
                        : trim($fp['vorname'] . ' ' . $fp['nachname']);
                }
            }
            $fromNames = array_values(array_unique($fromNames));
            sort($fromNames);
            $elternVon = !empty($fromNames) ? 'Eltern von: ' . implode(', ', $fromNames) : '';

            $html .= "<div class='{$classes}'>👤 {$line}";
            if ($events !== '') {
                $html .= "<div style='margin-top:4px; font-size:0.9em; color:#555;'>💍 {$events}</div>";
            }
            if ($elternVon !== '') {
                $html .= "<div style='margin-top:2px; font-size:0.85em; color:#888;'>{$elternVon}</div>";
            }
            $html .= "</div>";
        }

        $sideItems = $collateralLevels[(int)$depth] ?? [];
        if (!empty($sideItems)) {
            $html .= "<div style='margin-top:8px; font-size:0.9em; color:#666; font-weight:600;'>Seitenlinie (Tanten/Onkel dieser Generation)</div>";
            $sideIndex = 0;
            foreach ($sideItems as $item) {
                $sideIndex++;
                $personId = (int)($item['id'] ?? 0);
                if (!isset($personsById[$personId])) {
                    continue;
                }

                $person = $personsById[$personId];
                $line = htmlspecialchars(renderPersonLineWithOptionalAnonymous($person, $isTestAccount, (int)$depth + 1, $sideIndex), ENT_QUOTES, 'UTF-8');
                $originNames = $item['origin_names'] ?? [];
                $relation = $isTestAccount ? 'Seitenlinie dieser Generation' : ('Geschwister von: ' . (!empty($originNames) ? implode(', ', $originNames) : '-'));

                $spouseLines = [];
                $spouseIdx = 0;
                foreach ($spouseMap[$personId] ?? [] as $ehe) {
                    $spouseIdx++;
                    $spouseLines[] = $isTestAccount ? anonymizedSpouseLabel($spouseIdx) : formatSpouseEntryFromEhe($personId, $ehe);
                }
                $spouseText = !empty($spouseLines) ? implode(', ', array_values(array_unique($spouseLines))) : '-';

                $blurClass = getBlurClass($isTestAccount);
                $classes = "person" . ($blurClass ? " " . $blurClass : "");

                $html .= "<div class='{$classes}'>↔ {$line}";
                $html .= "<div style='margin-top:2px; font-size:0.85em; color:#666;'>" . htmlspecialchars($relation, ENT_QUOTES, 'UTF-8') . "</div>";
                $html .= "<div style='margin-top:2px; font-size:0.85em; color:#555;'>💍 " . htmlspecialchars($spouseText, ENT_QUOTES, 'UTF-8') . "</div>";
                $html .= "</div>";
            }
        }

        $html .= "</div>";
    }
    $html .= "</div></div>";

    return $html;
}

// =========================
// START
// =========================
list($personsById, $childrenMap, $spouseMap, $coupleEventMap) = loadData($pdo, $startId);

if (!isset($personsById[$startId])) {
    die("Person nicht gefunden");
}

$visitedAnc = [];
$visitedDesc = [];

$p = $personsById[$startId];

?>
<div class="action-bar">
    <a href="stammbaum-search.php?vorname=<?= $p['vorname']; ?>&nachname=<?= $p['nachname']?>" class="btn btn-primary">Zurück zur Übersicht</a>
    <a href="stammbaum-display.php?id=<?= (int)$startId ?>" class="btn btn-primary">horizontale Ansicht</a>
    <a href="stammbaum-display-extended.php?id=<?= (int)$startId ?>" class="btn btn-primary">vertikale Ansicht</a>
    <a href="stammbaum-display-complete.php?id=<?= (int)$startId ?>" class="btn btn-primary">Stammbaum vertikal komplett (inkl. Tanten und Onkel)</a>
    <button type="button" class="btn btn-primary" onclick="window.print()">🖨 Druckversion (PDF)</button>
</div>
    
<br />

<h2 style="text-align:center;">Stammbaum</h2>
<br />
<div class="container">

    <div class="column">
        <h3>⬆ Vorfahren inkl. Seitenlinien</h3>
        <?php 
        $ancestorLevels = collectAncestorUnitsByLevel($startId, $personsById, 6);
        $ancestorPeopleByLevel = collectAncestorPeopleByLevel($startId, $personsById, 6);
        $collateralAncestorLevels = collectCollateralAncestorsByLevel($ancestorPeopleByLevel, $personsById, $childrenMap);
        echo renderAncestorUnitsWithCollateral($ancestorLevels, $collateralAncestorLevels, $personsById, $spouseMap, $coupleEventMap, $isTestAccount);
        ?>
    </div>

<div class="column center">

    <h2><?= $isTestAccount ? 'Kind 1' : ($p['vorname'] . " " . $p['nachname']) ?></h2>

    <div style="margin-top:10px; color:#555;">
        <div>Geboren: <?= !empty($p['geburtsdatum']) ? formatDBDateOrNull($p['geburtsdatum']) : "-" ?></div>
        <div>Gestorben: <?= !empty($p['sterbedatum']) ? formatDBDateOrNull($p['sterbedatum']) : "-" ?></div>
    </div>
<br/>
    <hr>
<br />
    <h4>Ehe(n)</h4>
<br />
    <?php foreach ($spouseMap[$startId] ?? [] as $ehe): ?>

    <?php
    $spouseLoopIndex = ($spouseLoopIndex ?? 0) + 1;
    if ($isTestAccount) {
        $partnerName = anonymizedSpouseLabel($spouseLoopIndex);
    } elseif ($ehe['v_id'] == $startId) {
        $partnerName = formatSpouse(
            $ehe['m_vorname'] . " " . $ehe['m_nachname'],
            $ehe['m_geb'],
            $ehe['m_sterb']
        );
    } else {
        $partnerName = formatSpouse(
            $ehe['v_vorname'] . " " . $ehe['v_nachname'],
            $ehe['v_geb'],
            $ehe['v_sterb']
        );
    }

    $hochzeit = !empty($ehe['heiratsdatum'])
        ? formatDBDateOrNull($ehe['heiratsdatum'])
        : "-";

    ?>

    <div style="margin-bottom:10px; padding:8px; border:1px solid #ddd; border-radius:6px;">
        💍 <strong><?= $partnerName ?></strong><br>
        ⚭ <?= $hochzeit ?><br>
        <?php if (!empty($ehe['scheidungsdatum'])): ?>geschieden <?= formatDBDateOrNull($ehe['scheidungsdatum']) ?><br><?php endif; ?>
    </div>

<?php endforeach; ?>

</div>
 <div class="column">
        <h3>⬇ Nachkommen</h3>
        <?php 
            $visitedDesc = [];
            echo renderDescendantsTree($startId, $personsById, $childrenMap, $spouseMap, $visitedDesc, 0, 6, $isTestAccount);
        ?>
    </div>

</div>

<?php require_once dirname(__DIR__, 2) . '/layout/footer.php'; ?>