<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

if (!function_exists('getPDO')) {
    include 'include.php';
}

// ===========================
// HELPER FUNKTIONEN
// ===========================

function parseDate($text) {
    if (!$text) return null;
    try {
        // Handle flexible date formats with xx or 00 for unknown day/month
        if (preg_match('/^(0[1-9]|[12]\d|3[01]|xx|00)\.(0[1-9]|1[0-2]|xx|00)\.(\d{4})$/', $text, $m)) {
            $day   = ($m[1] === '00') ? 'xx' : $m[1];
            $month = ($m[2] === '00') ? 'xx' : $m[2];
            $year  = $m[3];
            return "$year-$month-$day";
        }
        $timestamp = strtotime(str_replace('.', '-', $text));
        return ($timestamp !== false) ? date("Y-m-d", $timestamp) : null;
    } catch (Exception $e) {
        return null;
    }
}

function extractSId(&$text) {
    if (preg_match('/\b(S\d+)\b/', $text, $m)) {
        $text = str_replace($m[1], '', $text);
        return $m[1];
    }
    return null;
}

function extractWitweWitwer(&$text) {
    $result = [
        'bemerkung' => null,
        'sterbedatum_partner' => null,
        'uneheliche_mutter' => null,
    ];
    
    // Check for pattern: (Name, Witwer/Witwe von ...)
    // This catches cases like: (Maria Gwiggner, Witwe von Johann Hörbiger)
    if (preg_match('/\(([^,]+),\s*(Witwer|Witwe)\s+(von)\s+([^)]+)\)/i', $text, $m)) {
        $potentialMother = trim($m[1]);
        $result['uneheliche_mutter'] = $potentialMother;
        $result['bemerkung'] = trim($m[2] . ' ' . $m[3] . ' ' . $m[4]);
        
        // Extract date if present
        if (preg_match('/(\d{2}\.\d{2}\.\d{4})/', $m[4], $dateMatch)) {
            $result['sterbedatum_partner'] = parseDate($dateMatch[1]);
        }
        
        $text = str_replace($m[0], '', $text);
    }
    // Original pattern for regular Witwer/Witwe
    elseif (preg_match('/(Witwer|Witwe)\s+(nach|von)\s+([^,)]+)(?:,\s*(\d{2}\.\d{2}\.\d{4}))?/i', $text, $m)) {
        $result['bemerkung'] = trim($m[0]);
        
        if (!empty($m[4])) {
            $result['sterbedatum_partner'] = parseDate($m[4]);
        }
        
        $text = str_replace($m[0], '', $text);
    }
    
    return $result;
}

function extractBemerkung(&$text) {
    $bemerkung = [];
    
    // Extract other bemerkungen (S-numbers)
    if (preg_match('/\b(S\d+)\b/', $text, $m)) {
        $bemerkung[] = $m[1];
        $text = str_replace($m[1], '', $text);
    }
    
    return implode('; ', $bemerkung);
}

function extractDateAndPlace(&$text, $type) {
    $datum = null;
    $ort = null;
    
    if ($type === 'geb') {
        if (preg_match('/geb\.\s*(\d{2}\.\d{2}\.\d{4})/', $text, $m)) {
            $datum = parseDate($m[1]);
            $text = preg_replace('/geb\.\s*\d{2}\.\d{2}\.\d{4}.*/', '', $text);
        }
    }
    
    if ($type === 'gest') {
        if (preg_match('/gest\.\s*(\d{2}\.\d{2}\.\d{4})/', $text, $m)) {
            $datum = parseDate($m[1]);
            $text = preg_replace('/gest\.\s*\d{2}\.\d{2}\.\d{4}.*/', '', $text);
        }
    }
    
    return [$datum, $ort];
}

/**
 * Extrahiert Eltern-Information und verarbeitet auch verschachtelte Fälle
 * Pattern 1: (Vater & Mutter) - normale Eltern
 * Pattern 2: (Name, Tochter/Sohn von Vater & Mutter) - Person mit Großeltern
 */
