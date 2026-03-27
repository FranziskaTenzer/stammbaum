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
 * Levenshtein-Ähnlichkeit in Prozent berechnen
 */
function levenshteinSimilarity($str1, $str2) {
    $distance = levenshtein(strtolower($str1), strtolower($str2));
    $maxLen = max(strlen($str1), strlen($str2));
    if ($maxLen == 0) return 100;
    return round((1 - ($distance / $maxLen)) * 100);
}

/**
 * Bestimmt das Tirol-Archiv-Prefix für einen Nachnamen
 * z.B. "Schmied" -> "sch", "Brenner" -> "b"
 */
function getTirolArchivPrefix($nachname) {
    $lower = strtolower($nachname);
    
    // Prüfe spezielle Präfixe (längere zuerst)
    $specials = ['sch', 'tz', 'tsch', 'ch'];
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
    if (strpos(strtolower($name), strtolower($prefix)) !== 0) {
        return null;
    }
    
    // Überspringe sehr kurze Namen (wahrscheinlich Fehler)
    if (strlen($name) < 3 && !preg_match('/^[A-Z]{2,3}$/', $name)) {
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
                'prefix' => $prefix
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
 */
function renderArchiveNamesBox($nachname, $minSimilarity = TIROL_ARCHIV_MIN_SIMILARITY) {
    $similar = findSimilarNamesInArchive($nachname, $minSimilarity);
    
    if (empty($similar)) {
        return '<div style="background:#fff3cd; border-left:4px solid #ffc107; padding:12px; margin:12px 0; border-radius:3px; font-size:0.9em; color:#856404;">
                    <strong>ℹ️ Tirol-Archiv:</strong> Keine Namen mit mind. ' . $minSimilarity . '% Ähnlichkeit gefunden
                </div>';
    }
    
    $html = '<div style="background:#e8f4f8; border:2px solid #0099cc; padding:12px; margin:12px 0; border-radius:3px; font-size:0.9em;">';
    $html .= '<strong style="color:#0099cc; font-size:1.05em;">📚 Tirol-Archiv Familiennamen (' . $minSimilarity . '%+ Ähnlichkeit)</strong><br>';
    $html .= '<small style="color:#0c5460; display:block; margin-top:8px;">';
    
    foreach ($similar as $item) {
        $match = '';
        if ($item['similarity'] == 100) {
            $match = '✅ Exakt';
        } elseif ($item['similarity'] >= 95) {
            $match = '🟢 ' . $item['similarity'] . '% sehr ähnlich';
        } elseif ($item['similarity'] >= 90) {
            $match = '🟡 ' . $item['similarity'] . '% ähnlich';
        } else {
            $match = '🔵 ' . $item['similarity'] . '% ähnlich';
        }
        
        $html .= '<div style="padding:10px; background:#d1ecf1; margin:8px 0; border-radius:3px; border-left:4px solid #0099cc;">';
        $html .= '<div style="margin-bottom:6px;">';
        $html .= '<strong style="color:#0c5460; font-size:1em;">' . htmlspecialchars($item['name']) . '</strong> ';
        $html .= '<span style="color:#555; font-size:0.9em;">' . $match . '</span>';
        $html .= '</div>';
        
        if (!empty($item['places'])) {
            $html .= '<div style="color:#555; font-size:0.85em; margin-top:4px;">';
            $html .= '<strong>Orte:</strong> ';
            $html .= htmlspecialchars(implode(', ', $item['places']));
            $html .= '</div>';
        }
        
        $html .= '</div>';
    }
    
    $html .= '</small>';
    $html .= '</div>';
    
    return $html;
}

?>