<?php

/**
 * Tirol-Archiv Familiennamen Helper
 * Verwaltet den Abruf und die Verarbeitung von Familiennamen aus dem Tirol-Archiv
 */

// ===========================
// KONFIGURATION
// ===========================

// Basis-URL zum Tirol-Archiv
define('TIROL_ARCHIV_BASE_URL', 'https://www.tirol.gv.at/kunst-kultur/landesarchiv/forschungstipps/familiennamen/');

// Timeout für HTTP-Requests
define('TIROL_ARCHIV_TIMEOUT', 15);

// Minimale Ähnlichkeit für Archiv-Vergleich (in Prozent)
define('TIROL_ARCHIV_MIN_SIMILARITY', 80);

// Cache-Verzeichnis (optional)
define('TIROL_ARCHIV_CACHE_DIR', __DIR__ . '/cache/tirol-archiv/');

// Cache-Gültigkeitsdauer (in Sekunden, 0 = kein Cache)
define('TIROL_ARCHIV_CACHE_TTL', 86400); // 24 Stunden

// ===========================
// HELPER FUNKTIONEN
// ===========================

/**
 * Entferne Fragezeichen und x aus Namen für Vergleich
 */
function cleanNameForComparison($name) {
    return str_replace(['?', '?', 'x', 'X'], '', $name);
}

/**
 * Levenshtein-Ähnlichkeit in Prozent berechnen
 * Entfernt Fragezeichen und x vor dem Vergleich
 */
function levenshteinSimilarity($str1, $str2) {
    // Entferne Fragezeichen und x für Vergleich
    $str1_clean = cleanNameForComparison($str1);
    $str2_clean = cleanNameForComparison($str2);
    
    $distance = levenshtein(strtolower($str1_clean), strtolower($str2_clean));
    $maxLen = max(strlen($str1_clean), strlen($str2_clean));
    if ($maxLen == 0) return 100;
    return round((1 - ($distance / $maxLen)) * 100);
}

/**
 * Bestimmt das Tirol-Archiv-Prefix für einen Nachnamen
 * z.B. "Schmied" -> "sch", "Brenner" -> "b"
 */
function getTirolArchivPrefix($nachname) {
    $lower = strtolower(cleanNameForComparison($nachname));
    
    // Prüfe spezielle Präfixe (längere zuerst)
    $specials = ['sch', 'sp', 'st', 'tz', 'tsch', 'ch'];
    foreach ($specials as $special) {
        if (strpos($lower, $special) === 0) {
            return $special;
        }
    }
    
    // Erster Buchstabe
    return strtolower(substr($nachname, 0, 1));
}

/**
 * Generiert die URL für einen bestimmten Prefix
 */
function getTirolArchivUrl($prefix) {
    return TIROL_ARCHIV_BASE_URL . 'familiennamen-' . $prefix . '/';
}

/**
 * Cache-Dateiname generieren
 */
function getTirolArchivCacheFile($prefix) {
    if (!TIROL_ARCHIV_CACHE_TTL) {
        return null;
    }
    
    if (!is_dir(TIROL_ARCHIV_CACHE_DIR)) {
        @mkdir(TIROL_ARCHIV_CACHE_DIR, 0755, true);
    }
    
    return TIROL_ARCHIV_CACHE_DIR . 'archiv-' . $prefix . '.json';
}

/**
 * Cache laden (wenn verfügbar und noch gültig)
 */
function loadTirolArchivCache($prefix) {
    if (!TIROL_ARCHIV_CACHE_TTL) {
        return null;
    }
    
    $cacheFile = getTirolArchivCacheFile($prefix);
    
    if (!file_exists($cacheFile)) {
        return null;
    }
    
    if (time() - filemtime($cacheFile) > TIROL_ARCHIV_CACHE_TTL) {
        @unlink($cacheFile);
        return null;
    }
    
    $cached = @json_decode(file_get_contents($cacheFile), true);
    return $cached ?: null;
}

/**
 * Cache speichern
 */
function saveTirolArchivCache($prefix, $data) {
    if (!TIROL_ARCHIV_CACHE_TTL) {
        return;
    }
    
    $cacheFile = getTirolArchivCacheFile($prefix);
    @file_put_contents($cacheFile, json_encode($data), LOCK_EX);
}

/**
 * Holt und parsed Nachnamen vom Tirol-Archiv mit Ortsangaben
 * Gibt Array zurück: ['Name' => ['Ort1', 'Ort2', ...], ...]
 */
