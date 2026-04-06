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
    
    // Extract all S-number notes and remove them from text.
    if (preg_match_all('/\b(S\d+)\b/', $text, $matches) && !empty($matches[1])) {
        foreach ($matches[1] as $sid) {
            $bemerkung[] = $sid;
        }
        $text = preg_replace('/\bS\d+\b/', '', $text);
    }
    
    $bemerkung = array_values(array_unique($bemerkung));
    return implode('; ', $bemerkung);
}

function extractDateAndPlace(&$text, $type) {
    $datum = null;
    $ort = null;

    $normalizePlace = static function ($value) {
        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }

        // Ortsangaben erscheinen oft als "in <Ort>"; das führende "in" soll nicht gespeichert werden.
        $value = preg_replace('/^in\s+/i', '', $value);
        $value = trim($value, " \t\n\r\0\x0B,.;");

        return $value === '' ? null : $value;
    };
    
    if ($type === 'geb') {
        if (preg_match_all('/\bgeb\.\s*((?:\d{2}|xx|00)\.(?:\d{2}|xx|00)\.\d{4})(?:\s+([^,&(]+?))?\s*(?=,|\(|&|$)/i', $text, $matches, PREG_SET_ORDER) && !empty($matches)) {
            // Prefer the last explicit birth date in the remaining person text.
            $last = end($matches);
            $datum = parseDate($last[1]);
            $ort = isset($last[2]) ? $normalizePlace($last[2]) : null;
            $text = preg_replace('/\bgeb\.\s*(?:\d{2}|xx|00)\.(?:\d{2}|xx|00)\.\d{4}(?:\s+[^,&(]+?)?\s*(?=,|\(|&|$)/i', '', $text);
        }
    }
    
    if ($type === 'gest') {
        if (preg_match_all('/\bgest\.\s*((?:\d{2}|xx|00)\.(?:\d{2}|xx|00)\.\d{4})(?:\s+([^,&(]+?))?\s*(?=,|\(|&|$)/i', $text, $matches, PREG_SET_ORDER) && !empty($matches)) {
            $last = end($matches);
            $datum = parseDate($last[1]);
            $ort = isset($last[2]) ? $normalizePlace($last[2]) : null;
            $text = preg_replace('/\bgest\.\s*(?:\d{2}|xx|00)\.(?:\d{2}|xx|00)\.\d{4}(?:\s+[^,&(]+?)?\s*(?=,|\(|&|$)/i', '', $text);
        }
    }
    
    return [$datum, $ort];
}