function extractParents(&$text) {
    $vater = null;
    $mutter = null;
    $nested_vater = null;
    $nested_mutter = null;
    
    // Pattern: (Name, Tochter/Sohn von Vater & Mutter)
    // This extracts: Name with nested grandparents
    if (preg_match('/\(([^,)]+?),\s*(?:Tochter|Sohn)\s+von\s+([^&)]+)\s*&\s*([^)]+)\)/i', $text, $m)) {
        // This is a person with nested grandparents
        $name_with_relation = trim($m[1]);
        $nested_vater = trim($m[2]);
        $nested_mutter = trim($m[3]);
        
        $text = str_replace($m[0], '', $text);
        
        return [$name_with_relation, null, $nested_vater, $nested_mutter];
    }
    // Pattern: (Vater & Mutter) - direct parents
    elseif (preg_match('/\(([^&)]+)\s*&\s*([^)]+)\)/i', $text, $m)) {
        $vater_text = trim($m[1]);
        $mutter_text = trim($m[2]);
        
        $vater = $vater_text ? $vater_text : null;
        $mutter = $mutter_text ? $mutter_text : null;
        
        $text = str_replace($m[0], '', $text);
        
        return [$vater, $mutter, null, null];
    }
    // Pattern: (Single parent info)
    elseif (preg_match('/\(([^)]+)\)/i', $text, $m)) {
        $parent_text = trim($m[1]);
        if ($parent_text && !preg_match('/^(unehelich|S\d+|Witwer|Witwe)/i', $parent_text)) {
            $vater = $parent_text;
        }
        $text = str_replace($m[0], '', $text);
        
        return [$vater, $mutter, null, null];
    }
    
    return [$vater, $mutter, $nested_vater, $nested_mutter];
}

/**
 * Extrahiert uneheliche Informationen
 * Pattern: (uneheliche(r) Tochter/Sohn von Name[, Vater & Mutter])
 */
function extractUnehelich(&$text) {
    $result = [
        'bemerkung' => null,
        'mutter_text' => null,
        'nested_vater_text' => null,
        'nested_mutter_text' => null,
    ];
    
    // Match: (uneheliche(r) Tochter/Sohn von Name[, VaterName & MutterName])
    if (preg_match('/\(unehelich(?:e|er)?\s+(?:Tochter|Sohn)\s+von\s+([^,)]+)(?:,\s*([^&)]+)\s*&\s*([^)]+))?\)/i', $text, $m)) {
        $mutterName      = trim($m[1]);
        $vaterDerMutter  = trim($m[2] ?? '') ?: null;
        $mutterDerMutter = trim($m[3] ?? '') ?: null;
        
        $result['bemerkung'] = trim(substr($m[0], 1, -1));
        $result['nested_vater_text'] = $vaterDerMutter;
        $result['nested_mutter_text'] = $mutterDerMutter;
        $result['mutter_text'] = $mutterName;
        
        $text = str_replace($m[0], '', $text);
    }
    
    return $result;
}

/**
 * Bereinigt den Namen von "unehelich" Markierungen
 * Entfernt: "unehelich, " oder ", unehelich"
 */
function cleanNameFromUneligitimate($name) {
    $name = trim($name);
    
    // Remove "unehelich, " at the start
    $name = preg_replace('/^unehelich,\s*/i', '', $name);
    
    // Remove ", unehelich" anywhere (not just at the end)
    $name = preg_replace('/,\s*unehelich\s*/i', '', $name);
    
    // Remove all remaining commas
    $name = str_replace(',', '', $name);
    
    return trim($name);
}

function splitOutsideBrackets($text) {
    $depth = 0;
    for ($i = 0; $i < strlen($text); $i++) {
        if ($text[$i] === '(') $depth++;
        if ($text[$i] === ')') $depth--;
        if ($text[$i] === '&' && $depth === 0) {
            return [
                trim(substr($text, 0, $i)),
                trim(substr($text, $i + 1))
            ];
        }
    }
    return [null, null];
}

function parsePerson($text) {
    
    $referenzEhe = extractSId($text);
    
    preg_match('/\b(\d+)\s*[jJ]\b/', $text, $m);
    $alter = $m[1] ?? null;
    
    // Extract Witwer/Witwe information first
    $witweData = extractWitweWitwer($text);
    
    // Extract other bemerkungen (S-numbers)
    $otherBemerkung = extractBemerkung($text);
    
    // Combine bemerkungen
    $bemerkung = [];
    if ($witweData['bemerkung']) {
        $bemerkung[] = $witweData['bemerkung'];
    }
    if ($otherBemerkung) {
        $bemerkung[] = $otherBemerkung;
    }
    $bemerkung = implode('; ', $bemerkung);
    
    // Extract dates
    list($tod, $todOrt) = extractDateAndPlace($text, 'gest');
    list($geb, $gebOrt) = extractDateAndPlace($text, 'geb');
    
    // Extract unehelich information
    $unehelichData = extractUnehelich($text);
    if ($unehelichData['bemerkung']) {
        $bemerkung .= ($bemerkung ? '; ' : '') . $unehelichData['bemerkung'];
    }
    
    // Extract parents (now returns 4 values for nested parents)
    list($vater_text, $mutter_text, $nested_vater_text, $nested_mutter_text) = extractParents($text);
    
    // Priority: If we have an uneheliche mother from the Witwe/Witwer combined pattern, use it
    if ($witweData['uneheliche_mutter']) {
        $mutter_text = $witweData['uneheliche_mutter'];
    }
    // Otherwise, use mother from unehelich extraction
    elseif ($unehelichData['mutter_text']) {
        $mutter_text = $unehelichData['mutter_text'];
        // If the unehelich mother also has grandparents, store them
        if ($unehelichData['nested_vater_text'] || $unehelichData['nested_mutter_text']) {
            $nested_vater_text = $unehelichData['nested_vater_text'];
            $nested_mutter_text = $unehelichData['nested_mutter_text'];
        }
    }
    
    // Remove all brackets before parsing name
    $text = preg_replace('/\([^)]*\)/', '', $text);
    $text = preg_replace('/\b\d+\s*[jJ]\b/', '', $text);
    $text = preg_replace('/,?\s*(Hof|Ort):\s*[^,]*/i', '', $text);
    
    // IMPORTANT: Clean "unehelich" BEFORE splitting vorname/nachname
    $text = cleanNameFromUneligitimate($text);
    
    $text = trim($text);
    
    $parts = preg_split('/\s+/', $text);
    $nachname = array_pop($parts);
    $vorname = implode(' ', $parts);
    
    return [
        'vorname' => trim($vorname),
        'nachname' => trim($nachname),
        'geburtsdatum' => $geb,
        'sterbedatum' => $tod,
        'geburtsort' => $gebOrt,
        'sterbeort' => $todOrt,
        'alter' => $alter,
        'referenz_ehe' => $referenzEhe,
        'bemerkung' => $bemerkung,
        'vater_text' => $vater_text,
        'mutter_text' => $mutter_text,
        'nested_vater_text' => $nested_vater_text,
        'nested_mutter_text' => $nested_mutter_text,
    ];
}

