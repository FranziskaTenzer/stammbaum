<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

if (!function_exists('getPDO')) {
    include 'include.php';
}

$DEBUG = true;

function debug($msg) {
    global $DEBUG;
    if ($DEBUG) echo "<div style='color:#555'>$msg</div>";
}

/* =========================
 HELFER
 ========================= */

function parseDateThierbach($text) {
    if (!$text) return null;
    // Handle flexible date formats with xx or 00 for unknown day/month
    if (preg_match('/^(0[1-9]|[12]\d|3[01]|xx|00)\.(0[1-9]|1[0-2]|xx|00)\.(\d{4})$/', $text, $m)) {
        $day   = ($m[1] === '00') ? 'xx' : $m[1];
        $month = ($m[2] === '00') ? 'xx' : $m[2];
        $year  = $m[3];
        return "$year-$month-$day";
    }
    $timestamp = strtotime(str_replace('.', '-', $text));
    return ($timestamp !== false) ? date("Y-m-d", $timestamp) : null;
}

function extractSIdThierbach(&$text) {
    if (preg_match('/\b(S\d+)\b/', $text, $m)) {
        $text = str_replace($m[1], '', $text);
        return $m[1];
    }
    return null;
}

function extractFieldThierbach(&$text, $label) {
    if (preg_match('/' . preg_quote($label, '/') . '\s*([^,]+)/i', $text, $m)) {
        $value = trim($m[1]);
        $text = preg_replace('/' . preg_quote($label, '/') . '\s*[^,]+,?/i', '', $text);
        return $value;
    }
    return null;
}

/* =========================
 UNEHELICHE PARENT EXTRACTOR - für einzelne Person
 ========================= */

function extractIllegitimateParentFromPerson(&$text) {
    $parent = null;
    
    // Suche IRGENDWELCHE Klammern mit "unehelich" darin
    if (preg_match('/\(uneheliche?[r]?\s+(?:von|Sohn\s+von|Tochter\s*:\s*)([^)]*)\)/i', $text, $m)) {
        $parentInfo = trim($m[1]);
        
        debug("🔍 Raw Parent Info: '$parentInfo'");
        
        // Splitte nach & und Kommas
        $people = preg_split('/[,&]/', $parentInfo);
        $people = array_map('trim', $people);
        $people = array_filter($people);
        
        // Nehme die ERSTE Person (das ist die direkte Mutter!)
        if (!empty($people)) {
            $firstPerson = array_shift($people);
            
            // Entferne S-IDs
            $firstPerson = preg_replace('/\s*S\d+\s*/', '', $firstPerson);
            $firstPerson = trim($firstPerson);
            
            // Teile in Vorname und Nachname
            $parentParts = preg_split('/\s+/', $firstPerson);
            $parentNachname = count($parentParts) > 1 ? array_pop($parentParts) : null;
            $parentVorname = implode(' ', $parentParts);
            
            $parent = [
                'vorname' => trim($parentVorname),
                'nachname' => $parentNachname
            ];
            
            debug("📌 Uneheliche Mutter extrahiert: " . $parent['vorname'] . " " . $parent['nachname']);
        }
    }
    
    // Entferne ALLE Klammern mit Unehelich-Info aus dem Text
    $text = preg_replace('/\s*\(uneheliche?[r]?\s+(?:von|Sohn\s+von|Tochter\s*:\s*)[^)]*\)/i', '', $text);
    
    return $parent;
}

/* =========================
 PERSON PARSER
 ========================= */

