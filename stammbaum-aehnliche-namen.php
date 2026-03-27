<?php

include 'include.php';
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

// Get all unique MOTHER first names (mutter_id from ehe)
function getSimilarMutterNamen($pdo) {
    $stmt = $pdo->prepare("
        SELECT DISTINCT p.vorname
        FROM person p
        JOIN ehe e ON p.id = e.mutter_id
        WHERE p.vorname IS NOT NULL AND p.vorname != ''
        ORDER BY p.vorname
    ");
    $stmt->execute();
    $vornamen = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $groups = [];
    $processed = [];
    
    foreach ($vornamen as $name) {
        if (in_array($name, $processed)) continue;
        
        $group = [$name];
        foreach ($vornamen as $compareName) {
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

// Get all unique FATHER first names (vater_id from ehe)
function getSimilarVaterNamen($pdo) {
    $stmt = $pdo->prepare("
        SELECT DISTINCT p.vorname
        FROM person p
        JOIN ehe e ON p.id = e.vater_id
        WHERE p.vorname IS NOT NULL AND p.vorname != ''
        ORDER BY p.vorname
    ");
    $stmt->execute();
    $vornamen = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $groups = [];
    $processed = [];
    
    foreach ($vornamen as $name) {
        if (in_array($name, $processed)) continue;
        
        $group = [$name];
        foreach ($vornamen as $compareName) {
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

// Get all records for a specific mother name
function getRecordsForMutterName($pdo, $vorname) {
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
        JOIN ehe e ON p.id = e.mutter_id
        LEFT JOIN person vater ON e.vater_id = vater.id
        LEFT JOIN person mutter ON e.mutter_id = mutter.id
        LEFT JOIN person kinder ON (e.id = kinder.referenz_ehe_id OR (kinder.vater_id = e.vater_id AND kinder.mutter_id = e.mutter_id))
        WHERE p.vorname = ?
        ORDER BY e.traubuch, p.nachname, p.geburtsdatum
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$vorname]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get all records for a specific father name
function getRecordsForVaterName($pdo, $vorname) {
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
        JOIN ehe e ON p.id = e.vater_id
        LEFT JOIN person vater ON e.vater_id = vater.id
        LEFT JOIN person mutter ON e.mutter_id = mutter.id
        LEFT JOIN person kinder ON (e.id = kinder.referenz_ehe_id OR (kinder.vater_id = e.vater_id AND kinder.mutter_id = e.mutter_id))
        WHERE p.vorname = ?
        ORDER BY e.traubuch, p.nachname, p.geburtsdatum
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$vorname]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
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

function renderPersonRecord($record, $recordId) {
    $html = '<div style="background:#f0f8ff; padding:12px; margin:8px 0; border-left:4px solid #0066cc; border-radius:3px; cursor:pointer;" class="toggle-record" onclick="toggleRecord(\'record-' . $recordId . '\');">';
    
    // Header mit Namen und Eltern
    $html .= '<div style="display:flex; align-items:center; justify-content:space-between;">';
    $html .= '<div>';
    
    // Name (außen, größer)
    $html .= '<span style="color:#0066cc; font-size:1.1em; font-weight:bold;">' . htmlspecialchars($record['vorname'] . ' ' . $record['nachname']) . '</span>';
    
    // Eltern direkt danach (innen, kleiner)
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
    
    // Toggle Icon
    $html .= '<span style="color:#0066cc; font-size:1.2em; font-weight:bold;">▶</span>';
    $html .= '</div>';
    
    // Versteckte Details
    $html .= '<div id="record-' . $recordId . '" style="display:none; margin-top:12px; padding-top:12px; border-top:1px solid #ccc;">';
    
    // Daten
    $html .= '<span style="color:#555; font-size:0.9em; display:block; margin-bottom:8px;">';
    if ($record['geburtsdatum'] || $record['sterbedatum']) {
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
        $html .= '<br>';
    }
    $html .= '</span>';
    
    // Zusatzinfos
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
    
    // Traubuch
    if ($record['traubuch']) {
        $html .= '<span style="background:#fff3cd; color:#856404; padding:4px 8px; border-radius:3px; font-size:0.85em; display:inline-block; margin-top:6px;">';
        $html .= '📖 ' . htmlspecialchars($record['traubuch']);
        if ($record['heiratsdatum']) {
            $html .= ' (' . formatDate($record['heiratsdatum']) . ')';
        }
        $html .= '</span>';
    }
    
    $html .= '</div>';
    $html .= '</div>';
    
    return $html;
}

function renderNameGroup($groupNames, $groupType) {
    $html = '<div class="name-group" style="cursor:pointer;" onclick="toggleGroup(this);">';
    
    // Header mit Namen und Toggle-Icon
    $html .= '<div style="display:flex; align-items:center; justify-content:space-between; padding:10px 12px; background-color:#f8f9fa; border-radius:4px;">';
    $html .= '<div>';
    $html .= '<strong style="color:#155724; font-size:1.1em;">';
    
    if ($groupType === 'mutter') {
        $html .= '👩 ';
    } elseif ($groupType === 'vater') {
        $html .= '👨 ';
    } else {
        $html .= '📝 ';
    }
    
    $html .= htmlspecialchars(implode(' / ', $groupNames));
    $html .= '</strong>';
    $html .= '</div>';
    $html .= '<span style="color:#155724; font-size:1.2em; font-weight:bold; transition:transform 0.2s;">▶</span>';
    $html .= '</div>';
    
    // Versteckter Inhalt (Records)
    $html .= '<div class="group-content" style="display:none; padding:10px 0;">';
    
    return $html;
}

function closeNameGroup() {
    return '</div></div>';
}

?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ähnliche Namen - Stammbäume Wildschönau</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 20px; 
            background-color: #f9f9f9;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        h1 { 
            color: #333; 
            border-bottom: 3px solid #007bff;
            padding-bottom: 15px;
            margin-bottom: 30px;
        }
        h2 { 
            color: #555;
            margin-top: 40px;
            margin-bottom: 20px;
            background-color: #e8f4f8;
            padding: 12px 15px;
            border-left: 5px solid #007bff;
            border-radius: 4px;
        }
        h3 {
            color: #0066cc;
            margin-top: 20px;
            margin-bottom: 12px;
            font-size: 1.05em;
            border-left: 4px solid #0066cc;
            padding-left: 10px;
        }
        .name-group {
            background-color: white;
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 15px;
            margin: 15px 0;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
            transition: box-shadow 0.3s;
        }
        .name-group:hover {
            box-shadow: 0 4px 10px rgba(0,0,0,0.12);
        }
        .name-variant {
            margin: 10px 0;
            padding: 10px 12px;
            background-color: #f8f9fa;
            border-left: 4px solid #28a745;
            border-radius: 3px;
        }
        .name-variant strong {
            color: #155724;
            font-size: 1.05em;
        }
        .records-list {
            margin: 12px 0 0 0;
            padding: 10px 0;
        }
        .toggle-record {
            transition: background-color 0.2s;
        }
        .toggle-record:hover {
            background-color: #e6f2ff !important;
        }
        .back-link {
            margin-bottom: 30px;
        }
        .back-link a {
            display: inline-block;
            padding: 10px 20px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: background-color 0.3s;
            font-weight: 500;
        }
        .back-link a:hover {
            background-color: #0056b3;
        }
        .no-results {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            border-left: 4px solid #dc3545;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .info-text {
            background-color: #d1ecf1;
            border: 1px solid #bee5eb;
            border-left: 4px solid #0c5460;
            color: #0c5460;
            padding: 12px 15px;
            border-radius: 5px;
            margin-bottom: 25px;
            font-size: 0.95em;
        }
        
        .name-group {
        background-color: white;
        border: 2px solid #28a745;
        border-radius: 6px;
        margin: 15px 0;
        box-shadow: 0 2px 6px rgba(0,0,0,0.08);
        transition: box-shadow 0.3s, border-color 0.3s;
    }
    
    .name-group:hover {
        box-shadow: 0 4px 10px rgba(0,0,0,0.12);
        border-color: #20c997;
    }
    
    .group-content {
        padding: 15px;
        background-color: #fafbfc;
    }
    
    .name-variant {
        margin: 10px 0;
        padding: 10px 12px;
        background-color: #f0f8ff;
        border-left: 4px solid #0066cc;
        border-radius: 3px;
    }
    
    .name-variant strong {
        color: #0066cc;
        font-size: 1em;
    }
    
    .records-list {
        margin: 12px 0 0 0;
        padding: 10px 0;
    }
    </style>
    <script>
        
        
       function toggleRecord(recordId) {
        const element = document.getElementById(recordId);
        const parent = element.parentElement;
        const arrow = parent.querySelector('span:last-child');
        
        if (element.style.display === 'none') {
            element.style.display = 'block';
            arrow.textContent = '▼';
        } else {
            element.style.display = 'none';
            arrow.textContent = '▶';
        }
    }
    
    function toggleGroup(groupHeader) {
        const content = groupHeader.nextElementSibling;
        const arrow = groupHeader.querySelector('span:last-child');
        
        if (content.style.display === 'none') {
            content.style.display = 'block';
            arrow.style.transform = 'rotate(90deg)';
        } else {
            content.style.display = 'none';
            arrow.style.transform = 'rotate(0deg)';
        }
    }
    </script>
</head>
<body>
    <div class="container">
        <div class="back-link">
            <a href="stammbaum.php">← Zurück zur Hauptseite</a>
        </div>
        
        <h1>📋 Ähnliche Namen im Stammbaum</h1>
        <div class="info-text">
            Diese Übersicht zeigt Namen mit ähnlicher Schreibweise (ab 80% Übereinstimmung). 
            Klicken Sie auf einen Namen, um Details zu sehen.
        </div>

      <!-- Mutter Vornamen Section -->
<h2>👩 Ähnliche Vornamen (Mütter)</h2>
<?php
$mutterGroups = getSimilarMutterNamen($pdo);
$hasMutter = false;
$recordCounter = 0;

foreach ($mutterGroups as $group) {
    if (count($group) > 1) {
        $hasMutter = true;
        
        // Öffne die Gruppe
        echo renderNameGroup($group, 'mutter');
        
        foreach ($group as $vorname) {
            $records = getRecordsForMutterName($pdo, $vorname);
            echo "<div class='name-variant'>";
            echo "<strong style='font-size:0.95em;'>" . htmlspecialchars($vorname) . "</strong> (" . count($records) . " Einträge)";
            
            if (count($records) > 0) {
                echo "<div class='records-list'>";
                $displayed = [];
                foreach ($records as $record) {
                    $key = $record['id'];
                    if (!in_array($key, $displayed)) {
                        echo renderPersonRecord($record, 'mutter-' . $recordCounter++);
                        $displayed[] = $key;
                    }
                }
                echo "</div>";
            }
            
            echo "</div>";
        }
        
        // Schließe die Gruppe
        echo closeNameGroup();
    }
}

if (!$hasMutter) {
    echo "<div class='no-results'>❌ Keine ähnlichen Muttervornamen gefunden.</div>";
}
?>
        <!-- Vater Vornamen Section -->
        <h2>👨 Ähnliche Vornamen (Väter)</h2>
        <?php
        $vaterGroups = getSimilarVaterNamen($pdo);
        $hasVater = false;
        
        foreach ($vaterGroups as $group) {
            if (count($group) > 1) {
                $hasVater = true;
                echo "<div class='name-group'>";
                
                foreach ($group as $vorname) {
                    $records = getRecordsForVaterName($pdo, $vorname);
                    echo "<div class='name-variant'>";
                    echo "<strong>👨 " . htmlspecialchars($vorname) . "</strong> (" . count($records) . " Einträge)";
                    
                    if (count($records) > 0) {
                        echo "<div class='records-list'>";
                        $displayed = [];
                        foreach ($records as $record) {
                            $key = $record['id'];
                            if (!in_array($key, $displayed)) {
                                echo renderPersonRecord($record, 'vater-' . $recordCounter++);
                                $displayed[] = $key;
                            }
                        }
                        echo "</div>";
                    }
                    
                    echo "</div>";
                }
                
                echo "</div>";
            }
        }
        
        if (!$hasVater) {
            echo "<div class='no-results'>❌ Keine ähnlichen Vatervornamen gefunden.</div>";
        }
        ?>

        <!-- Nachnamen Section -->
        <h2>📛 Ähnliche Nachnamen</h2>
        <?php
        $nachnamenGroups = getSimilarNachnamen($pdo);
        $hasNachnamen = false;
        
        foreach ($nachnamenGroups as $group) {
            if (count($group) > 1) {
                $hasNachnamen = true;
                echo "<div class='name-group'>";
                
                foreach ($group as $nachname) {
                    $records = getRecordsForNachname($pdo, $nachname);
                    echo "<div class='name-variant'>";
                    echo "<strong>📝 " . htmlspecialchars($nachname) . "</strong> (" . count($records) . " Einträge)";
                    
                    if (count($records) > 0) {
                        echo "<div class='records-list'>";
                        $displayed = [];
                        foreach ($records as $record) {
                            $key = $record['id'];
                            if (!in_array($key, $displayed)) {
                                echo renderPersonRecord($record, 'nachname-' . $recordCounter++);
                                $displayed[] = $key;
                            }
                        }
                        echo "</div>";
                    }
                    
                    echo "</div>";
                }
                
                echo "</div>";
            }
        }
        
        if (!$hasNachnamen) {
            echo "<div class='no-results'>❌ Keine ähnlichen Nachnamen gefunden.</div>";
        }
        ?>

        <div class="back-link" style="margin-top: 40px; text-align: center;">
            <a href="stammbaum.php">← Zurück zur Hauptseite</a>
        </div>
    </div>
</body>
</html>