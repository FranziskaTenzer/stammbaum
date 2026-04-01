<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

$pageTitle = "Zeige ähnliche Nachnamen";
require_once '../../layout/header.php';
require_once '../../lib/include.php';

include '../../lib/tirol-archiv-helper.php';

if (!isAdmin()) {
    die('❌ Zugriff verweigert! Nur für Administratoren.');
}

$pdo = getPDO();


// ===========================
// HELPER FUNKTIONEN
// ===========================

// Verwende die Funktion aus tirol-archiv-helper.php (falls noch nicht definiert)
if (!function_exists('levenshteinSimilarity')) {
    function levenshteinSimilarity($str1, $str2) {
        $distance = levenshtein(strtolower($str1), strtolower($str2));
        $maxLen = max(strlen($str1), strlen($str2));
        return ($maxLen == 0) ? 100 : round((1 - ($distance / $maxLen)) * 100);
    }
}

// Get all unique last names
function getSimilarNachnamen($pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT DISTINCT nachname
            FROM person
            WHERE nachname IS NOT NULL AND nachname != ''
            ORDER BY nachname
        ");
        $stmt->execute();
        $nachnamen = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        echo "DB Fehler: " . $e->getMessage();
        return [];
    }
    
    $groups = [];
    $processed = [];
    
    foreach ($nachnamen as $name) {
        if (in_array($name, $processed)) continue;
        
        $group = [$name];
        foreach ($nachnamen as $compareName) {
            if ($compareName != $name && !in_array($compareName, $processed)) {
                $similarity = levenshteinSimilarity($name, $compareName);
                if ($similarity >= 80) {
                    $group[] = $compareName;
                    $processed[] = $compareName;
                }
            }
        }
        
        if (count($group) > 1) {
            $groups[] = $group;
            $processed[] = $name;
        }
    }
    
    return $groups;
}

// Group by first letter with special handling for S variants
function groupNachamenByFirstLetter($groups) {
    $grouped = [];
    
    foreach ($groups as $group) {
        $firstLetter = strtoupper(substr($group[0], 0, 1));
        $prefix = getTirolArchivPrefix($group[0]);
        
        // Für S: unterscheide zwischen S, Sch, Sp, St
        if ($firstLetter === 'S') {
            if ($prefix === 'sch') {
                $key = 'SCH';
            } elseif ($prefix === 'sp') {
                $key = 'SP';
            } elseif ($prefix === 'st') {
                $key = 'ST';
            } else {
                $key = 'S';
            }
        } else {
            $key = $firstLetter;
        }
        
        if (!isset($grouped[$key])) {
            $grouped[$key] = [];
        }
        $grouped[$key][] = $group;
    }
    
    // Sortiere: A-Q, dann R, S, Sch, Sp, St, T, dann U-Z
    uksort($grouped, function($a, $b) {
        // Definiere die gewünschte Reihenfolge
        $order = [
            'A' => 1, 'B' => 2, 'C' => 3, 'D' => 4, 'E' => 5,
            'F' => 6, 'G' => 7, 'H' => 8, 'I' => 9, 'J' => 10,
            'K' => 11, 'L' => 12, 'M' => 13, 'N' => 14, 'O' => 15,
            'P' => 16, 'Q' => 17, 'R' => 18, 'S' => 19, 'SCH' => 20,
            'SP' => 21, 'ST' => 22, 'T' => 23, 'U' => 24, 'V' => 25,
            'W' => 26, 'X' => 27, 'Y' => 28, 'Z' => 29
        ];
        
        $aOrder = $order[$a] ?? 999;
        $bOrder = $order[$b] ?? 999;
        return $aOrder <=> $bOrder;
    });
        
        return $grouped;
}

// Get records for a name
function getRecordsForNachname($pdo, $nachname) {
    try {
        $sql = "
            SELECT
                p.id,
                p.vorname,
                p.nachname,
                p.geburtsdatum,
                p.sterbedatum,
                p.hof,
                p.ort,
                p.bemerkung,
                e.traubuch,
                e.heiratsdatum,
                vater.vorname as vater_vorname,
                vater.nachname as vater_nachname,
                mutter.vorname as mutter_vorname,
                mutter.nachname as mutter_nachname
            FROM person p
            JOIN ehe e ON (p.id = e.mann_id OR p.id = e.frau_id)
            LEFT JOIN person vater ON e.mann_id = vater.id
            LEFT JOIN person mutter ON e.frau_id = mutter.id
            WHERE p.nachname = ?
            ORDER BY e.traubuch, p.geburtsdatum
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nachname]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("DB Error in getRecordsForNachname: " . $e->getMessage());
        return [];
    }
}

function formatDate($date) {
    if (!$date) return '—';
    try {
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d ? $d->format('d.m.Y') : '—';
    } catch (Exception $e) {
        return '—';
    }
}

function formatTraubuch($traubuch) {
    if (strpos($traubuch, '.txt') !== false) {
        return substr($traubuch, 0, strpos($traubuch, '.txt') + 4);
    }
    return $traubuch;
}