function getTirolArchivNamesWithPlaces($prefix) {
    // Prüfe Cache
    $cached = loadTirolArchivCache($prefix);
    if ($cached !== null) {
        return $cached;
    }
    
    $url = getTirolArchivUrl($prefix);
    $names = [];
    
    try {
        $context = stream_context_create([
            'http' => [
                'timeout' => TIROL_ARCHIV_TIMEOUT,
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ]
        ]);
        
        $html = @file_get_contents($url, false, $context);
        
        if ($html === false) {
            return [];
        }
        
        // Entferne Script und Style Tags
        $html = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $html);
        $html = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $html);
        
        // Parse Names mit verschiedenen Mustern
        $names = parseNamesFromHtml($html, $prefix);
        
        // Speichere im Cache
        saveTirolArchivCache($prefix, $names);
        
    } catch (Exception $e) {
        error_log('Tirol Archiv Error: ' . $e->getMessage());
    }
    
    return $names;
}

/**
 * Parse Familiennamen aus HTML
 * Diese Funktion kann leicht erweitert werden
 */
function parseNamesFromHtml($html, $prefix) {
    $names = [];
    
    // Muster 1: <li>Name (Ort1, Ort2)</li>
    if (preg_match_all('/<li[^>]*>([^<]+)<\/li>/i', $html, $matches)) {
        foreach ($matches[1] as $match) {
            $entry = parseNameEntry($match, $prefix);
            if ($entry) {
                $name = $entry['name'];
                $places = $entry['places'];
                
                if (!isset($names[$name])) {
                    $names[$name] = [];
                }
                $names[$name] = array_merge($names[$name], $places);
                $names[$name] = array_unique($names[$name]);
            }
        }
    }
    
    // Muster 2: <p>Name (Ort1, Ort2)</p>
    if (empty($names)) {
        if (preg_match_all('/<p[^>]*>([^<]+(?:' . preg_quote($prefix, '/') . ')[^<]*)<\/p>/i', $html, $matches)) {
            foreach ($matches[1] as $match) {
                $entry = parseNameEntry($match, $prefix);
                if ($entry) {
                    $name = $entry['name'];
                    $places = $entry['places'];
                    
                    if (!isset($names[$name])) {
                        $names[$name] = [];
                    }
                    $names[$name] = array_merge($names[$name], $places);
                    $names[$name] = array_unique($names[$name]);
                }
            }
        }
    }
    
    ksort($names);
    return $names;
}

/**
 * Parse einen einzelnen Name-Eintrag
 * Gibt ['name' => '...', 'places' => [...]] oder null zurück
 */
function parseNameEntry($text, $prefix) {
    $text = trim(strip_tags(html_entity_decode($text)));
    $text = trim($text, ' ,');
    
    if (empty($text) || strlen($text) < 2) {
        return null;
    }
    
    // Überspringe Jahrzahlen und Längen-Angaben
    if (preg_match('/^\d{4}|[0-9]{4}$/', $text)) {
        return null;
    }
    
    $name = $text;
    $places = [];
    
    // Muster 1: "Name (Ort1, Ort2)"
    if (preg_match('/^([^(]+)\s*\(([^)]+)\)/', $text, $m)) {
        $name = trim($m[1]);
        $places = array_map('trim', explode(',', $m[2]));
        $places = array_filter($places); // Entferne leere Einträge
    }
    // Muster 2: "Name – Ort1, Ort2" oder "Name - Ort1, Ort2"
    elseif (preg_match('/^([^–-]+)\s*[–-]\s*(.+)$/', $text, $m)) {
        $name = trim($m[1]);
        $places = array_map('trim', explode(',', $m[2]));
        $places = array_filter($places);
    }
    
    // Validiere Namen
    if (empty($name) || strlen($name) < 2) {
        return null;
    }
    
    // Prüfe, ob Name mit dem richtigen Prefix anfängt
    if (strpos(strtolower(cleanNameForComparison($name)), strtolower($prefix)) !== 0) {
        return null;
    }
    
    // Überspringe sehr kurze Namen (wahrscheinlich Fehler)
    if (strlen(cleanNameForComparison($name)) < 3 && !preg_match('/^[A-Z]{2,3}$/', $name)) {
        return null;
    }
    
    return [
        'name' => $name,
        'places' => $places
    ];
}

/**
 * Findet ähnliche Namen im Tirol-Archiv mit konfigurierbarer minimaler Ähnlichkeit
 */
function findSimilarNamesInArchive($nachname, $minSimilarity = TIROL_ARCHIV_MIN_SIMILARITY) {
    $prefix = getTirolArchivPrefix($nachname);
    $archiveNames = getTirolArchivNamesWithPlaces($prefix);
    
    if (empty($archiveNames)) {
        return [];
    }
    
    $similar = [];
    foreach ($archiveNames as $archiveName => $places) {
        $similarity = levenshteinSimilarity($nachname, $archiveName);
        
        if ($similarity >= $minSimilarity) {
            $similar[] = [
                'name' => $archiveName,
                'similarity' => $similarity,
                'places' => $places,
                'prefix' => $prefix,
                'searchedName' => $nachname
            ];
        }
    }
    
    // Sortiere nach Ähnlichkeit (absteigend)
    usort($similar, function($a, $b) {
        return $b['similarity'] - $a['similarity'];
    });
        
        return $similar;
}

