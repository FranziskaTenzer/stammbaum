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

// Buchstaben-Gruppen für Tirol-Archiv
// A  B  C  D  E  F  G  H  I  J  K  L  M  N  O  P/Q  R  S  Sch  Sp  St  T  U  V  W  X/Y/Z
$TIROL_ARCHIV_MAPPINGS = [
    'a' => 'a',
    'b' => 'b',
    'c' => 'c',
    'd' => 'd',
    'e' => 'e',
    'f' => 'f',
    'g' => 'g',
    'h' => 'h',
    'i' => 'i',
    'j' => 'j',
    'k' => 'k',
    'l' => 'l',
    'm' => 'm',
    'n' => 'n',
    'o' => 'o',
    'p' => 'pq',      // P/Q zusammen als pq
    'q' => 'pq',      // P/Q zusammen als pq
    'r' => 'r',
    's' => 's',
    'sch' => 'sch',
    'sp' => 'sp',
    'st' => 'st',
    't' => 't',
    'u' => 'u',
    'v' => 'v',
    'w' => 'w',
    'x' => 'xyz',     // X/Y/Z zusammen als xyz
    'y' => 'xyz',     // X/Y/Z zusammen als xyz
    'z' => 'xyz',     // X/Y/Z zusammen als xyz
];

// ===========================
// HELPER FUNKTIONEN
// ===========================

/**
 * Entferne Fragezeichen aus Namen für Vergleich
 */
function cleanNameForComparison($name) {
    return str_replace('?', '', $name);
}

/**
 * Levenshtein-Ähnlichkeit in Prozent berechnen
 */
function levenshteinSimilarity($str1, $str2) {
    // Entferne Fragezeichen für Vergleich
    $str1_clean = cleanNameForComparison($str1);
    $str2_clean = cleanNameForComparison($str2);
    
    $distance = levenshtein(strtolower($str1_clean), strtolower($str2_clean));
    $maxLen = max(strlen($str1_clean), strlen($str2_clean));
    if ($maxLen == 0) return 100;
    return round((1 - ($distance / $maxLen)) * 100);
}

/**
 * Bestimmt das Tirol-Archiv-Prefix für einen Nachnamen
 */
function getTirolArchivPrefix($nachname) {
    global $TIROL_ARCHIV_MAPPINGS;
    
    $lower = strtolower(cleanNameForComparison($nachname));
    
    // Prüfe längere spezielle Präfixe zuerst
    $specials = ['sch', 'sp', 'st', 'tz', 'tsch', 'ch'];
    foreach ($specials as $special) {
        if (strpos($lower, $special) === 0) {
            return isset($TIROL_ARCHIV_MAPPINGS[$special])
            ? $TIROL_ARCHIV_MAPPINGS[$special]
            : $special;
        }
    }
    
    // Erster Buchstabe
    $firstChar = strtolower(substr($nachname, 0, 1));
    
    return isset($TIROL_ARCHIV_MAPPINGS[$firstChar])
    ? $TIROL_ARCHIV_MAPPINGS[$firstChar]
    : $firstChar;
}

/**
 * Generiert die URL für einen bestimmten Prefix
 */
function getTirolArchivUrl($prefix) {
    if (empty($prefix)) {
        return '';
    }
    return TIROL_ARCHIV_BASE_URL . 'familiennamen-' . urlencode($prefix) . '/';
}

/**
 * Cache-Dateiname generieren
 */
function getTirolArchivCacheFile($prefix) {
    if (!TIROL_ARCHIV_CACHE_TTL || empty($prefix)) {
        return null;
    }
    
    if (!is_dir(TIROL_ARCHIV_CACHE_DIR)) {
        @mkdir(TIROL_ARCHIV_CACHE_DIR, 0755, true);
    }
    
    return TIROL_ARCHIV_CACHE_DIR . 'archiv-' . sanitizeFilename($prefix) . '.json';
}

/**
 * Sanitize Dateiname
 */
function sanitizeFilename($str) {
    return preg_replace('/[^a-z0-9-]/i', '_', $str);
}

/**
 * Cache laden (wenn verfügbar und noch gültig)
 */