function renderPersonRecord($record, $recordId) {
    $html = '<div style="background:#f0f8ff; padding:12px; margin:8px 0; border-left:4px solid #0066cc; border-radius:3px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px;">';
    
    $html .= '<div style="flex-grow:1; min-width:250px; user-select:text;">';
    
    $html .= '<span style="color:#0066cc; font-size:1.1em; font-weight:bold;">' . htmlspecialchars($record['vorname'] . ' ' . $record['nachname'], ENT_QUOTES, 'UTF-8') . '</span>';
    
    if (!empty($record['vater_vorname']) || !empty($record['mutter_vorname'])) {
        $html .= '<span style="color:#666; font-size:0.85em; margin-left:10px;">';
        if (!empty($record['vater_vorname'])) {
            $html .= htmlspecialchars($record['vater_vorname'] . ' ' . $record['vater_nachname'], ENT_QUOTES, 'UTF-8');
        }
        if (!empty($record['mutter_vorname'])) {
            if (!empty($record['vater_vorname'])) $html .= ' & ';
            $html .= htmlspecialchars($record['mutter_vorname'] . ' ' . $record['mutter_nachname'], ENT_QUOTES, 'UTF-8');
        }
        $html .= '</span>';
    }
    
    $html .= '</div>';
    
    if (!empty($record['traubuch'])) {
        $traubuchClean = formatTraubuch($record['traubuch']);
        $html .= '<span style="background:#fff3cd; color:#856404; padding:4px 8px; border-radius:3px; font-size:0.85em; display:inline-block; white-space:nowrap;">';
        $html .= '📖 ' . htmlspecialchars($traubuchClean, ENT_QUOTES, 'UTF-8');
        $html .= '</span>';
    }
    
    $html .= '<span class="toggle-icon" style="color:#0066cc; font-size:1.2em; font-weight:bold; transition:transform 0.3s; cursor:pointer; user-select:none;" onclick="toggleRecord(this, \'' . htmlspecialchars($recordId, ENT_QUOTES) . '\'); event.stopPropagation();">▶</span>';
    
    $html .= '</div>';
    
    $html .= '<div id="record-' . htmlspecialchars($recordId, ENT_QUOTES) . '" style="display:none; margin-top:12px; margin-left:12px; padding:12px; background:#f9f9f9; border-radius:3px; border-left:4px solid #0066cc;">';
    
    if (!empty($record['geburtsdatum']) || !empty($record['sterbedatum'])) {
        $html .= '<span style="color:#555; font-size:0.9em; display:block; margin-bottom:8px;">';
        $html .= '<strong>Lebensdaten:</strong> ';
        if (!empty($record['geburtsdatum'])) {
            $html .= 'geb. ' . formatDate($record['geburtsdatum']);
        }
        if (!empty($record['sterbedatum'])) {
            if (!empty($record['geburtsdatum'])) $html .= ' | ';
            $html .= 'gest. ' . formatDate($record['sterbedatum']);
        }
        $html .= '</span>';
    }
    
    if (!empty($record['hof']) || !empty($record['ort']) || !empty($record['bemerkung'])) {
        $html .= '<span style="color:#888; font-size:0.85em; display:block;">';
        if (!empty($record['hof'])) {
            $html .= '<strong>Hof:</strong> ' . htmlspecialchars($record['hof'], ENT_QUOTES, 'UTF-8') . '<br>';
        }
        if (!empty($record['ort'])) {
            $html .= '<strong>Ort:</strong> ' . htmlspecialchars($record['ort'], ENT_QUOTES, 'UTF-8') . '<br>';
        }
        if (!empty($record['bemerkung'])) {
            $html .= '<strong>Bem.:</strong> ' . htmlspecialchars($record['bemerkung'], ENT_QUOTES, 'UTF-8') . '<br>';
        }
        $html .= '</span>';
    }
    
    if (!empty($record['heiratsdatum'])) {
        $html .= '<span style="color:#888; font-size:0.85em; display:block;">';
        $html .= '<strong>Heirat:</strong> ' . formatDate($record['heiratsdatum']);
        $html .= '</span>';
    }
    
    $html .= '</div>';
    
    return $html;
}

function renderNameGroup($groupNames, $pdo) {
    $html = '<div class="name-group" style="background:#f9f9f9; padding:12px; margin:10px 0; border-left:4px solid #666; border-radius:3px;">';
    $html .= '<strong style="color:#333;">' . htmlspecialchars(implode(', ', $groupNames), ENT_QUOTES, 'UTF-8') . '</strong>';
    $html .= renderArchiveNamesBoxForGroup($groupNames);
    $html .= '</div>';
    return $html;
}