function findOrCreatePerson($pdo, $data, $vaterId = null, $mutterId = null) {
    
    $stmt = $pdo->prepare("
        SELECT id FROM person
        WHERE vorname=? AND nachname=?
        AND (vater_id <=> ?)
        AND (mutter_id <=> ?)
    ");
    $stmt->execute([$data['vorname'], $data['nachname'], $vaterId, $mutterId]);
    
    $id = $stmt->fetchColumn();
    
    if (!$id) {
        $stmt = $pdo->prepare("
            INSERT INTO person (vorname, nachname, geburtsdatum, sterbedatum, bemerkung, vater_id, mutter_id)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['vorname'],
            $data['nachname'],
            $data['geburtsdatum'],
            $data['sterbedatum'],
            $data['bemerkung'],
            $vaterId,
            $mutterId
        ]);
        $id = $pdo->lastInsertId();
    }
    
    return $id;
}

function findOrCreatePersonFromText($pdo, $personText) {
    if (!$personText) return null;
    
    $personData = parsePerson($personText);
    
    $vaterId = null;
    $mutterId = null;
    
    if ($personData['vater_text']) {
        $vaterId = findOrCreatePersonFromText($pdo, $personData['vater_text']);
    }
    
    if ($personData['mutter_text']) {
        // If the mother also has nested parents (grandparents), process them first
        if ($personData['nested_vater_text'] || $personData['nested_mutter_text']) {
            $nested_vater_id = null;
            $nested_mutter_id = null;
            
            if ($personData['nested_vater_text']) {
                $nested_vater_id = findOrCreatePersonFromText($pdo, $personData['nested_vater_text']);
            }
            if ($personData['nested_mutter_text']) {
                $nested_mutter_id = findOrCreatePersonFromText($pdo, $personData['nested_mutter_text']);
            }
            
            // Now create the mother with the grandparents
            $mutterPersonData = parsePerson($personData['mutter_text']);
            $mutterId = findOrCreatePerson($pdo, $mutterPersonData, $nested_vater_id, $nested_mutter_id);
        } else {
            $mutterId = findOrCreatePersonFromText($pdo, $personData['mutter_text']);
        }
    }
    
    return findOrCreatePerson($pdo, $personData, $vaterId, $mutterId);
}