function extractLabeledField(&$text, $label) {
    $pattern = '/\b' . preg_quote($label, '/') . '\s*:\s*([^,&(]+?)(?=\s+\d+\s*[jJ]\b|\s+geb\.|\s+gest\.|,|&|\(|$)/i';
    if (!preg_match($pattern, $text, $m)) {
        return null;
    }

    $value = trim($m[1]);
    $text = preg_replace($pattern, '', $text, 1);
    $text = preg_replace('/\s{2,}/', ' ', $text);
    return $value === '' ? null : $value;
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
    
    // Pattern: (Name (Vater & Mutter)) with missing outer closing bracket in source.
    // Example: (Maria ... (Mathias ... & Anna ...)
    if (preg_match('/^\(\s*([^()&]+?)\s*\(\s*([^()&]+)\s*&\s*([^)]+)\)\s*$/i', $text, $m)) {
        $text = trim($m[1]);
        $vater = trim($m[2]);
        $mutter = trim($m[3]);

        return [$vater ?: null, $mutter ?: null, null, null];
    }

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
    elseif (preg_match('/\(([^()&]+)\s*&\s*([^()]+)\)/i', $text, $m)) {
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

    // Spezialfall:
    // (unehelicher Sohn Maria X Bemerkung: ..., eheliche Tochter Vater ... & Mutter ...)
    // Beispiel: Franz Sahartinger / Maria Sahartinger / Fabriksnachtwaechter Ort: Woergl & Ursula Feiersinger Ort: Innsbruck
    if (preg_match('/\(unehelich(?:e|er)?\s+(?:Tochter|Sohn)\s+(?:von\s+)?([^,\)]+?)(?:\s+Bemerkung:\s*([^,\)]*))?,\s*eheliche\s+Tochter\s+([^&\)]+)\s*&\s*([^\)]+)\)/i', $text, $m)) {
        $mutterName = trim($m[1]);
        $mutterBemerkung = trim($m[2] ?? '');
        $vaterDerMutter = trim($m[3]);
        $mutterDerMutter = trim($m[4]);

        if ($mutterBemerkung !== '') {
            $mutterName .= ', Bemerkung: ' . $mutterBemerkung;
        }

        $result['bemerkung'] = trim(substr($m[0], 1, -1));
        $result['mutter_text'] = $mutterName ?: null;
        $result['nested_vater_text'] = $vaterDerMutter ?: null;
        $result['nested_mutter_text'] = $mutterDerMutter ?: null;

        $text = str_replace($m[0], '', $text);
        return $result;
    }
    
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

function extractScheidungsdatum(&$text) {
    $scheidungsdatum = null;

    // Formats in source data: "geschieden am 04.05.1949" with optional "in <Ort>".
    // Keep trailing tokens (e.g. S92) untouched by removing only the matched clause.
    if (preg_match('/\bgeschieden\s+(?:am\s+)?((?:\d{2}|xx|00)\.(?:\d{2}|xx|00)\.\d{4})(?:\s+in\s+[^,&(]+)?/i', $text, $m)) {
        $scheidungsdatum = parseDate($m[1]);
        $text = str_replace($m[0], '', $text);
        $text = preg_replace('/\s{2,}/', ' ', $text);
        $text = trim($text);
    }

    return $scheidungsdatum;
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
    if ($referenzEhe) {
        $bemerkung[] = $referenzEhe;
    }
    if ($witweData['bemerkung']) {
        $bemerkung[] = $witweData['bemerkung'];
    }
    if ($otherBemerkung) {
        $bemerkung[] = $otherBemerkung;
    }
    $bemerkung = implode('; ', $bemerkung);
    
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

    // Extract dates after parent extraction so parent birth dates stay with parent records.
    list($tod, $todOrt) = extractDateAndPlace($text, 'gest');
    list($geb, $gebOrt) = extractDateAndPlace($text, 'geb');

    // Feldmarker wie in importThierbach unterstuetzen (wichtig fuer unehelich-Spezialfall).
    $hof = extractLabeledField($text, 'Hof');
    $ort = extractLabeledField($text, 'Ort');
    $bemerkungLabel = extractLabeledField($text, 'Bemerkung');
    if (!empty($bemerkungLabel)) {
        $bemerkung = mergeBemerkungValues($bemerkung, $bemerkungLabel);
    }
    
    // Remove all brackets before parsing name
    $text = preg_replace('/\([^)]*\)/', '', $text);
    $text = preg_replace('/\b\d+\s*[jJ]\b/', '', $text);
    
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
        'hof' => $hof,
        'ort' => $ort,
        'alter' => $alter,
        'referenz_ehe' => $referenzEhe,
        'bemerkung' => $bemerkung,
        'vater_text' => $vater_text,
        'mutter_text' => $mutter_text,
        'nested_vater_text' => $nested_vater_text,
        'nested_mutter_text' => $nested_mutter_text,
    ];
}

function mergeBemerkungValues($existing, $incoming) {
    $existing = trim((string)$existing);
    $incoming = trim((string)$incoming);

    if ($existing === '') return $incoming;
    if ($incoming === '') return $existing;

    $parts = array_merge(
        preg_split('/\s*;\s*/', $existing),
        preg_split('/\s*;\s*/', $incoming)
    );

    $parts = array_values(array_filter(array_map('trim', $parts), static function ($value) {
        return $value !== '';
    }));

    $parts = array_values(array_unique($parts));
    return implode('; ', $parts);
}

function findExistingEheByDateAndNamesOrte($pdo, $heiratsdatum, $mannData, $frauData) {
    if (empty($mannData['vorname']) || empty($mannData['nachname']) || empty($frauData['vorname']) || empty($frauData['nachname'])) {
        return null;
    }

    $stmt = $pdo->prepare(" 
        SELECT e.id, e.mann_id, e.frau_id
        FROM ehe e
        JOIN person mann ON mann.id = e.mann_id
        JOIN person frau ON frau.id = e.frau_id
        WHERE (e.heiratsdatum <=> ?)
          AND mann.vorname = ?
          AND mann.nachname = ?
          AND frau.vorname = ?
          AND frau.nachname = ?
        LIMIT 1
    ");

    $stmt->execute([
        $heiratsdatum,
        $mannData['vorname'],
        $mannData['nachname'],
        $frauData['vorname'],
        $frauData['nachname']
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function syncPersonDetailsByIdOrte($pdo, $personId, $data) {
    if (empty($personId) || !is_numeric($personId)) {
        return;
    }

    $stmt = $pdo->prepare(" 
        SELECT geburtsdatum, sterbedatum, geburtsort, sterbeort, hof, ort, bemerkung
        FROM person
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([(int)$personId]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$existing) {
        return;
    }

    if (!empty($data['geburtsdatum']) && empty($existing['geburtsdatum'])) {
        $pdo->prepare("UPDATE person SET geburtsdatum = ? WHERE id = ?")
            ->execute([$data['geburtsdatum'], (int)$personId]);
    }

    if (!empty($data['sterbedatum']) && empty($existing['sterbedatum'])) {
        $pdo->prepare("UPDATE person SET sterbedatum = ? WHERE id = ?")
            ->execute([$data['sterbedatum'], (int)$personId]);
    }

    if (!empty($data['geburtsort']) && empty($existing['geburtsort'])) {
        $pdo->prepare("UPDATE person SET geburtsort = ? WHERE id = ?")
            ->execute([$data['geburtsort'], (int)$personId]);
    }

    if (!empty($data['sterbeort']) && empty($existing['sterbeort'])) {
        $pdo->prepare("UPDATE person SET sterbeort = ? WHERE id = ?")
            ->execute([$data['sterbeort'], (int)$personId]);
    }

    if (!empty($data['hof']) && empty($existing['hof'])) {
        $pdo->prepare("UPDATE person SET hof = ? WHERE id = ?")
            ->execute([$data['hof'], (int)$personId]);
    }

    if (!empty($data['ort']) && empty($existing['ort'])) {
        $pdo->prepare("UPDATE person SET ort = ? WHERE id = ?")
            ->execute([$data['ort'], (int)$personId]);
    }

    if (!empty($data['bemerkung'])) {
        $mergedBemerkung = mergeBemerkungValues($existing['bemerkung'] ?? '', $data['bemerkung']);
        $pdo->prepare("UPDATE person SET bemerkung = ? WHERE id = ?")
            ->execute([$mergedBemerkung, (int)$personId]);
    }
}

function ensureParentEheExistsOrte($pdo, $vaterId, $mutterId) {
    if ($vaterId === null || $mutterId === null) {
        return null;
    }

    // Vater muss als mann_id und Mutter als frau_id in der Ehe vorhanden sein.
    $stmt = $pdo->prepare("SELECT id FROM ehe WHERE mann_id = ? AND frau_id = ? LIMIT 1");
    $stmt->execute([$vaterId, $mutterId]);
    $eheId = $stmt->fetchColumn();

    if ($eheId) {
        return $eheId;
    }

    $stmt = $pdo->prepare(" 
        INSERT INTO ehe (mann_id, frau_id, heiratsdatum, scheidungsdatum, traubuch)
        VALUES (?, ?, NULL, NULL, NULL)
    ");
    $stmt->execute([$vaterId, $mutterId]);

    return $pdo->lastInsertId();
}

function findExistingParentPairByTextsOrte($pdo, $vaterText, $mutterText) {
    if (empty($vaterText) || empty($mutterText)) {
        return null;
    }

    $vaterData = parsePerson($vaterText);
    $mutterData = parsePerson($mutterText);

    if (empty($vaterData['vorname']) || empty($vaterData['nachname']) || empty($mutterData['vorname']) || empty($mutterData['nachname'])) {
        return null;
    }

    if (($vaterData['vorname'] === '???' && $vaterData['nachname'] === '???') || ($mutterData['vorname'] === '???' && $mutterData['nachname'] === '???')) {
        return null;
    }

    $stmt = $pdo->prepare(" 
        SELECT e.id AS ehe_id,
               v.id AS vater_id,
               v.geburtsdatum AS vater_geb,
               m.id AS mutter_id,
               m.geburtsdatum AS mutter_geb
        FROM ehe e
        JOIN person v ON v.id = e.mann_id
        JOIN person m ON m.id = e.frau_id
        WHERE v.vorname = ? AND v.nachname = ?
          AND m.vorname = ? AND m.nachname = ?
    ");

    $stmt->execute([
        $vaterData['vorname'],
        $vaterData['nachname'],
        $mutterData['vorname'],
        $mutterData['nachname']
    ]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($rows)) {
        return null;
    }

    foreach ($rows as $row) {
        if (!empty($vaterData['geburtsdatum']) && $row['vater_geb'] !== $vaterData['geburtsdatum']) {
            continue;
        }
        if (!empty($mutterData['geburtsdatum']) && $row['mutter_geb'] !== $mutterData['geburtsdatum']) {
            continue;
        }

        return [
            'vater_id' => (int)$row['vater_id'],
            'mutter_id' => (int)$row['mutter_id'],
            'ehe_id' => (int)$row['ehe_id'],
        ];
    }

    // Fallback: erster Treffer, wenn keine genauere Datums-Selektion möglich ist.
    return [
        'vater_id' => (int)$rows[0]['vater_id'],
        'mutter_id' => (int)$rows[0]['mutter_id'],
        'ehe_id' => (int)$rows[0]['ehe_id'],
    ];
}

function findOrCreatePerson($pdo, $data, $vaterId = null, $mutterId = null) {

    // Unlesbare Namen ("???") werden nie gespeichert.
    if (trim($data['vorname'] ?? '') === '???' && trim($data['nachname'] ?? '') === '???') {
        return null;
    }

    // Einteilige Namen (z.B. nur "Ursula") nicht als Person speichern.
    if (trim($data['vorname'] ?? '') === '' || trim($data['nachname'] ?? '') === '') {
        return null;
    }

    // Wenn beide Eltern gesetzt sind, muss auch die passende Ehe existieren.
    ensureParentEheExistsOrte($pdo, $vaterId, $mutterId);

    $stmt = $pdo->prepare(" 
        SELECT id, geburtsdatum, bemerkung, hof, ort FROM person
        WHERE vorname=? AND nachname=?
        AND (vater_id <=> ?)
        AND (mutter_id <=> ?)
    ");
    $stmt->execute([$data['vorname'], $data['nachname'], $vaterId, $mutterId]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $existing = null;

    foreach ($rows as $row) {
        if (!empty($data['geburtsdatum'])) {
            // Geburtsdatum bekannt: nur exakten Treffer akzeptieren.
            if ($row['geburtsdatum'] === $data['geburtsdatum']) {
                $existing = $row;
                break;
            }
        } elseif ($vaterId !== null || $mutterId !== null) {
            // Mindestens ein Elternteil bekannt: ersten Treffer nehmen.
            // Die WHERE-Klausel hat bereits beide Elternteile geprueft.
            $existing = $row;
            break;
        } else {
            // Weder Geburtsdatum noch ein bekannter Elternteil:
            // Keine Wiederverwendung – neue Person anlegen.
            // Verhindert Fehlzuordnungen (z.B. unmoeglich lange Lebensspannen).
            break;
        }
    }

    $id = $existing['id'] ?? null;
    
    if ($id) {
        if (!empty($data['geburtsdatum']) && empty($existing['geburtsdatum'])) {
            $update = $pdo->prepare("UPDATE person SET geburtsdatum = ? WHERE id = ?");
            $update->execute([$data['geburtsdatum'], $id]);
        }

        if (!empty($data['sterbedatum'])) {
            $update = $pdo->prepare("UPDATE person SET sterbedatum = COALESCE(sterbedatum, ?) WHERE id = ?");
            $update->execute([$data['sterbedatum'], $id]);
        }

        if (!empty($data['geburtsort'])) {
            $update = $pdo->prepare("UPDATE person SET geburtsort = COALESCE(geburtsort, ?) WHERE id = ?");
            $update->execute([$data['geburtsort'], $id]);
        }

        if (!empty($data['sterbeort'])) {
            $update = $pdo->prepare("UPDATE person SET sterbeort = COALESCE(sterbeort, ?) WHERE id = ?");
            $update->execute([$data['sterbeort'], $id]);
        }

        if (!empty($data['bemerkung'])) {
            $mergedBemerkung = mergeBemerkungValues($existing['bemerkung'] ?? '', $data['bemerkung']);
            $update = $pdo->prepare("UPDATE person SET bemerkung = ? WHERE id = ?");
            $update->execute([$mergedBemerkung, $id]);
        }

        if (!empty($data['hof']) && empty($existing['hof'])) {
            $update = $pdo->prepare("UPDATE person SET hof = COALESCE(hof, ?) WHERE id = ?");
            $update->execute([$data['hof'], $id]);
        }

        if (!empty($data['ort']) && empty($existing['ort'])) {
            $update = $pdo->prepare("UPDATE person SET ort = COALESCE(ort, ?) WHERE id = ?");
            $update->execute([$data['ort'], $id]);
        }
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO person (vorname, nachname, geburtsdatum, sterbedatum, geburtsort, sterbeort, hof, ort, bemerkung, vater_id, mutter_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['vorname'],
            $data['nachname'],
            $data['geburtsdatum'],
            $data['sterbedatum'],
            $data['geburtsort'],
            $data['sterbeort'],
            $data['hof'] ?? null,
            $data['ort'] ?? null,
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

    if (trim($personData['vorname'] ?? '') === '???' && trim($personData['nachname'] ?? '') === '???') {
        return null;
    }

    // Einteilige Person-Texte (z.B. nur "Ursula") werden nicht als Person angelegt.
    if (trim($personData['vorname'] ?? '') === '' || trim($personData['nachname'] ?? '') === '') {
        return null;
    }
    
    $vaterId = null;
    $mutterId = null;

    // Wenn beide Elterntexte vorhanden sind, zuerst bestehendes Eltern-Ehepaar
    // anhand der Namenskombination wiederverwenden.
    $canUsePairLookup = !empty($personData['vater_text'])
        && !empty($personData['mutter_text'])
        && empty($personData['nested_vater_text'])
        && empty($personData['nested_mutter_text']);

    if ($canUsePairLookup) {
        $existingPair = findExistingParentPairByTextsOrte($pdo, $personData['vater_text'], $personData['mutter_text']);
        if ($existingPair) {
            $vaterId = $existingPair['vater_id'];
            $mutterId = $existingPair['mutter_id'];
        }
    }
    
    if ($personData['vater_text'] && $vaterId === null) {
        $vaterData = parsePerson($personData['vater_text']);
        if (trim($vaterData['vorname'] ?? '') === '' && trim($vaterData['nachname'] ?? '') !== '') {
            // Unvollstaendiger Vatername nur als Bemerkung am Kind speichern.
            $personData['bemerkung'] = mergeBemerkungValues($personData['bemerkung'] ?? '', 'Vater ' . trim($vaterData['nachname']));
            $vaterId = null;
        } else {
            $vaterId = findOrCreatePersonFromText($pdo, $personData['vater_text']);
        }
    }
    
    if ($personData['mutter_text'] && $mutterId === null) {
        $mutterData = parsePerson($personData['mutter_text']);
        if (trim($mutterData['vorname'] ?? '') === '' && trim($mutterData['nachname'] ?? '') !== '') {
            // Unvollstaendiger Muttername nur als Bemerkung am Kind speichern.
            $personData['bemerkung'] = mergeBemerkungValues($personData['bemerkung'] ?? '', 'Mutter ' . trim($mutterData['nachname']));
            $mutterId = null;
        }
        // If the mother also has nested parents (grandparents), process them first
        elseif ($personData['nested_vater_text'] || $personData['nested_mutter_text']) {
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
        SELECT mann_id, frau_id
        FROM ehe
        WHERE mann_id = ? OR frau_id = ?
        LIMIT 1
    ");
    $stmt->execute([$personId, $personId]);
    $ehe = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$ehe) return;
    
    $partnerId = ($ehe['mann_id'] == $personId)
    ? $ehe['frau_id']
    : $ehe['mann_id'];
    
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
            $scheidungsdatum = extractScheidungsdatum($line);
            
            list($mannText, $frauText) = splitOutsideBrackets($line);
            if (!$mannText || !$frauText) continue;

            $mannData = parsePerson($mannText);
            $frauData = parsePerson($frauText);

            // 1) Zuerst auf bestehende Ehe pruefen: Hochzeitsdatum + vollstaendige Partnernamen.
            $existingEhe = findExistingEheByDateAndNamesOrte($pdo, $heiratsdatum, $mannData, $frauData);
            if ($existingEhe) {
                // Keine neue Ehe anlegen; nur zusaetzliche Personeninfos zusammenfuehren.
                syncPersonDetailsByIdOrte($pdo, $existingEhe['mann_id'], $mannData);
                syncPersonDetailsByIdOrte($pdo, $existingEhe['frau_id'], $frauData);

                if (!empty($scheidungsdatum)) {
                    $pdo->prepare(" 
                        UPDATE ehe
                        SET scheidungsdatum = COALESCE(scheidungsdatum, ?)
                        WHERE id = ?
                    ")->execute([$scheidungsdatum, $existingEhe['id']]);
                }

                $imported++;
                continue;
            }
            
            $mannId = findOrCreatePersonFromText($pdo, $mannText);
            $frauId = findOrCreatePersonFromText($pdo, $frauText);
            
            if (!$mannId || !$frauId) continue;
            
            $stmt = $pdo->prepare("
                SELECT id FROM ehe
                     WHERE (mann_id = ? AND frau_id = ?)
                         OR (mann_id = ? AND frau_id = ?)
            ");
            $stmt->execute([$mannId, $frauId, $frauId, $mannId]);
            $eheId = $stmt->fetchColumn();
            
            if (!$eheId) {
                $pdo->prepare("
                    INSERT INTO ehe (mann_id, frau_id, heiratsdatum, scheidungsdatum, traubuch)
                    VALUES (?, ?, ?, ?, ?)
                ")->execute([$mannId, $frauId, $heiratsdatum, $scheidungsdatum, $traubuch]);
                
                $eheId = $pdo->lastInsertId();
            } elseif (!empty($scheidungsdatum)) {
                $pdo->prepare(" 
                    UPDATE ehe
                    SET scheidungsdatum = COALESCE(scheidungsdatum, ?)
                    WHERE id = ?
                ")->execute([$scheidungsdatum, $eheId]);
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

        // verifiedNames.txt ausschließen
        if (stripos($filename, 'verifiedNames') !== false) {
            echo "<div style='color:#999;'>⏭️ <strong>$filename</strong> - übersprungen</div>";
            continue;
        }
        
        // Traubuch-Name: nur Teil vor dem ersten "-" (z.B. Auffach-komplett -> Auffach)
        $filenameWithoutExt = pathinfo($filename, PATHINFO_FILENAME);
        $traubuch = trim(explode('-', $filenameWithoutExt, 2)[0]);
        
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
}

/* =========================
 HAUPTLOGIK
 ========================= */

// Wenn direkt aufgerufen (nicht über re-create-all.php)
if (!isset($SKIP_AUTO_IMPORT)) {
    $pdo = getPDO();
    runOrteImport();
}