function parsePersonText($text, &$illegitimateParent = null) {
    
    // ZUERST alle Variablen mit null initialisieren
    $referenzEhe = null;
    $alter = null;
    $geb = null;
    $gest = null;
    $hof = null;
    $ort = null;
    $bemerkung = null;
    $illegitimateParent = null;
    
    // ✅ KRITISCH: Uneheliche Parent aus diesem Text extrahieren
    $illegitimateParent = extractIllegitimateParentFromPerson($text);
    
    $referenzEhe = extractSIdThierbach($text);
    
    $text = preg_replace('/^\d{2}\.\d{2}\.\d{4}\s*/', '', $text);
    
    preg_match('/\b(\d+)\s*[jJ]\b/', $text, $ageMatch);
    $alter = $ageMatch[1] ?? null;
    
    preg_match('/geb\.\s*(\d{2}\.\d{2}\.\d{4})/', $text, $gebMatch);
    preg_match('/gest\.\s*(\d{2}\.\d{2}\.\d{4})/', $text, $gestMatch);
    
    $geb = $gebMatch[1] ?? null;
    $gest = $gestMatch[1] ?? null;
    
    $text = preg_replace('/geb\..*?\d{2}\.\d{2}\.\d{4}/', '', $text);
    $text = preg_replace('/gest\..*?\d{2}\.\d{2}\.\d{4}/', '', $text);
    $text = preg_replace('/\b(\d+)\s*[jJ]\b/', '', $text);
    
    $hof = extractFieldThierbach($text, 'Hof:');
    $ort = extractFieldThierbach($text, 'Ort:');
    $bemerkung = extractFieldThierbach($text, 'Bemerkung:');
    
    $text = str_replace(',', '', $text);
    
    $text = trim($text);
    
    $parts = preg_split('/\s+/', $text);
    $nachname = count($parts) > 1 ? array_pop($parts) : null;
    $vorname = implode(' ', $parts);
    
    return [
        'vorname' => trim($vorname),
        'nachname' => $nachname,
        'geburtsdatum' => $geb ? parseDateThierbach($geb) : null,
        'sterbedatum' => $gest ? parseDateThierbach($gest) : null,
        'hof' => $hof,
        'ort' => $ort,
        'bemerkung' => $bemerkung,
        'referenz_ehe' => $referenzEhe,
        'alter' => $alter,
        'illegitimate_parent' => $illegitimateParent
    ];
}

/* =========================
 PERSON DB - NORMAL
 ========================= */