function updateSpouseDeathByEhe($pdo, $personId, $sterbedatum) {
    
    if (!$sterbedatum || !$personId) return;
    
    $stmt = $pdo->prepare("
        SELECT vater_id, mutter_id
        FROM ehe
        WHERE vater_id = ? OR mutter_id = ?
        LIMIT 1
    ");
    $stmt->execute([$personId, $personId]);
    $ehe = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$ehe) return;
    
    $partnerId = ($ehe['vater_id'] == $personId)
    ? $ehe['mutter_id']
    : $ehe['vater_id'];
    
    if ($partnerId) {
        $pdo->prepare("
            UPDATE person
            SET sterbedatum = ?
            WHERE id = ?
        ")->execute([$sterbedatum, $partnerId]);
    }
}

function importFile($pdo, $filePath, $traubuch) {
    
    if (!file_exists($filePath)) {
        return ['error' => "Datei nicht gefunden: $filePath"];
    }
    
    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    $imported = 0;
    $errors = 0;
    
    foreach ($lines as $line) {
        
        $line = trim($line);
        if ($line === '') continue;
        
        try {
            preg_match('/^\d{2}\.\d{2}\.\d{4}/', $line, $m);
            $heiratsdatum = isset($m[0]) ? parseDate($m[0]) : null;
            
            $line = preg_replace('/^\d{2}\.\d{2}\.\d{4}\s*/', '', $line);
            
            list($mannText, $frauText) = splitOutsideBrackets($line);
            if (!$mannText || !$frauText) continue;
            
            $mannId = findOrCreatePersonFromText($pdo, $mannText);
            $frauId = findOrCreatePersonFromText($pdo, $frauText);
            
            if (!$mannId || !$frauId) continue;
            
            $stmt = $pdo->prepare("
                SELECT id FROM ehe
                WHERE (vater_id = ? AND mutter_id = ?)
                   OR (vater_id = ? AND mutter_id = ?)
            ");
            $stmt->execute([$mannId, $frauId, $frauId, $mannId]);
            $eheId = $stmt->fetchColumn();
            
            if (!$eheId) {
                $pdo->prepare("
                    INSERT INTO ehe (vater_id, mutter_id, heiratsdatum, traubuch)
                    VALUES (?, ?, ?, ?)
                ")->execute([$mannId, $frauId, $heiratsdatum, $traubuch]);
                
                $eheId = $pdo->lastInsertId();
            }
            
            $imported++;
            
        } catch (Exception $e) {
            $errors++;
        }
    }
    
    return [
        'file' => basename($filePath),
        'traubuch' => $traubuch,
        'imported' => $imported,
        'errors' => $errors
    ];
}

function runOrteImport() {
    global $pdo;
    
    // stammbaum-daten directory is a sibling of the project root
    $dataDir = dirname(dirname(dirname(__DIR__))) . '/stammbaum-daten/';
    $results = [];
    $totalImported = 0;
    $totalErrors = 0;
    
    if (!is_dir($dataDir)) {
        echo "❌ Verzeichnis nicht gefunden: $dataDir";
        return;
    }
    
    $files = glob($dataDir . "*.txt");
    
    if (empty($files)) {
        echo "❌ Keine .txt Dateien im Verzeichnis gefunden";
        return;
    }
    
    echo "<h2>🔄 Importiere alle Orte...</h2>";
    echo "<div style='background:#f5f5f5; padding:15px; border-radius:8px;'>";
    
    foreach ($files as $filePath) {
        
        $filename = basename($filePath);
        
        // Thierbach-komplett.txt ausschließen
        if (stripos($filename, 'thierbach-komplett') !== false) {
            echo "<div style='color:#999;'>⏭️ <strong>$filename</strong> - übersprungen</div>";
            continue;
        }
        
        // Traubuch-Name extrahieren (erste Wort vor - oder _)
        preg_match('/^([^-_]+)/', $filename, $m);
        $traubuch = ucfirst(strtolower($m[1] ?? 'Unbekannt'));
        
        echo "<div style='margin:10px 0; padding:10px; background:white; border-left:4px solid #667eea;'>";
        echo "📄 <strong>$filename</strong><br>";
        
        $result = importFile($pdo, $filePath, $traubuch);
        
        if (isset($result['error'])) {
            echo "❌ Fehler: " . htmlspecialchars($result['error']) . "<br>";
        } else {
            echo "✅ Ort: <strong>" . htmlspecialchars($result['traubuch']) . "</strong><br>";
            echo "📊 Importiert: <span style='color:green;'>" . $result['imported'] . " Einträge</span>";
            
            if ($result['errors'] > 0) {
                echo ", <span style='color:red;'>" . $result['errors'] . " Fehler</span>";
            }
            
            $totalImported += $result['imported'];
            $totalErrors += $result['errors'];
        }
        
        echo "</div>";
    }
    
    echo "</div>";
    
    echo "<hr>";
    echo "<h3>📈 Zusammenfassung</h3>";
    echo "<ul>";
    echo "<li>✅ Insgesamt importiert: <strong style='color:green;'>$totalImported</strong> Einträge</li>";
    if ($totalErrors > 0) {
        echo "<li>❌ Fehler: <strong style='color:red;'>$totalErrors</strong></li>";
    }
    echo "<li>📁 Verarbeitete Dateien: " . count($files) . "</li>";
    echo "</ul>";
    
    echo "<hr><br />";
    echo "<a href='../../views/user/index.php' style='background:#667eea; color:white; padding:10px 20px; border-radius:6px; text-decoration:none;'>← Zurück zur Startseite</a>";
}

/* =========================
 HAUPTLOGIK
 ========================= */

// Wenn direkt aufgerufen (nicht über re-create-all.php)
if (!isset($SKIP_AUTO_IMPORT)) {
    $pdo = getPDO();
    runOrteImport();
}