function loadTirolArchivCache($prefix) {
    if (!TIROL_ARCHIV_CACHE_TTL || empty($prefix)) {
        return null;
    }
    
    $cacheFile = getTirolArchivCacheFile($prefix);
    
    if (!$cacheFile || !file_exists($cacheFile)) {
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
    if (!TIROL_ARCHIV_CACHE_TTL || empty($prefix)) {
        return;
    }
    
    $cacheFile = getTirolArchivCacheFile($prefix);
    if (!$cacheFile) {
        return;
    }
    
    @file_put_contents($cacheFile, json_encode($data), LOCK_EX);
}

/**
 * Prüfe ob eine URL erreichbar ist
 */
function isTirolArchivUrlAvailable($url) {
    if (empty($url)) {
        return false;
    }
    
    $context = stream_context_create([
        'http' => [
            'method' => 'HEAD',
            'timeout' => 5,
            'user_agent' => 'Mozilla/5.0'
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ]
    ]);
    
    try {
        $headers = @get_headers($url, 1, $context);
        
        if ($headers === false) {
            return false;
        }
        
        if (is_array($headers)) {
            $status = isset($headers[0]) ? $headers[0] : '';
        } else {
            $status = $headers;
        }
        
        return (strpos($status, '200') !== false ||
            strpos($status, '301') !== false ||
            strpos($status, '302') !== false);
        
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Holt und parsed Nachnamen vom Tirol-Archiv mit Ortsangaben
 * Gibt Array zurück: ['Name' => ['Ort1', 'Ort2', ...], ...]
 */
function getTirolArchivNamesWithPlaces($prefix) {
    if (empty($prefix)) {
        return [];
    }
    
    // Prüfe Cache
    $cached = loadTirolArchivCache($prefix);
    if ($cached !== null) {
        return $cached;
    }
    
    $url = getTirolArchivUrl($prefix);
    if (empty($url)) {
        return [];
    }
    
    // Prüfe ob URL verfügbar ist
    if (!isTirolArchivUrlAvailable($url)) {
        error_log('Tirol Archiv: URL nicht verfügbar - ' . $url);
        return [];
    }
    
    $names = [];
    
    try {
        $context = stream_context_create([
            'http' => [
                'timeout' => TIROL_ARCHIV_TIMEOUT,
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
                'follow_location' => true,
                'max_redirects' => 5
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ]
        ]);
        
        $html = @file_get_contents($url, false, $context);
        
        if ($html === false) {
            error_log('Tirol Archiv: Keine HTML erhalten für Prefix ' . $prefix);
            return [];
        }
        
        // Entferne Script und Style Tags
        $html = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $html);
        $html = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $html);
        
        // Parse Names mit verschiedenen Mustern
        $names = parseNamesFromHtml($html, $prefix);
        
        error_log('Tirol Archiv: ' . count($names) . ' Namen gefunden für Prefix ' . $prefix);
        
        // Speichere im Cache
        saveTirolArchivCache($prefix, $names);
        
    } catch (Exception $e) {
        error_log('Tirol Archiv Error: ' . $e->getMessage());
    }
    
    return $names;
}

/**
 * Parse Familiennamen aus HTML
 * Format: <p>Oberauer: Alpbach, Brandenberg, Gnadenwald, Innsbruck, ...</p>
 */
function parseNamesFromHtml($html, $prefix) {
    if (empty($html) || empty($prefix)) {
        return [];
    }
    
    $names = [];
    
    // Muster: <p>Name: Ort1, Ort2, Ort3</p>
    if (preg_match_all('/<p[^>]*>([^<]+)<\/p>/i', $html, $p_matches)) {
        foreach ($p_matches[1] as $p_content) {
            // Prüfe ob Doppelpunkt vorhanden ist
            if (strpos($p_content, ':') === false) {
                continue;
            }
            
            // Splitte bei Doppelpunkt
            $parts = explode(':', $p_content, 2);
            
            if (count($parts) !== 2) {
                continue;
            }
            
            $name_part = trim($parts[0]);
            $places_part = trim($parts[1]);
            
            // Überspringe wenn leer
            if (empty($name_part) || empty($places_part)) {
                continue;
            }
            
            // Decode HTML entities
            $name_part = html_entity_decode($name_part);
            $places_part = html_entity_decode($places_part);
            
            // Entferne Zahlen/Nummern am Anfang
            $name_part = preg_replace('/^\d+\.\s*/', '', $name_part);
            $name_part = trim($name_part);
            
            if (empty($name_part) || strlen($name_part) < 2) {
                continue;
            }
            
            // Parse Orte (komma-separiert)
            $places = array_map('trim', explode(',', $places_part));
            $places = array_filter($places); // Entferne leere Einträge
            
            // Validiere Name gegen Prefix
            $name_lower = strtolower(cleanNameForComparison($name_part));
            $prefix_lower = strtolower($prefix);
            
            // Prüfe Prefix-Match
            $valid = false;
            
            if ($prefix_lower === 'pq') {
                // P/Q Seite - erlaubt P und Q
                $valid = in_array($name_lower[0], ['p', 'q']);
            } elseif ($prefix_lower === 'xyz') {
                // X/Y/Z Seite - erlaubt X, Y und Z
                $valid = in_array($name_lower[0], ['x', 'y', 'z']);
            } elseif ($prefix_lower === 'sch') {
                $valid = strpos($name_lower, 'sch') === 0;
            } elseif ($prefix_lower === 'sp') {
                $valid = strpos($name_lower, 'sp') === 0;
            } elseif ($prefix_lower === 'st') {
                $valid = strpos($name_lower, 'st') === 0;
            } elseif (strlen($prefix_lower) === 1) {
                $valid = ($name_lower[0] === $prefix_lower);
            } else {
                $valid = strpos($name_lower, $prefix_lower) === 0;
            }
            
            if (!$valid) {
                continue;
            }
            
            // Überspringe sehr kurze Namen
            if (strlen($name_part) < 3 && !preg_match('/^[A-Z]{2,3}$/', $name_part)) {
                continue;
            }
            
            // Speichere Name mit Orten
            if (!isset($names[$name_part])) {
                $names[$name_part] = [];
            }
            $names[$name_part] = array_merge($names[$name_part], $places);
            $names[$name_part] = array_unique($names[$name_part]);
        }
    }
    
    ksort($names);
    return $names;
}

/**
 * Findet ähnliche Namen im Tirol-Archiv mit konfigurierbarer minimaler Ähnlichkeit
 */
function findSimilarNamesInArchive($nachname, $minSimilarity = TIROL_ARCHIV_MIN_SIMILARITY) {
    if (empty($nachname)) {
        return [];
    }
    
    $prefix = getTirolArchivPrefix($nachname);
    if (empty($prefix)) {
        return [];
    }
    
    $archiveNames = getTirolArchivNamesWithPlaces($prefix);
    
    if (empty($archiveNames)) {
        error_log('Tirol Archiv: Keine Namen in Archiv für Prefix ' . $prefix . ', suchte nach: ' . $nachname);
        return [];
    }
    
    $similar = [];
    foreach ($archiveNames as $archiveName => $places) {
        $similarity = levenshteinSimilarity($nachname, $archiveName);
        
        error_log('Tirol Archiv: Vergleich ' . $nachname . ' mit ' . $archiveName . ' = ' . $similarity . '%');
        
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