<?php

include 'include.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);

$DEBUG = true;

function debug($msg) {
    global $DEBUG;
    if ($DEBUG) echo "<div style='color:#555'>$msg</div>";
}

$pdo = getPDO();

echo "<a href='stammbaum.php' style='background:#667eea; color:white; padding:10px 20px; border-radius:6px; text-decoration:none;'>← Zurück zur Startseite</a>";



/* =========================
 HELFER
 ========================= */

function parseDate($text) {
    return date("Y-m-d", strtotime(str_replace('.', '-', $text)));
}

function extractSId(&$text) {
    if (preg_match('/\b(S\d+)\b/', $text, $m)) {
        $text = str_replace($m[1], '', $text);
        return $m[1];
    }
    return null;
}

function extractField(&$text, $label) {
    
    /* $value = trim($m[1]);
    
    // nur erstes Wort
    $value = preg_split('/\s+/', $value)[0];
    */
    if (preg_match('/' . preg_quote($label, '/') . '\s*([^,]+)/i', $text, $m)) {
        $value = trim($m[1]);
        
        // Entfernt exakt dieses Feld wieder aus dem Text
        $text = preg_replace('/' . preg_quote($label, '/') . '\s*[^,]+,?/i', '', $text);
        
        return $value;
    }
    return null;
}


/* =========================
 PERSON PARSER
 ========================= */

function parsePersonText($text) {
    
    $referenzEhe = extractSId($text);
    
    // Datum am Anfang entfernen
    $text = preg_replace('/^\d{2}\.\d{2}\.\d{4}\s*/', '', $text);
    
    preg_match('/\b(\d+)\s*[jJ]\b/', $text, $ageMatch);
    $alter = $ageMatch[1] ?? null;
    
    preg_match('/geb\.\s*(\d{2}\.\d{2}\.\d{4})/', $text, $gebMatch);
    preg_match('/gest\.\s*(\d{2}\.\d{2}\.\d{4})/', $text, $gestMatch);
    
    $geb = $gebMatch[1] ?? null;
    $gest = $gestMatch[1] ?? null;
    
    // Entfernen
    $text = preg_replace('/geb\..*?\d{2}\.\d{2}\.\d{4}/', '', $text);
    $text = preg_replace('/gest\..*?\d{2}\.\d{2}\.\d{4}/', '', $text);
    $text = preg_replace('/\b(\d+)\s*[jJ]\b/', '', $text);
    
    // Hof / Ort / Bemerkung extrahieren
    $hof = extractField($text, 'Hof:');
    $ort = extractField($text, 'Ort:');
    $bemerkung = extractField($text, 'Bemerkung:');
    
    // Kommas entfernen
    $text = str_replace(',', '', $text);
    
    $text = trim($text);
    
    $parts = preg_split('/\s+/', $text);
    $nachname = count($parts) > 1 ? array_pop($parts) : null;
    $vorname = implode(' ', $parts);
    
    return [
        'vorname' => trim($vorname),
        'nachname' => $nachname,
        'geburtsdatum' => $geb ? parseDate($geb) : null,
        'sterbedatum' => $gest ? parseDate($gest) : null,
        'hof' => $hof,
        'ort' => $ort,
        'bemerkung' => $bemerkung,
        'referenz_ehe' => $referenzEhe,
        'alter' => $alter
    ];
}


/* =========================
 PERSON DB
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
            AND p.vater_id = e.vater_id
            AND p.mutter_id = e.mutter_id
            LIMIT 1
        ");
        
        $stmt->execute([
            $data['referenz_ehe'],
            $data['vorname'],
            $data['nachname']
        ]);
        
        if ($id = $stmt->fetchColumn()) return $id;
    }
    
    // 2. Match über Eltern
    // 2. Match über Eltern + Geburtsdatum
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
        
        // Wenn Geburtsdatum gesetzt ist → vergleichen
        if (!empty($data['geburtsdatum'])) {
            
            if ($row['geburtsdatum'] === $data['geburtsdatum']) {
                return $row['id']; // exakter Treffer
            }
            
        } else {
            // Falls kein Geburtsdatum vorhanden → erster Treffer zählt
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
    
    // Traubuch extrahieren
    $traubuch = extractField($line, 'Traubuch:');
    $result['traubuch'] = $traubuch;
    
    if (preg_match('/^(S\d+)/', $line, $m)) {
        $result['s_id'] = $m[1];
        $line = preg_replace('/^S\d+\s*/', '', $line);
    }
    
    // erstes Datum = Heirat
    if (preg_match('/^\s*(\d{2}\.\d{2}\.\d{4})/', $line, $dm)) {
        $result['heiratsdatum'] = parseDate($dm[1]);
        $line = preg_replace('/^\s*\d{2}\.\d{2}\.\d{4}\s*/', '', $line);
    }
    
    // Kinder
    if (strpos($line, 'Kinder:') !== false) {
        list($line, $kinderTeil) = explode('Kinder:', $line, 2);
        
        $kinderEintraege = explode('&', $kinderTeil);
        
        foreach ($kinderEintraege as $k) {
            $k = trim($k);
            if ($k !== '') {
                $result['kinder'][] = parsePersonText($k);
            }
        }
    }
    
    // Eltern
    if (preg_match('/^(.*?)\s*&\s*(.*)$/', $line, $m)) {
        
        $result['eltern'] = [
            'vater' => parsePersonText(trim($m[1])),
            'mutter' => parsePersonText(trim($m[2]))
        ];
    }
    
    return $result;
}


/* =========================
 IMPORT
 ========================= */

$daten = $_POST['daten_import'] ?? '';
$lines = preg_split("/\r\n|\n|\r/", $daten);

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
    
    $vaterId = getOrCreatePerson($pdo, $vaterData);
    $mutterId = getOrCreatePerson($pdo, $mutterData);
    
    // -------------------------
    // Ehe: Mehrfachehen erlaubt
    // -------------------------
    $stmt = $pdo->prepare("
        INSERT INTO ehe (
            externe_id,
            vater_id, vater_alter,
            mutter_id, mutter_alter,
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
    
    // Referenz setzen
    $pdo->prepare("UPDATE person SET referenz_ehe_id=? WHERE id IN (?, ?)")
    ->execute([$eheId, $vaterId, $mutterId]);
    
    // -------------------------
    // Kinder
    // -------------------------
    foreach ($parsed['kinder'] as $kind) {
        
        $kindId = getOrCreatePerson($pdo, $kind, $vaterId, $mutterId);
        
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
}

echo "<hr><strong>Import erfolgreich</strong><br /><br /><br />";
echo "<a href='stammbaum.php' style='background:#667eea; color:white; padding:10px 20px; border-radius:6px; text-decoration:none;'>← Zurück zur Startseite</a><br /><br />";

?>