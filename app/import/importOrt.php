<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

// Prüfe ob getPDO bereits definiert ist, um doppelte Definitionen zu vermeiden
if (!function_exists('getPDO')) {
    include dirname(__DIR__) . '/lib/include.php';
}
$traubuch = $_POST['traubuch'];
$uploadedFile = $_FILES['daten_import_file'];

if (!$traubuch || !isset($_FILES['daten_import_file']) || $_FILES['daten_import_file']['error'] !== UPLOAD_ERR_OK) {
    echo "Fehler beim Upload";
    return;
}

$pdo = getPDO();

// =========================
// DATEI LADEN
// =========================
$lines = file($_FILES['daten_import_file']['tmp_name'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

// =========================
// HELFER
// =========================

function parseDate($text) {
    if (preg_match('/\d{2}\.\d{2}\.\d{4}/', $text, $m)) {
        return date("Y-m-d", strtotime(str_replace('.', '-', $m[0])));
    }
    return null;
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
    ];
    
    if (preg_match('/(Witwer|Witwe)\s+(nach|von)\s+([^,]+)(?:,\s*(\d{2}\.\d{2}\.\d{4}))?/i', $text, $m)) {
        
        $result['bemerkung'] = trim($m[0]);
        
        if (!empty($m[4])) {
            $result['sterbedatum_partner'] = date("Y-m-d", strtotime(str_replace('.', '-', $m[4])));
        }
        
        // Entfernen aus Text
        $text = str_replace($m[0], '', $text);
    }
    
    return $result;
}

function extractBemerkung(&$text) {
    $bemerkung = [];
    
    // unehelich
    if (preg_match('/\((unehelich von [^)]+)\)/i', $text, $m)) {
        $bemerkung[] = trim($m[1]);
        $text = str_replace($m[0], '', $text);
    }
    
    // Sxxx
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

function createPersonFromName($pdo, $name) {
    $parts = preg_split('/\s+/', $name);
    $nachname = array_pop($parts);
    $vorname = implode(' ', $parts);
    
    $stmt = $pdo->prepare("SELECT id FROM person WHERE vorname=? AND nachname=?");
    $stmt->execute([$vorname, $nachname]);
    $id = $stmt->fetchColumn();
    
    if (!$id) {
        $pdo->prepare("INSERT INTO person (vorname, nachname) VALUES (?, ?)")
        ->execute([$vorname, $nachname]);
        $id = $pdo->lastInsertId();
    }
    
    return $id;
}

function parsePerson($text) {
    
    $referenzEhe = extractSId($text);
    
    preg_match('/\b(\d+)\s*[jJ]\b/', $text, $m);
    $alter = $m[1] ?? null;
    
    $witweData = extractWitweWitwer($text);
    $bemerkung = extractBemerkung($text);
    
    if ($witweData['bemerkung']) {
        $bemerkung .= ($bemerkung ? '; ' : '') . $witweData['bemerkung'];
    }
    
    list($tod, $todOrt) = extractDateAndPlace($text, 'gest');
    list($geb, $gebOrt) = extractDateAndPlace($text, 'geb');
    
    // Klammern entfernen
    $text = preg_replace('/\([^)]*\)/', '', $text);
    
    // Alter entfernen
    $text = preg_replace('/\b\d+\s*[jJ]\b/', '', $text);
    
    $text = trim($text);
    
    $parts = preg_split('/\s+/', $text);
    $nachname = array_pop($parts);
    $vorname = implode(' ', $parts);
    
    return [
        'vorname' => trim($vorname),
        'nachname' => $nachname,
        'geburtsdatum' => $geb,
        'sterbedatum' => $tod,
        'geburtsort' => $gebOrt,
        'sterbeort' => $todOrt,
        'alter' => $alter,
        'referenz_ehe' => $referenzEhe,
        'bemerkung' => $bemerkung,
        'witwe' => $witweData
    ];
}

function findOrCreatePerson($pdo, $data) {
    
    $stmt = $pdo->prepare("
        SELECT id FROM person
        WHERE vorname=? AND nachname=?
    ");
    $stmt->execute([$data['vorname'], $data['nachname']]);
    
    $id = $stmt->fetchColumn();
    
    if (!$id) {
        $stmt = $pdo->prepare("
            INSERT INTO person (vorname, nachname, geburtsdatum, sterbedatum, bemerkung)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['vorname'],
            $data['nachname'],
            $data['geburtsdatum'],
            $data['sterbedatum'],
            $data['bemerkung']
        ]);
        $id = $pdo->lastInsertId();
    }
    
    return $id;
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

// =========================
// IMPORT
// =========================

foreach ($lines as $line) {
    
    $line = trim($line);
    if ($line === '') continue;
    
    preg_match('/^\d{2}\.\d{2}\.\d{4}/', $line, $m);
    $heiratsdatum = isset($m[0]) ? parseDate($m[0]) : null;
    
    $line = preg_replace('/^\d{2}\.\d{2}\.\d{4}\s*/', '', $line);
    
    list($mannText, $frauText) = splitOutsideBrackets($line);
    if (!$mannText || !$frauText) continue;
    
    $mann = parsePerson($mannText);
    $frau = parsePerson($frauText);
    
    $mannId = findOrCreatePerson($pdo, $mann);
    $frauId = findOrCreatePerson($pdo, $frau);
    
    // Ehe suchen
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
    
    // Witwer/Witwe -> Partner aktualisieren
    if (!empty($mann['witwe']['sterbedatum_partner'])) {
        updateSpouseDeathByEhe($pdo, $mannId, $mann['witwe']['sterbedatum_partner']);
    }
    
    if (!empty($frau['witwe']['sterbedatum_partner'])) {
        updateSpouseDeathByEhe($pdo, $frauId, $frau['witwe']['sterbedatum_partner']);
    }
}

echo "<h2>Import erfolgreich</h2>";

?>