/**
 * Gibt HTML für eine Ähnliche-Namen-Box zurück
 * Box ist standardmäßig eingeklappt
 */
function renderArchiveNamesBox($nachname, $minSimilarity = TIROL_ARCHIV_MIN_SIMILARITY) {
    if (empty($nachname)) {
        return '';
    }
    
    $similar = findSimilarNamesInArchive($nachname, $minSimilarity);
    
    if (empty($similar)) {
        return '<div style="background:#fff3cd; border-left:4px solid #ffc107; padding:12px; margin:12px 0; border-radius:3px; font-size:0.9em; color:#856404;">
                    <strong>ℹ️ Tirol-Archiv:</strong> Keine Namen mit mind. ' . $minSimilarity . '% Ähnlichkeit gefunden
                </div>';
    }
    
    $boxId = 'tirol-archive-' . md5($nachname);
    $contentId = 'tirol-archive-content-' . md5($nachname);
    
    $html = '<div style="background:#e8f4f8; border:2px solid #0099cc; padding:14px; margin:12px 0; border-radius:3px;">';
    
    // Kopfzeile mit Toggle
    $html .= '<div style="cursor:pointer; display:flex; align-items:center; justify-content:space-between;" onclick="toggleTirolArchive(\'' . $boxId . '\', \'' . $contentId . '\');">';
    $html .= '<strong style="color:#0099cc; font-size:1.1em;">📚 Tirol-Archiv Familiennamen - Ähnlichkeiten zu <em>' . htmlspecialchars($nachname, ENT_QUOTES, 'UTF-8') . '</em></strong>';
    $html .= '<span id="' . $boxId . '-icon" style="color:#0099cc; font-size:1.2em; transition:transform 0.3s; display:inline-block;">▶</span>';
    $html .= '</div>';
    
    // Zusammenfassung (immer sichtbar)
    $html .= '<div style="color:#0c5460; font-size:0.9em; margin-top:8px;">';
    $html .= '<strong>' . count($similar) . ' ähnliche Namen gefunden</strong>';
    if (count($similar) > 0) {
        $html .= ' - Beste Übereinstimmung: <strong>' . htmlspecialchars($similar[0]['name'], ENT_QUOTES, 'UTF-8') . '</strong> (' . intval($similar[0]['similarity']) . '%)';
    }
    $html .= '</div>';
    
    // Inhalt (eingeklappt)
    $html .= '<div id="' . $contentId . '" style="display:none; margin-top:14px; padding-top:14px; border-top:2px solid #0099cc;">';
    $html .= '<small style="color:#0c5460;">';
    
    foreach ($similar as $item) {
        if (!isset($item['similarity']) || !isset($item['name'])) {
            continue;
        }
        
        $match = '';
        $sim = intval($item['similarity']);
        
        if ($sim == 100) {
            $match = '✅ Exakt';
        } elseif ($sim >= 95) {
            $match = '🟢 ' . $sim . '% sehr ähnlich';
        } elseif ($sim >= 90) {
            $match = '🟡 ' . $sim . '% ähnlich';
        } else {
            $match = '🔵 ' . $sim . '% ähnlich';
        }
        
        $html .= '<div style="padding:12px; background:#d1ecf1; margin:10px 0; border-radius:3px; border-left:4px solid #0099cc;">';
        
        // Kopfzeile mit Ähnlichkeit
        $html .= '<div style="margin-bottom:8px;">';
        $html .= '<strong style="color:#0c5460; font-size:1.05em;">' . htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') . '</strong> ';
        $html .= '<span style="color:#555; font-size:0.95em;">' . htmlspecialchars($match, ENT_QUOTES, 'UTF-8') . '</span>';
        $html .= '</div>';
        
        // Vergleichsinfo
        $html .= '<div style="color:#0c5460; font-size:0.85em; background:#ffffff; padding:6px 8px; border-radius:2px; margin-bottom:8px; border-left:3px solid #0099cc;">';
        $html .= '<em>Ähnlichkeit zu "' . htmlspecialchars($item['searchedName'], ENT_QUOTES, 'UTF-8') . '": ' . $sim . '%</em>';
        $html .= '</div>';
        
        // Orte
        if (!empty($item['places']) && is_array($item['places'])) {
            $html .= '<div style="color:#555; font-size:0.85em;">';
            $html .= '<strong>Orte:</strong> ';
            $placesStr = implode(', ', array_map(function($p) {
                return htmlspecialchars($p, ENT_QUOTES, 'UTF-8');
            }, $item['places']));
                $html .= $placesStr;
                $html .= '</div>';
        }
        
        $html .= '</div>';
    }
    
    $html .= '</small>';
    $html .= '</div>';
    $html .= '</div>';
    
    return $html;
}

?>