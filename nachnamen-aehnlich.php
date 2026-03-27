<?php

include 'include.php';
include 'tirol-archiv-helper.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

$pdo = getPDO();

// ===========================
// HELPER FUNKTIONEN
// ===========================

function levenshteinSimilarity($str1, $str2) {
    $distance = levenshtein(strtolower($str1), strtolower($str2));
    $maxLen = max(strlen($str1), strlen($str2));
    if ($maxLen == 0) return 100;
    return round((1 - ($distance / $maxLen)) * 100);
}

// Get all unique last names (both genders)
function getSimilarNachnamen($pdo) {
    $stmt = $pdo->prepare("
        SELECT DISTINCT nachname
        FROM person
        WHERE nachname IS NOT NULL AND nachname != ''
        ORDER BY nachname
    ");
    $stmt->execute();
    $nachnamen = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
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

// Gruppiere Nachnamen nach Anfangsbuchstaben
function groupNachamenByFirstLetter($groups) {
    $grouped = [];
    
    foreach ($groups as $group) {
        $firstLetter = strtoupper(substr($group[0], 0, 1));
        if (!isset($grouped[$firstLetter])) {
            $grouped[$firstLetter] = [];
        }
        $grouped[$firstLetter][] = $group;
    }
    
    ksort($grouped);
    return $grouped;
}

// Get all records for a specific nachname
function getRecordsForNachname($pdo, $nachname) {
    $sql = "
        SELECT
            p.id,
            p.vorname,
            p.nachname,
            p.geburtsdatum,
            p.sterbedatum,
            p.geburtsort,
            p.sterbeort,
            p.hof,
            p.ort,
            p.bemerkung,
            e.traubuch,
            e.heiratsdatum,
            vater.vorname as vater_vorname,
            vater.nachname as vater_nachname,
            mutter.vorname as mutter_vorname,
            mutter.nachname as mutter_nachname,
            kinder.id as kind_id,
            kinder.vorname as kind_vorname,
            kinder.nachname as kind_nachname,
            kinder.geburtsdatum as kind_geburtsdatum,
            kinder.sterbedatum as kind_sterbedatum
        FROM person p
        JOIN ehe e ON (p.id = e.vater_id OR p.id = e.mutter_id)
        LEFT JOIN person vater ON e.vater_id = vater.id
        LEFT JOIN person mutter ON e.mutter_id = mutter.id
        LEFT JOIN person kinder ON (e.id = kinder.referenz_ehe_id OR (kinder.vater_id = e.vater_id AND kinder.mutter_id = e.mutter_id))
        WHERE p.nachname = ?
        ORDER BY e.traubuch, p.geburtsdatum
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$nachname]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function formatDate($date) {
    if (!$date) return '—';
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d ? $d->format('d.m.Y') : '—';
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
    
    $html .= '<span style="color:#0066cc; font-size:1.1em; font-weight:bold;">' . htmlspecialchars($record['vorname'] . ' ' . $record['nachname']) . '</span>';
    
    if ($record['vater_vorname'] || $record['mutter_vorname']) {
        $html .= '<span style="color:#666; font-size:0.85em; margin-left:10px;">';
        if ($record['vater_vorname']) {
            $html .= htmlspecialchars($record['vater_vorname'] . ' ' . $record['vater_nachname']);
        }
        if ($record['mutter_vorname']) {
            if ($record['vater_vorname']) $html .= ' & ';
            $html .= htmlspecialchars($record['mutter_vorname'] . ' ' . $record['mutter_nachname']);
        }
        $html .= '</span>';
    }
    
    $html .= '</div>';
    
    if ($record['traubuch']) {
        $traubuchClean = formatTraubuch($record['traubuch']);
        $html .= '<span style="background:#fff3cd; color:#856404; padding:4px 8px; border-radius:3px; font-size:0.85em; display:inline-block; white-space:nowrap;">';
        $html .= '📖 ' . htmlspecialchars($traubuchClean);
        $html .= '</span>';
    }
    
    $html .= '<span class="toggle-icon" style="color:#0066cc; font-size:1.2em; font-weight:bold; transition:transform 0.3s; cursor:pointer; user-select:none;" onclick="toggleRecord(this, \'' . $recordId . '\'); event.stopPropagation();">▶</span>';
    
    $html .= '</div>';
    
    $html .= '<div id="record-' . $recordId . '" style="display:none; margin-top:12px; margin-left:12px; padding:12px; background:#f9f9f9; border-radius:3px; border-left:4px solid #0066cc;">';
    
    if ($record['geburtsdatum'] || $record['sterbedatum']) {
        $html .= '<span style="color:#555; font-size:0.9em; display:block; margin-bottom:8px;">';
        $html .= '<strong>Lebensdaten:</strong> ';
        if ($record['geburtsdatum']) {
            $html .= 'geb. ' . formatDate($record['geburtsdatum']);
            if ($record['geburtsort']) {
                $html .= ' in ' . htmlspecialchars($record['geburtsort']);
            }
        }
        if ($record['sterbedatum']) {
            if ($record['geburtsdatum']) $html .= ' | ';
            $html .= 'gest. ' . formatDate($record['sterbedatum']);
            if ($record['sterbeort']) {
                $html .= ' in ' . htmlspecialchars($record['sterbeort']);
            }
        }
        $html .= '</span>';
    }
    
    if ($record['hof'] || $record['ort'] || $record['bemerkung']) {
        $html .= '<span style="color:#888; font-size:0.85em; display:block;">';
        if ($record['hof']) {
            $html .= '<strong>Hof:</strong> ' . htmlspecialchars($record['hof']) . '<br>';
        }
        if ($record['ort']) {
            $html .= '<strong>Ort:</strong> ' . htmlspecialchars($record['ort']) . '<br>';
        }
        if ($record['bemerkung']) {
            $html .= '<strong>Bem.:</strong> ' . htmlspecialchars($record['bemerkung']) . '<br>';
        }
        $html .= '</span>';
    }
    
    if ($record['heiratsdatum']) {
        $html .= '<span style="color:#888; font-size:0.85em; display:block;">';
        $html .= '<strong>Heirat:</strong> ' . formatDate($record['heiratsdatum']);
        $html .= '</span>';
    }
    
    $html .= '</div>';
    
    return $html;
}

function renderNameGroup($groupNames, $pdo) {
    $groupId = 'group-nachname-' . md5(implode('-', $groupNames));
    
    $html = '<div class="name-group" style="background:#f9f9f9; padding:12px; margin:10px 0; border-left:4px solid #666; border-radius:3px;">';
    
    $html .= '<div style="display:flex; align-items:center; justify-content:space-between; cursor:pointer;" onclick="toggleNameGroup(this, \'' . $groupId . '\');">';
    $html .= '<div>';
    $html .= '<strong style="color:#333; user-select:none;">' . htmlspecialchars(implode(', ', $groupNames)) . '</strong>';
    $html .= '</div>';
    $html .= '<span class="toggle-icon" style="color:#666; font-size:1.2em; transition:transform 0.3s; user-select:none;">▶</span>';
    $html .= '</div>';
    
    // Verwende die Helper-Funktion aus tirol-archiv-helper.php
    $html .= renderArchiveNamesBox($groupNames[0]);
    
    $html .= '<div id="' . $groupId . '" class="group-content" style="display:none; margin-top:12px; padding-top:12px; border-top:1px solid #ddd;">';
    
    foreach ($groupNames as $name) {
        $records = getRecordsForNachname($pdo, $name);
        
        if (!empty($records)) {
            $versionId = 'version-nachname-' . md5($name);
            
            $html .= '<div style="margin-top:12px; padding:10px; background:#ffffff; border:1px solid #ddd; border-radius:3px;">';
            $html .= '<div style="display:flex; align-items:center; justify-content:space-between; cursor:pointer;" onclick="toggleVersion(this, \'' . $versionId . '\'); event.stopPropagation();">';
            $html .= '<div>';
            $html .= '<strong style="color:#0066cc; font-size:1em; user-select:none;">' . htmlspecialchars($name) . '</strong>';
            $html .= ' <span style="color:#999; font-size:0.9em; user-select:none;">(' . count($records) . ' Einträge)</span>';
            $html .= '</div>';
            $html .= '<span class="version-icon" style="color:#0066cc; font-size:1.1em; transition:transform 0.3s; display:inline-block; user-select:none;">▶</span>';
            $html .= '</div>';
            
            $html .= '<div id="' . $versionId . '" class="version-content" style="display:none; margin-top:10px; padding-top:10px; border-top:1px solid #eee;">';
            
            $recordCounter = 0;
            foreach ($records as $record) {
                $recordId = 'nachname-' . $name . '-' . ($recordCounter++);
                $html .= renderPersonRecord($record, $recordId);
            }
            
            $html .= '</div>';
            $html .= '</div>';
        }
    }
    
    $html .= '</div>';
    $html .= '</div>';
    
    return $html;
}

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Ähnliche Nachnamen - Stammbaum</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background: #f5f5f5;
        }
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
</head>
<body>
    <div class="container">
        <a href="stammbaum.php" class="back-link">← Zurück zur Startseite</a>
        
        <h1>🔍 Ähnliche Nachnamen im Stammbaum</h1>
        
        <p style="color:#666; margin-bottom:20px;">
            Diese Seite zeigt Gruppen ähnlicher Familiennamen und vergleicht sie mit dem 
            Tirol-Archiv Familiennamen-Verzeichnis. Es werden nur Namen mit mind. 80% Ähnlichkeit angezeigt,
            sowie die Orte, in denen diese Namen vorkommen.
            <br>
            <strong>Wählen Sie einen Anfangsbuchstaben:</strong> Die Nachnamen werden nach Gruppe und Ähnlichkeit angezeigt.
            <br>
            <em style="color:#999;">Namen und Eltern können kopiert werden</em>
        </p>
        
        <!-- BUCHSTABEN-FILTER -->
        <h2>📝 Nachnamen nach Anfangsbuchstabe</h2>
        
        <?php
            $nachnamenGroups = getSimilarNachnamen($pdo);
            $groupedByLetter = groupNachamenByFirstLetter($nachnamenGroups);
            
            echo '<div class="letter-grid">';
            foreach ($groupedByLetter as $letter => $groups) {
                $count = count($groups);
                echo '<button class="letter-btn" onclick="showLetter(\'' . $letter . '\'); event.preventDefault();">';
                echo $letter . '<br><small style="font-size:0.8em; font-weight:normal;">(' . $count . ')</small>';
                echo '</button>';
            }
            echo '</div>';
            
            foreach ($groupedByLetter as $letter => $groups) {
                echo '<div id="letter-' . $letter . '" class="letter-content">';
                echo '<div class="letter-section">';
                echo '<h3 style="color:#667eea; margin-top:0;">Nachnamen mit ' . $letter . ' (' . count($groups) . ' Gruppen)</h3>';
                
                foreach ($groups as $group) {
                    echo renderNameGroup($group, $pdo);
                }
                
                echo '</div>';
                echo '</div>';
            }
        ?>
        
        <br>
        <a href="stammbaum.php" class="back-link">← Zurück zur Startseite</a>
    </div>
</body>
</html>