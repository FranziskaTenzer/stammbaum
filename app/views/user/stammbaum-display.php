<?php

$pageTitle = "Anzeige Stammbaum";
$extraHead = '<style>
.container {
    display:flex;
    gap:20px;
    justify-content:center;
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

function formatSpouse($name, $geb, $tod) {
    
    $gebText = !empty($geb) ? " * " . formatDBDateOrNull($geb) : "";
    $todText = !empty($tod) ? " † " . formatDBDateOrNull($tod) : "";
    
    return $name . $gebText . $todText;
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
    
    if (empty($ids)) return [[], [], []];
    
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
        LEFT JOIN person v ON v.id = e.vater_id
        LEFT JOIN person m ON m.id = e.mutter_id
        WHERE e.vater_id IN ($placeholders)
           OR e.mutter_id IN ($placeholders)
    ");
    
    $stmt->execute(array_merge($ids, $ids));
    $ehen = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $spouseMap = [];
    foreach ($ehen as $ehe) {
        if ($ehe['v_id']) $spouseMap[$ehe['v_id']][] = $ehe;
        if ($ehe['m_id']) $spouseMap[$ehe['m_id']][] = $ehe;
    }
    
    return [$personsById, $childrenMap, $spouseMap];
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
function renderAncestors($id, $personsById, $spouseMap, &$visited, $depth = 0, $maxDepth = 6){
    
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
            
            $html .= "<li class='node open'>";
            $html .= "<div class='person' style='margin-left:" . ($depth * 20) . "px'>
                👨 {$fatherText}
             </div>";
            
            // ✅ Rekursion Vater
            $html .= renderAncestors($father['id'], $personsById, $spouseMap, $visited, $depth + 1, $maxDepth);
            
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
            
            $html .= "<li class='node open'>";
            $html .= "<div class='person' style='margin-left:" . ($depth * 20) . "px'>
                👩 {$motherText}
             </div>";
            
            // ✅ Rekursion Mutter (NEU!)
            $html .= renderAncestors($mother['id'], $personsById, $spouseMap, $visited, $depth + 1, $maxDepth);
            
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
function renderDescendantsTree($personId, $personsById, $childrenMap, $spouseMap, &$visited = [], $depth = 0, $maxDepth = 6) {
    
    if ($depth > $maxDepth) return "";
    if (!isset($personsById[$personId])) return "";
    
    $html = "<ul>";
    
    // Kinder holen
    $children = $childrenMap[$personId] ?? [];
    
    foreach ($children as $child) {
        
        if (isset($visited[$child['id']])) continue;
        $visited[$child['id']] = true;
        
        $html .= "<li class='node open'>";
        
        $html .= "<div class='person' style='margin-left:".($depth * 20)."px'>
                    👶 {$child['vorname']} {$child['nachname']}
                    " . (!empty($child['geburtsdatum']) ? " * ".formatDBDateOrNull($child['geburtsdatum']) : "") . "
                    " . (!empty($child['sterbedatum']) ? " † ".formatDBDateOrNull($child['sterbedatum']) : "") . "
                 </div>";
        
        // Ehen des Kindes anzeigen
        foreach ($spouseMap[$child['id']] ?? [] as $ehe) {
            
            if ($ehe['v_id'] == $child['id']) {
                $partnerName = formatSpouse(
                    $ehe['m_vorname'] . " " . $ehe['m_nachname'],
                    $ehe['m_geb'],
                    $ehe['m_sterb']
                    );
                $partnerId = $ehe['m_id'];
            } else {
                $partnerName = formatSpouse(
                    $ehe['v_vorname'] . " " . $ehe['v_nachname'],
                    $ehe['v_geb'],
                    $ehe['v_sterb']
                    );
                $partnerId = $ehe['v_id'];
            }
            
            $hochzeit = !empty($ehe['heiratsdatum'])
            ? formatDBDateOrNull($ehe['heiratsdatum'])
            : "";
            
            $html .= "<div class='ehe' style='margin-left:".(($depth * 20) + 20)."px'>
                        💍 {$partnerName} (⚭ {$hochzeit})
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
            $maxDepth
            );
        
        $html .= "</li>";
    }
    
    $html .= "</ul>";
    
    return $html;
}
// =========================
// 🆕 VORFAHREN ALS SPALTEN
// =========================
function buildAncestorColumns($startId, $personsById, $maxDepth = 3) {
    
    $columns = [];
    $current = [$startId];
    
    for ($depth = 0; $depth < $maxDepth; $depth++) {
        
        $next = [];
        $columns[$depth] = [];
        
        foreach ($current as $id) {
            
            if (!isset($personsById[$id])) {
                $columns[$depth][] = null;
                $columns[$depth][] = null;
                continue;
            }
            
            $p = $personsById[$id];
            
            // Vater
            if (!empty($p['vater_id']) && isset($personsById[$p['vater_id']])) {
                $columns[$depth][] = $personsById[$p['vater_id']];
                $next[] = $p['vater_id'];
            } else {
                $columns[$depth][] = null;
            }
            
            // Mutter
            if (!empty($p['mutter_id']) && isset($personsById[$p['mutter_id']])) {
                $columns[$depth][] = $personsById[$p['mutter_id']];
                $next[] = $p['mutter_id'];
            } else {
                $columns[$depth][] = null;
            }
        }
        
        $current = $next;
    }
    
    return array_reverse($columns);
}
function renderAncestorColumns($columns) {
    
    $html = "<div class='ancestor-flex'>";
    
    foreach ($columns as $generation) {
        
        $html .= "<div class='ancestor-col'>";
        
        $count = count($generation);
        
        for ($i = 0; $i < $count; $i++) {
            
            $p = $generation[$i];
            
            if ($p === null) {
                $html .= "<div class='person placeholder'></div>";
            } else {
                $html .= "<div class='person'>
                    👤 {$p['vorname']} {$p['nachname']}<br>
                    " . (!empty($p['geburtsdatum']) ? " * ". formatDBDateOrNull($p['geburtsdatum']) : "") . "<br>
                    " . (!empty($p['sterbedatum']) ? " † ". formatDBDateOrNull($p['sterbedatum']) : "") . "
                </div>";
            }
            
            // 👉 Nach JEDEM PAAR (also jedes 2. Element) Linie einfügen
            if ($i % 2 === 1 && $i < $count - 1) {
                $html .= "<div class='ancestor-line'></div>";
            }
        }
        
        $html .= "</div>";
    }
    
    $html .= "</div>";
    
    return $html;
}

// =========================
// START
// =========================
list($personsById, $childrenMap, $spouseMap) = loadData($pdo, $startId);

if (!isset($personsById[$startId])) {
    die("Person nicht gefunden");
}

$visitedAnc = [];
$visitedDesc = [];

$p = $personsById[$startId];

?>
<a href="stammbaum-search.php?vorname=<?= $p['vorname']; ?>&nachname=<?= $p['nachname']?>&geburtsdatum=<?= $p['geburtsdatum']?>" class="btn btn-primary">Zurück zur Übersicht</a>
<br />

<h2 style="text-align:center;">Stammbaum</h2>
<br />
<br />
<div class="container">

    <div class="column">
        <h3>⬆ Vorfahren</h3>
        <?php 
        $columns = buildAncestorColumns($startId, $personsById, 3);
        echo renderAncestorColumns($columns);
        ?>
    </div>

<div class="column center">

    <h2><?= $p['vorname'] . " " . $p['nachname'] ?></h2>

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
    if ($ehe['v_id'] == $startId) {
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
        ⚭ <?= $hochzeit ?>
    </div>

<?php endforeach; ?>

</div>
 <div class="column">
        <h3>⬇ Nachkommen</h3>
        <?php 
            $visitedDesc = [];
            echo renderDescendantsTree($startId, $personsById, $childrenMap, $spouseMap, $visitedDesc); 
        ?>
    </div>

</div>

<?php require_once dirname(__DIR__, 2) . '/layout/footer.php'; ?>