function getOrCreatePerson($pdo, $data, $vaterId = null, $mutterId = null) {
    
    // 1. Match über S-ID (stärkste Identität)
    if (!empty($data['referenz_ehe'])) {
        
        $stmt = $pdo->prepare("
            SELECT p.id
            FROM person p
            JOIN ehe e ON e.externe_id = ?
            WHERE p.vorname = ?
            AND p.nachname = ?
            AND p.vater_id = e.mann_id
            AND p.mutter_id = e.frau_id
            LIMIT 1
        ");
        
        $stmt->execute([
            $data['referenz_ehe'],
            $data['vorname'],
            $data['nachname']
        ]);
        
        if ($id = $stmt->fetchColumn()) return $id;
    }
    
    // 2. Match ��ber Eltern + Geburtsdatum
    $stmt = $pdo->prepare("
        SELECT id, geburtsdatum
        FROM person
        WHERE vorname = ?
        AND nachname = ?
        AND (vater_id <=> ?)
        AND (mutter_id <=> ?)
    ");
    
    $stmt->execute([
        $data['vorname'],
        $data['nachname'],
        $vaterId,
        $mutterId
    ]);
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        
        if (!empty($data['geburtsdatum'])) {
            if ($row['geburtsdatum'] === $data['geburtsdatum']) {
                return $row['id'];
            }
        } else {
            return $row['id'];
        }
    }
    
    // 3. Insert
    $stmt = $pdo->prepare("
        INSERT INTO person (
            vorname, nachname,
            vater_id, mutter_id,
            geburtsdatum, sterbedatum,
            hof, ort, bemerkung
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $data['vorname'],
        $data['nachname'],
        $vaterId,
        $mutterId,
        $data['geburtsdatum'],
        $data['sterbedatum'],
        $data['hof'] ?? null,
        $data['ort'] ?? null,
        $data['bemerkung'] ?? null
    ]);
    
    return $pdo->lastInsertId();
}

/* =========================
 PERSON DB - NUR FÜR UNEHELICHE FÄLLE
 ========================= */

function getOrCreatePersonIllegitimate($pdo, $data) {
    
    // Für uneheliche Fälle: Suche Person nur nach Vorname + Nachname
    // Diese Funktion wird NICHT mit Parent-IDs aufgerufen!
    
    // 1. Match über Geburtsdatum (falls vorhanden)
    if (!empty($data['geburtsdatum'])) {
        $stmt = $pdo->prepare("
            SELECT id
            FROM person
            WHERE vorname = ?
            AND nachname = ?
            AND geburtsdatum = ?
            LIMIT 1
        ");
        
        $stmt->execute([
            $data['vorname'],
            $data['nachname'],
            $data['geburtsdatum']
        ]);
        
        if ($id = $stmt->fetchColumn()) return $id;
    }
    
    // 2. Match über Vorname + Nachname allein
    $stmt = $pdo->prepare("
        SELECT id
        FROM person
        WHERE vorname = ?
        AND nachname = ?
        LIMIT 1
    ");
    
    $stmt->execute([
        $data['vorname'],
        $data['nachname']
    ]);
    
    if ($id = $stmt->fetchColumn()) return $id;
    
    // 3. Insert (nur wenn nicht existiert)
    $stmt = $pdo->prepare("
        INSERT INTO person (
            vorname, nachname,
            vater_id, mutter_id,
            geburtsdatum, sterbedatum,
            hof, ort, bemerkung
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $data['vorname'],
        $data['nachname'],
        null, // Keine Parent-IDs bei unehelichen - diese werden später gesetzt
        null,
        $data['geburtsdatum'] ?? null,
        $data['sterbedatum'] ?? null,
        $data['hof'] ?? null,
        $data['ort'] ?? null,
        $data['bemerkung'] ?? null
    ]);
    
    return $pdo->lastInsertId();
}

/* =========================
 LINE PARSER
 ========================= */

function parseLine($line) {
    
    $result = [
        's_id' => null,
        'heiratsdatum' => null,
        'traubuch' => null,
        'eltern' => null,
        'kinder' => []
    ];
    
    $traubuch = extractFieldThierbach($line, 'Traubuch:');
    $result['traubuch'] = $traubuch;
    
    if (preg_match('/^(S\d+)/', $line, $m)) {
        $result['s_id'] = $m[1];
        $line = preg_replace('/^S\d+\s*/', '', $line);
    }
    
    if (preg_match('/^\s*(\d{2}\.\d{2}\.\d{4})/', $line, $dm)) {
        $result['heiratsdatum'] = parseDateThierbach($dm[1]);
        $line = preg_replace('/^\s*\d{2}\.\d{2}\.\d{4}\s*/', '', $line);
    }
    
    if (strpos($line, 'Kinder:') !== false) {
        list($line, $kinderTeil) = explode('Kinder:', $line, 2);
        
        $kinderEintraege = explode('&', $kinderTeil);
        
        foreach ($kinderEintraege as $k) {
            $k = trim($k);
            if ($k !== '') {
                $illegParent = null;
                $result['kinder'][] = parsePersonText($k, $illegParent);
            }
        }
    }
    
    if (preg_match('/^(.*?)\s*&\s*(.*)$/', $line, $m)) {
        $vaterIllegParent = null;
        $mutterIllegParent = null;
        
        $result['eltern'] = [
            'vater' => parsePersonText(trim($m[1]), $vaterIllegParent),
            'mutter' => parsePersonText(trim($m[2]), $mutterIllegParent)
        ];
        
        // Speichere die unehelichen Parent-Infos
        if ($vaterIllegParent) {
            $result['eltern']['vater']['illegitimate_parent'] = $vaterIllegParent;
        }
        if ($mutterIllegParent) {
            $result['eltern']['mutter']['illegitimate_parent'] = $mutterIllegParent;
        }
    }
    
    return $result;
}

/* =========================
 IMPORT FUNKTION
 ========================= */

function runThierbachImport() {
    global $pdo;
    
    // Versuche, Daten aus POST zu bekommen, sonst aus Datei
    $daten = $_POST['daten_import'] ?? '';
    
    // Wenn keine POST-Daten, versuche aus Datei zu lesen
    if (!$daten) {
        // Pfad zur Thierbach-Datei
        $filePath = dirname(dirname(dirname(__DIR__))) . '/stammbaum-daten/Thierbach-komplett.txt';
        
        if (!file_exists($filePath)) {
            echo "❌ Datei nicht gefunden: $filePath";
            return ['imported' => 0, 'errors' => 0];
        }
        
        $daten = file_get_contents($filePath);
        if (!$daten) {
            echo "❌ Fehler beim Lesen der Datei: $filePath";
            return ['imported' => 0, 'errors' => 0];
        }
    }
    
    $lines = preg_split("/\r\n|\n|\r/", $daten);
    $imported = 0;
    $errors = 0;
    
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') continue;
        
        echo "<hr>";
        debug("🧾 RAW: $line");
        
        $parsed = parseLine($line);
        
        if (!$parsed['eltern']) {
            debug("❌ Kein Eltern-Pattern");
            continue;
        }
        
        $vaterData = $parsed['eltern']['vater'];
        $mutterData = $parsed['eltern']['mutter'];
        
        $vaterId = null;
        $mutterId = null;
        
        // Handle Vater mit unehelicher Herkunft
        if (!empty($vaterData['illegitimate_parent'])) {
            $illegMutterData = $vaterData['illegitimate_parent'];
            // Nutze spezielle Funktion für uneheliche Fälle
            $illegMutterId = getOrCreatePersonIllegitimate($pdo, $illegMutterData);
            $vaterId = getOrCreatePerson($pdo, $vaterData, null, $illegMutterId);
            debug("✓ Unehelicher Vater: " . $vaterData['vorname'] . " " . $vaterData['nachname'] . " mit Mutter " . $illegMutterData['vorname'] . " " . $illegMutterData['nachname']);
        } else {
            $vaterId = getOrCreatePerson($pdo, $vaterData);
        }
        
        // Handle Mutter mit unehelicher Herkunft
        if (!empty($mutterData['illegitimate_parent'])) {
            $illegMutterData = $mutterData['illegitimate_parent'];
            // Nutze spezielle Funktion für uneheliche Fälle
            $illegMutterId = getOrCreatePersonIllegitimate($pdo, $illegMutterData);
            $mutterId = getOrCreatePerson($pdo, $mutterData, null, $illegMutterId);
            debug("✓ Uneheliche Mutter: " . $mutterData['vorname'] . " " . $mutterData['nachname'] . " mit Mutter " . $illegMutterData['vorname'] . " " . $illegMutterData['nachname']);
        } else {
            $mutterId = getOrCreatePerson($pdo, $mutterData);
        }
        
        try {
            $stmt = $pdo->prepare("
                INSERT INTO ehe (
                    externe_id,
                    mann_id, mann_alter,
                    frau_id, frau_alter,
                    heiratsdatum, traubuch
                )
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $parsed['s_id'],
                $vaterId,
                $vaterData['alter'] ?? null,
                $mutterId,
                $mutterData['alter'] ?? null,
                $parsed['heiratsdatum'],
                $parsed['traubuch'] ?? 'Thierbach'
            ]);
            
            $eheId = $pdo->lastInsertId();
            
            $pdo->prepare("UPDATE person SET referenz_ehe_id=? WHERE id IN (?, ?)")
            ->execute([$eheId, $vaterId, $mutterId]);
            
            foreach ($parsed['kinder'] as $kind) {
                $kindVaterId = $vaterId;
                $kindMutterId = $mutterId;
                
                // Prüfe ob Kind uneheliche Parent hat
                if (!empty($kind['illegitimate_parent'])) {
                    $illegMutterData = $kind['illegitimate_parent'];
                    // Nutze spezielle Funktion für uneheliche Fälle
                    $kindMutterId = getOrCreatePersonIllegitimate($pdo, $illegMutterData);
                    $kindVaterId = null;
                    debug("✓ Kind mit unehelicher Mutter: " . $kind['vorname'] . " " . $kind['nachname'] . " - Mutter: " . $illegMutterData['vorname'] . " " . $illegMutterData['nachname']);
                }
                
                $kindId = getOrCreatePerson($pdo, $kind, $kindVaterId, $kindMutterId);
                
                if (!empty($kind['referenz_ehe'])) {
                    $stmt = $pdo->prepare("SELECT id FROM ehe WHERE externe_id = ?");
                    $stmt->execute([$kind['referenz_ehe']]);
                    
                    if ($refEheId = $stmt->fetchColumn()) {
                        $pdo->prepare("UPDATE person SET referenz_ehe_id=? WHERE id=?")
                        ->execute([$refEheId, $kindId]);
                    }
                }
            }
            
            debug("✅ Ehe gespeichert (ID: $eheId)");
            $imported++;
            
        } catch (Exception $e) {
            debug("❌ Fehler: " . $e->getMessage());
            $errors++;
        }
    }
    
    return ['imported' => $imported, 'errors' => $errors];
}

/* =========================
 HAUPTLOGIK
 ========================= */

// Wenn direkt aufgerufen (nicht via require_once in re-create-all.php)
if (!isset($SKIP_AUTO_IMPORT)) {
    $pdo = getPDO();
    
    echo "<br /><br />";
    $result = runThierbachImport();
    
    echo "<hr><strong><h2>Import Thierbach erfolgreich</h2></strong><br />";
    echo "✅ Importiert: " . $result['imported'] . " Einträge";
    if ($result['errors'] > 0) {
        echo " | ❌ Fehler: " . $result['errors'];
    }
    echo "<br /><br /><br />";
}