?>
<style>
    .container {
        max-width: 1200px;
        margin: 0 auto;
        background: white;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    h1 {
        color: #333;
        border-bottom: 3px solid #667eea;
        padding-bottom: 10px;
    }
    h2 {
        color: #667eea;
        margin-top: 30px;
        font-size: 1.3em;
    }
    .back-link {
        display: inline-block;
        background: #667eea;
        color: white;
        padding: 10px 20px;
        border-radius: 6px;
        text-decoration: none;
        margin-bottom: 20px;
    }
    .back-link:hover {
        background: #5568d3;
    }
    .letter-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(80px, 1fr));
        gap: 10px;
        margin: 20px 0;
    }
    .letter-btn {
        padding: 12px;
        text-align: center;
        background: #f0f0f0;
        border: 2px solid #ddd;
        border-radius: 6px;
        cursor: pointer;
        font-weight: bold;
        font-size: 1.1em;
        transition: all 0.3s;
    }
    .letter-btn:hover {
        background: #667eea;
        color: white;
        border-color: #667eea;
    }
    .letter-btn.active {
        background: #667eea;
        color: white;
        border-color: #667eea;
    }
    .letter-content {
        display: none;
    }
    .letter-content.active {
        display: block;
    }
    .letter-section {
        background: #f9f9f9;
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 20px;
    }
</style>
   <script>
    function showLetter(letter) {
        document.querySelectorAll('.letter-content').forEach(el => {
            el.classList.remove('active');
        });
        document.querySelectorAll('.letter-btn').forEach(el => {
            el.classList.remove('active');
        });
        
        const content = document.getElementById('letter-' + letter);
        if (content) {
            content.classList.add('active');
        }
        
        event.target.classList.add('active');
    }
    
    function toggleTirolArchive(boxId, contentId) {
        const content = document.getElementById(contentId);
        const icon = document.getElementById(boxId + '-icon');
        
        if (content.style.display === 'none') {
            content.style.display = 'block';
            icon.style.transform = 'rotate(90deg)';
        } else {
            content.style.display = 'none';
            icon.style.transform = 'rotate(0deg)';
        }
    }
    
    function toggleRecord(element, recordId) {
        const elem = document.getElementById(recordId);
        const icon = element;
        
        if (elem.style.display === 'none') {
            elem.style.display = 'block';
            icon.style.transform = 'rotate(90deg)';
        } else {
            elem.style.display = 'none';
            icon.style.transform = 'rotate(0deg)';
        }
        
        event.stopPropagation();
    }
    
    function toggleNameGroup(element, groupId) {
        const content = document.getElementById(groupId);
        const icon = element.querySelector('.toggle-icon');
        
        if (content.style.display === 'none') {
            content.style.display = 'block';
            icon.style.transform = 'rotate(90deg)';
        } else {
            content.style.display = 'none';
            icon.style.transform = 'rotate(0deg)';
        }
    }
    
    function toggleVersion(element, versionId) {
        const content = document.getElementById(versionId);
        const icon = element.querySelector('.version-icon');
        
        if (content.style.display === 'none') {
            content.style.display = 'block';
            icon.style.transform = 'rotate(90deg)';
        } else {
            content.style.display = 'none';
            icon.style.transform = 'rotate(0deg)';
        }
    }
</script>

<div class="container">
   
    <h1>🔍 Ähnliche Nachnamen im Stammbaum</h1>
    <br/>
    <p style="color:#666; margin-bottom:20px;">
        Diese Seite zeigt Gruppen ähnlicher Familiennamen und vergleicht sie mit dem 
        Tirol-Archiv Familiennamen-Verzeichnis.
        <br>
        <strong>Wählen Sie einen Anfangsbuchstaben:</strong> Die Nachnamen werden nach Gruppe und Ähnlichkeit angezeigt.
    </p>
    
    <!-- BUCHSTABEN-FILTER -->
    <h2>📝 Nachnamen nach Anfangsbuchstabe</h2>
    
    <?php
        $nachnamenGroups = getSimilarNachnamen($pdo);
        $groupedByLetter = groupNachamenByFirstLetter($nachnamenGroups);
        
        if (empty($groupedByLetter)) {
            echo '<p style="color:#999;">Keine ähnlichen Nachnamen gefunden.</p>';
        } else {
            echo '<div class="letter-grid">';
            foreach ($groupedByLetter as $letter => $groups) {
                $count = count($groups);
                echo '<button class="letter-btn" onclick="showLetter(\'' . htmlspecialchars($letter, ENT_QUOTES) . '\'); event.preventDefault();">';
                echo htmlspecialchars($letter, ENT_QUOTES) . '<br><small style="font-size:0.8em; font-weight:normal;">(' . intval($count) . ')</small>';
                echo '</button>';
            }
            echo '</div>';
            
            foreach ($groupedByLetter as $letter => $groups) {
                echo '<div id="letter-' . htmlspecialchars($letter, ENT_QUOTES) . '" class="letter-content">';
                echo '<div class="letter-section">';
                echo '<h3 style="color:#667eea; margin-top:0;">Nachnamen mit ' . htmlspecialchars($letter, ENT_QUOTES) . ' (' . count($groups) . ' Gruppen)</h3>';
                
                foreach ($groups as $group) {
                    echo renderNameGroup($group, $pdo);
                }
                
                echo '</div>';
                echo '</div>';
            }
        }
    ?>
    
    <br>
</div>

<?php require_once dirname(__DIR__, 2) . '/layout/footer.php'; ?>