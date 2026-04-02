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

// Minimale Ähnlichkeit für Debug-Anzeige (in Prozent)
define('TIROL_ARCHIV_DEBUG_MIN_SIMILARITY', 30);

// Cache-Verzeichnis (unter dem Projektverzeichnis)
define('TIROL_ARCHIV_CACHE_DIR', dirname(dirname(__DIR__)) . '/cache/tirol-archiv/');

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
 * Prüft, ob ein erkannter Namens-Prefix zur angeforderten Archivseite passt.
 * Gruppenseiten wie pq oder xyz enthalten mehrere Initialen.
 */
function tirolArchivPrefixMatches($namePrefix, $requestedPrefix) {
    if ($namePrefix === $requestedPrefix) {
        return true;
    }

    if ($requestedPrefix === 'pq' && in_array($namePrefix, ['p', 'q'], true)) {
        return true;
    }

    if ($requestedPrefix === 'xyz' && in_array($namePrefix, ['x', 'y', 'z'], true)) {
        return true;
    }

    return false;
}

/**
 * Generiert die korrekte URL für einen bestimmten Prefix
 * Berücksichtigt Sonderfälle:
 * - x, y, z -> xyz
 * - p, q -> pq
 * - sch, sp, st -> eigene Seiten
 */
function getTirolArchivUrl($prefix) {
    // X, Y, Z werden auf die xyz-Seite umgeleitet
    if (in_array($prefix, ['x', 'y', 'z'])) {
        return TIROL_ARCHIV_BASE_URL . 'familiennamen-xyz/';
    }
    
    // P und Q werden zusammen auf pq-Seite umgeleitet
    if (in_array($prefix, ['p', 'q'])) {
        return TIROL_ARCHIV_BASE_URL . 'familiennamen-pq/';
    }
    
    // Sch, Sp, St haben eigene Seiten
    if (in_array($prefix, ['sch', 'sp', 'st'])) {
        return TIROL_ARCHIV_BASE_URL . 'familiennamen-' . $prefix . '/';
    }
    
    // Standard: familiennamen-X/
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
        // Historische Recovery: pq/xyz konnten früher fälschlich als leer gecacht werden.
        if (empty($cached) && in_array($prefix, ['pq', 'xyz'], true)) {
            // Mit Live-Daten neu aufbauen statt leeren Alt-Cache zu übernehmen.
        } else {
            return $cached;
        }
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
 * Versucht mehrere Formate
 */
function parseNamesFromHtml($html, $prefix) {
    $names = [];
    
    // Muster 1: <li>Name (Ort1, Ort2)</li> oder <li>Name: Ort1, Ort2</li>
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
    
    // Muster 2: <p>Name: Ort1, Ort2, Ort3</p>
    if (empty($names) || count($names) < 5) {
        if (preg_match_all('/<p[^>]*>([^<]+)<\/p>/i', $html, $matches)) {
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
    
    // Muster 3: Direkter Text "Name: Ort1, Ort2, Ort3" (auch über mehrere Zeilen)
    // Laeuft IMMER als zuverlaessige Ergaenzung zu Muster 1+2, weil Muster 2 bei
    // <p>-Eintraegen mit inneren <br>-Tags (mehrzeilige Ortslisten) scheitert.
    {
        // Entferne HTML-Tags, behalte aber Block-Struktur. Das Tirol-Archiv nutzt
        // innerhalb einzelner <p>-Eintraege teils Tags wie <abbr>; ohne explizite
        // Zeilenumbrueche vor strip_tags() kleben mehrere Namen zusammen.
        $textHtml = preg_replace('/<\s*br\s*\/?>/i', "\n", $html);
        $textHtml = preg_replace('/<\s*\/\s*(p|li|div|h1|h2|h3|h4|h5|h6)\s*>/i', "\n", $textHtml);
        $textHtml = strip_tags($textHtml);
        $textHtml = html_entity_decode($textHtml);
        $textHtml = preg_replace('/\r\n?|\r/u', "\n", $textHtml);
        
        // Suche nach "Name: Orte" Muster
        // Berücksichtigt auch Namen mit Umlauten und Bindestrichen
        if (preg_match_all('/^([A-ZÄÖÜ][A-Za-zÄÖÜäöüß\-\']+)\s*:\s*(.+?)(?=\n[A-ZÄÖÜ]|\n$|$)/msu', $textHtml, $matches)) {
            for ($i = 0; $i < count($matches[0]); $i++) {
                $name = trim($matches[1][$i]);
                $placesStr = trim($matches[2][$i]);
                
                // Prüfe ob Name mit richtigem Prefix anfängt
                if (strlen($name) >= 2 && tirolArchivPrefixMatches(getTirolArchivPrefix($name), $prefix)) {
                    $places = array_map('trim', explode(',', $placesStr));
                    $places = array_filter($places, function($p) {
                        return !empty($p) && strlen($p) > 1;
                    });
                        
                    if (count($places) > 0) {
                        if (!isset($names[$name])) {
                            $names[$name] = [];
                        }
                        $names[$name] = array_merge($names[$name], $places);
                        $names[$name] = array_unique($names[$name]);
                    }
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
    
    // Muster 1: "Name: (Orte)"
    if (preg_match('/^([^(:\n]+?)\s*:\s*\(([^)]+)\)/', $text, $m)) {
        $name = trim($m[1]);
        $places = array_map('trim', explode(',', $m[2]));
        $places = array_filter($places);
    }
    // Muster 2: "Name: Ort1, Ort2, Ort3"
    elseif (preg_match('/^([^(:\n]+?)\s*:\s*([^(]+)$/', $text, $m)) {
        $name = trim($m[1]);
        $placesStr = trim($m[2]);
        $places = array_map('trim', explode(',', $placesStr));
        $places = array_filter($places, function($p) {
            return !empty($p) && strlen($p) > 1;
        });
    }
    // Muster 3: "Name (Ort1, Ort2)"
    elseif (preg_match('/^([^(]+)\s*\(([^)]+)\)/', $text, $m)) {
        $name = trim($m[1]);
        $places = array_map('trim', explode(',', $m[2]));
        $places = array_filter($places);
    }
    // Muster 4: "Name – Ort1, Ort2" oder "Name - Ort1, Ort2"
    elseif (preg_match('/^([^–\-\n]+)\s*[–\-]\s*(.+)$/', $text, $m)) {
        $name = trim($m[1]);
        $places = array_map('trim', explode(',', $m[2]));
        $places = array_filter($places);
    }
    
    // Validiere Namen
    if (empty($name) || strlen($name) < 2) {
        return null;
    }
    
    // Entferne evtl. Doppelpunkte
    $name = trim($name, ': ');
    
    // Prüfe, ob Name mit dem richtigen Prefix anfängt
    if (!tirolArchivPrefixMatches(getTirolArchivPrefix($name), $prefix)) {
        return null;
    }
    
    // Überspringe sehr kurze Namen (wahrscheinlich Fehler)
    if (strlen(cleanNameForComparison($name)) < 3 && !preg_match('/^[A-Z]{2,3}$/', $name)) {
        return null;
    }
    
    // Muss mindestens einen Ort haben
    if (empty($places)) {
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
 * Findet alle Namen mit Ähnlichkeit - auch unter der Mindesthöhe
 * Für Debug-Zwecke - mit konfigurierbarer Mindesthöhe
 */
function findAllSimilarNamesInArchive($nachname, $minSimilarity = TIROL_ARCHIV_DEBUG_MIN_SIMILARITY) {
    $prefix = getTirolArchivPrefix($nachname);
    $archiveNames = getTirolArchivNamesWithPlaces($prefix);
    
    if (empty($archiveNames)) {
        return [];
    }
    
    $allSimilar = [];
    foreach ($archiveNames as $archiveName => $places) {
        $similarity = levenshteinSimilarity($nachname, $archiveName);
        
        // Nur Namen mit mindestens 30% Ähnlichkeit anzeigen
        if ($similarity >= $minSimilarity) {
            $allSimilar[] = [
                'name' => $archiveName,
                'similarity' => $similarity,
                'places' => $places,
                'prefix' => $prefix,
                'searchedName' => $nachname
            ];
        }
    }
    
    // Sortiere nach Ähnlichkeit (absteigend)
    usort($allSimilar, function($a, $b) {
        return $b['similarity'] - $a['similarity'];
    });
        
        return $allSimilar;
}

/**
 * Findet ähnliche Namen für EINE KOMPLETTE GRUPPE im Tirol-Archiv
 * Durchsucht JEDEN Namen der Gruppe einzeln
 * Gibt ein dedupliziertes Array sortiert nach Ähnlichkeit zurück
 */
function findSimilarNamesInArchiveForGroup($groupNames, $minSimilarity = TIROL_ARCHIV_MIN_SIMILARITY) {
    if (empty($groupNames)) {
        return [];
    }
    
    // Stelle sicher, dass $groupNames ein Array ist
    if (!is_array($groupNames)) {
        $groupNames = [$groupNames];
    }
    
    // Sammle Ergebnisse für JEDEN Namen einzeln in der Gruppe
    $allSimilar = [];
    
    foreach ($groupNames as $nachname) {
        $similar = findSimilarNamesInArchive($nachname, $minSimilarity);
        
        // Speichere jeden Match mit Info welcher Gruppennamen ihn gefunden hat
        foreach ($similar as $match) {
            $match['foundBy'] = $nachname; // Welcher Name der Gruppe hat das gefunden?
            $allSimilar[] = $match;
        }
    }
    
    // Deduplizieren: Behalte den besten Match pro Archiv-Name
    $dedup = [];
    foreach ($allSimilar as $item) {
        $key = $item['name'];
        if (!isset($dedup[$key]) || $item['similarity'] > $dedup[$key]['similarity']) {
            $dedup[$key] = $item;
        }
    }
    
    // Konvertiere zurück zu indiziertem Array
    $dedup = array_values($dedup);
    
    // Sortiere nach Ähnlichkeit (absteigend)
    usort($dedup, function($a, $b) {
        return $b['similarity'] - $a['similarity'];
    });
        
        return $dedup;
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
    $prefix = getTirolArchivPrefix($nachname);
    $archiveUrl = getTirolArchivUrl($prefix);
    
    // Für Debug: Alle Namen mit Ähnlichkeit laden (min. 30%)
    $allNames = findAllSimilarNamesInArchive($nachname, TIROL_ARCHIV_DEBUG_MIN_SIMILARITY);
    
    if (empty($similar)) {
        // Debug-Modus: Zeige alle Namen mit ihrer Ähnlichkeit (>= 30%)
        $html = '<div style="background:#fff3cd; border-left:4px solid #ffc107; padding:14px; margin:12px 0; border-radius:3px; font-size:0.9em; color:#856404;">';
        $html .= '<strong>ℹ️ Tirol-Archiv:</strong> Keine Namen mit mind. ' . $minSimilarity . '% Ähnlichkeit gefunden<br>';
        
        $html .= '<div style="margin-top:12px; padding:10px; background:#fffbeb; border-radius:3px; font-size:0.85em;">';
        $html .= '<strong>🔍 Debug-Informationen:</strong><br>';
        $html .= '<strong>Gesuchter Name:</strong> <span style="font-family:monospace; background:#fff9e6; padding:2px 4px; border-radius:2px;">' . htmlspecialchars($nachname, ENT_QUOTES, 'UTF-8') . '</span><br>';
        $html .= '<strong>Prefix:</strong> <span style="font-family:monospace; background:#fff9e6; padding:2px 4px; border-radius:2px;">' . htmlspecialchars($prefix, ENT_QUOTES, 'UTF-8') . '</span><br>';
        $html .= '<strong>Mindest-Ähnlichkeit (Match):</strong> ' . $minSimilarity . '%<br>';
        $html .= '<strong>Debug-Schwelle:</strong> ' . TIROL_ARCHIV_DEBUG_MIN_SIMILARITY . '%<br>';
        $html .= '</div>';
        
        if (!empty($allNames)) {
            $html .= '<div style="margin-top:12px; padding:10px; background:#fffbeb; border-radius:3px; font-size:0.85em;">';
            $html .= '<strong>📊 Top 15 Namen auf Archiv-Seite (ab ' . TIROL_ARCHIV_DEBUG_MIN_SIMILARITY . '% Ähnlichkeit):</strong><br>';
            $html .= '<table style="width:100%; border-collapse:collapse; margin-top:6px; font-size:0.9em;">';
            $html .= '<thead style="border-bottom:2px solid #856404;">';
            $html .= '<tr style="text-align:left;"><th style="padding:4px; font-weight:bold;">Name</th><th style="padding:4px; text-align:right; font-weight:bold;">Ähnlichkeit</th></tr>';
            $html .= '</thead>';
            $html .= '<tbody>';
            
            $counter = 0;
            foreach ($allNames as $item) {
                if ($counter >= 15) break;
                
                $sim = intval($item['similarity']);
                $bgColor = '';
                $icon = '';
                
                if ($sim >= 80) {
                    $bgColor = '#c6efce'; // Grün
                    $icon = '✅';
                } elseif ($sim >= 70) {
                    $bgColor = '#ffeb9c'; // Orange
                    $icon = '⚠️';
                } elseif ($sim >= 60) {
                    $bgColor = '#ffc7ce'; // Rosa
                    $icon = '❌';
                } else {
                    $bgColor = '#f0f0f0'; // Grau
                    $icon = '—';
                }
                
                $html .= '<tr style="background:' . $bgColor . '; border-bottom:1px solid #ddd;">';
                $html .= '<td style="padding:6px; font-family:monospace;">' . $icon . ' ' . htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') . '</td>';
                $html .= '<td style="padding:6px; text-align:right; font-weight:bold;">' . $sim . '%</td>';
                $html .= '</tr>';
                $counter++;
            }
            
            $html .= '</tbody>';
            $html .= '</table>';
            
            if (count($allNames) > 15) {
                $html .= '<small style="color:#666; margin-top:6px; display:block;">... und ' . (count($allNames) - 15) . ' weitere Namen</small>';
            }
            $html .= '</div>';
        } else {
            $html .= '<div style="margin-top:12px; padding:10px; background:#fffbeb; border-radius:3px; color:#d32f2f;">';
            $html .= '<strong>⚠️ Keine Namen auf der Archiv-Seite gefunden (auch nicht mit ' . TIROL_ARCHIV_DEBUG_MIN_SIMILARITY . '%)!</strong><br>';
            $html .= 'Dies könnte bedeuten:<br>';
            $html .= '• Der Prefix ist möglicherweise falsch<br>';
            $html .= '• Die Seite konnte nicht geladen werden (SSL/Timeout)<br>';
            $html .= '• Das HTML-Format wird nicht erkannt';
            $html .= '</div>';
        }
        
        $html .= '<div style="margin-top:12px; padding:10px; background:#e8f5e9; border-radius:3px; font-size:0.9em;">';
        $html .= '<strong>📖 Zur Archiv-Seite:</strong> ';
        $html .= '<a href="' . htmlspecialchars($archiveUrl, ENT_QUOTES, 'UTF-8') . '" target="_blank" style="color:#2e7d32; text-decoration:underline; word-break:break-all;">';
        $html .= htmlspecialchars($archiveUrl, ENT_QUOTES, 'UTF-8') . '</a>';
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
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
        if (!empty($similar[0]['places']) && is_array($similar[0]['places'])) {
            $placesStr = implode(', ', array_map(function($p) {
                return htmlspecialchars($p, ENT_QUOTES, 'UTF-8');
            }, $similar[0]['places']));
            $html .= ' - Orte: <strong>' . $placesStr . '</strong>';
        }
    }
    $html .= '<br><small><a href="' . htmlspecialchars($archiveUrl, ENT_QUOTES, 'UTF-8') . '" target="_blank" style="color:#0099cc;">Zur Archiv-Seite ➔</a></small>';
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

/**
 * Gibt HTML für eine Ähnliche-Namen-Box für eine GRUPPE zurück
 * Durchsucht JEDEN Namen der Gruppe im Tirol-Archiv
 * Box ist standardmäßig eingeklappt
 */
function getArchiveGroupMatchSummary($groupNames, $minSimilarity = TIROL_ARCHIV_MIN_SIMILARITY) {
    static $cache = [];

    if (empty($groupNames)) {
        return [
            'similar' => [],
            'exactMatches' => [],
            'variantMatches' => [],
            'prefix' => '',
            'archiveUrl' => '',
        ];
    }

    if (!is_array($groupNames)) {
        $groupNames = [$groupNames];
    }

    $cacheKey = md5(json_encode([$groupNames, intval($minSimilarity)]));
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $similar = findSimilarNamesInArchiveForGroup($groupNames, $minSimilarity);
    $prefix = getTirolArchivPrefix($groupNames[0]);
    $archiveUrl = getTirolArchivUrl($prefix);

    $exactMatches = array_values(array_filter($similar, function($item) {
        return isset($item['similarity']) && intval($item['similarity']) === 100;
    }));
    $variantMatches = array_values(array_filter($similar, function($item) {
        return isset($item['similarity']) && intval($item['similarity']) < 100;
    }));

    $cache[$cacheKey] = [
        'similar' => $similar,
        'exactMatches' => $exactMatches,
        'variantMatches' => $variantMatches,
        'prefix' => $prefix,
        'archiveUrl' => $archiveUrl,
    ];

    return $cache[$cacheKey];
}

function hasArchiveVariantMatchesForGroup($groupNames, $minSimilarity = TIROL_ARCHIV_MIN_SIMILARITY) {
    $summary = getArchiveGroupMatchSummary($groupNames, $minSimilarity);
    return !empty($summary['variantMatches']) || empty($summary['similar']);
}

function renderArchiveNamesBoxForGroup($groupNames, $minSimilarity = TIROL_ARCHIV_MIN_SIMILARITY) {
    if (empty($groupNames)) {
        return '';
    }
    
    if (!is_array($groupNames)) {
        $groupNames = [$groupNames];
    }
    
    $summary = getArchiveGroupMatchSummary($groupNames, $minSimilarity);
    $similar = $summary['similar'];
    $exactMatches = $summary['exactMatches'];
    $variantMatches = $summary['variantMatches'];
    $prefix = $summary['prefix'];
    $archiveUrl = $summary['archiveUrl'];

    if (empty($variantMatches) && !empty($exactMatches)) {
        return '';
    }
    
    // Für Debug: Alle Namen mit Ähnlichkeit laden (min. 30%, für JEDEN Namen der Gruppe)
    $allNamesDebug = [];
    foreach ($groupNames as $name) {
        $allNamesDebug = array_merge($allNamesDebug, findAllSimilarNamesInArchive($name, TIROL_ARCHIV_DEBUG_MIN_SIMILARITY));
    }
    
    // Deduplizieren für Debug-Anzeige
    $dedup = [];
    foreach ($allNamesDebug as $item) {
        $key = $item['name'];
        if (!isset($dedup[$key]) || $item['similarity'] > $dedup[$key]['similarity']) {
            $dedup[$key] = $item;
        }
    }
    usort($dedup, function($a, $b) {
        return $b['similarity'] - $a['similarity'];
    });
        
        $groupLabel = implode(', ', array_map('htmlspecialchars', $groupNames));
        
        if (empty($similar)) {
            // Debug-Modus: Zeige alle Namen mit ihrer Ähnlichkeit (>= 30%)
            $html = '<div style="background:#fff3cd; border-left:4px solid #ffc107; padding:14px; margin:12px 0; border-radius:3px; font-size:0.9em; color:#856404;">';
            $html .= '<strong>ℹ️ Tirol-Archiv:</strong> Keine Namen mit mind. ' . $minSimilarity . '% Ähnlichkeit für die Gruppe gefunden<br>';
            
            $html .= '<div style="margin-top:12px; padding:10px; background:#fffbeb; border-radius:3px; font-size:0.85em;">';
            $html .= '<strong>🔍 Debug-Informationen:</strong><br>';
            $html .= '<strong>Gesuchte Gruppe:</strong> <span style="font-family:monospace; background:#fff9e6; padding:2px 4px; border-radius:2px;">' . $groupLabel . '</span><br>';
            $html .= '<strong>Prefix:</strong> <span style="font-family:monospace; background:#fff9e6; padding:2px 4px; border-radius:2px;">' . htmlspecialchars($prefix, ENT_QUOTES, 'UTF-8') . '</span><br>';
            $html .= '<strong>Mindest-Ähnlichkeit (Match):</strong> ' . $minSimilarity . '%<br>';
            $html .= '<strong>Debug-Schwelle:</strong> ' . TIROL_ARCHIV_DEBUG_MIN_SIMILARITY . '%<br>';
            $html .= '</div>';
            
            $html .= '<div style="margin-top:12px; padding:10px; background:#fffbeb; border-radius:3px; font-size:0.85em;">';
            $html .= '<strong>📋 Pro Name durchsucht:</strong><br>';
            foreach ($groupNames as $gname) {
                $results = findAllSimilarNamesInArchive($gname, TIROL_ARCHIV_DEBUG_MIN_SIMILARITY);
                $bestMatch = !empty($results) ? $results[0] : null;
                if ($bestMatch) {
                    $html .= '<em style="display:block; margin-top:4px;">' . htmlspecialchars($gname, ENT_QUOTES, 'UTF-8') . ': ✅ Beste Übereinstimmung = <strong>' . htmlspecialchars($bestMatch['name'], ENT_QUOTES, 'UTF-8') . '</strong> (' . intval($bestMatch['similarity']) . '%)</em>';
                } else {
                    $html .= '<em style="display:block; margin-top:4px; color:#d32f2f;">' . htmlspecialchars($gname, ENT_QUOTES, 'UTF-8') . ': ❌ Keine Namen gefunden (auch nicht mit ' . TIROL_ARCHIV_DEBUG_MIN_SIMILARITY . '%)</em>';
                }
            }
            $html .= '</div>';
            
            if (!empty($dedup)) {
                $html .= '<div style="margin-top:12px; padding:10px; background:#fffbeb; border-radius:3px; font-size:0.85em;">';
                $html .= '<strong>📊 Top 20 Namen auf Archiv-Seite (ab ' . TIROL_ARCHIV_DEBUG_MIN_SIMILARITY . '% Ähnlichkeit):</strong><br>';
                $html .= '<table style="width:100%; border-collapse:collapse; margin-top:6px; font-size:0.9em;">';
                $html .= '<thead style="border-bottom:2px solid #856404;">';
                $html .= '<tr style="text-align:left;"><th style="padding:4px; font-weight:bold;">Name</th><th style="padding:4px; text-align:right; font-weight:bold;">Ähnlichkeit</th></tr>';
                $html .= '</thead>';
                $html .= '<tbody>';
                
                $counter = 0;
                foreach ($dedup as $item) {
                    if ($counter >= 20) break;
                    
                    $sim = intval($item['similarity']);
                    $bgColor = '';
                    $icon = '';
                    
                    if ($sim >= 80) {
                        $bgColor = '#c6efce'; // Grün
                        $icon = '✅';
                    } elseif ($sim >= 70) {
                        $bgColor = '#ffeb9c'; // Orange
                        $icon = '⚠️';
                    } elseif ($sim >= 60) {
                        $bgColor = '#ffc7ce'; // Rosa
                        $icon = '❌';
                    } else {
                        $bgColor = '#f0f0f0'; // Grau
                        $icon = '—';
                    }
                    
                    $html .= '<tr style="background:' . $bgColor . '; border-bottom:1px solid #ddd;">';
                    $html .= '<td style="padding:6px; font-family:monospace;">' . $icon . ' ' . htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') . '</td>';
                    $html .= '<td style="padding:6px; text-align:right; font-weight:bold;">' . $sim . '%</td>';
                    $html .= '</tr>';
                    $counter++;
                }
                
                $html .= '</tbody>';
                $html .= '</table>';
                
                if (count($dedup) > 20) {
                    $html .= '<small style="color:#666; margin-top:6px; display:block;">... und ' . (count($dedup) - 20) . ' weitere Namen</small>';
                }
                $html .= '</div>';
            } else {
                $html .= '<div style="margin-top:12px; padding:10px; background:#fffbeb; border-radius:3px; color:#d32f2f;">';
                $html .= '<strong>⚠️ Keine Namen auf der Archiv-Seite gefunden (auch nicht mit ' . TIROL_ARCHIV_DEBUG_MIN_SIMILARITY . '%)!</strong><br>';
                $html .= 'Dies könnte bedeuten:<br>';
                $html .= '• Der Prefix ist möglicherweise falsch<br>';
                $html .= '• Die Seite konnte nicht geladen werden (SSL/Timeout)<br>';
                $html .= '• Das HTML-Format wird nicht erkannt';
                $html .= '</div>';
            }
            
            $html .= '<div style="margin-top:12px; padding:10px; background:#e8f5e9; border-radius:3px; font-size:0.9em;">';
            $html .= '<strong>📖 Zur Archiv-Seite:</strong> ';
            $html .= '<a href="' . htmlspecialchars($archiveUrl, ENT_QUOTES, 'UTF-8') . '" target="_blank" style="color:#2e7d32; text-decoration:underline; word-break:break-all;">';
            $html .= htmlspecialchars($archiveUrl, ENT_QUOTES, 'UTF-8') . '</a>';
            $html .= '</div>';
            $html .= '</div>';
            
            return $html;
        }
        
        $boxId = 'tirol-archive-group-' . md5(implode('-', $groupNames));
        $contentId = 'tirol-archive-group-content-' . md5(implode('-', $groupNames));
        
        $html = '<div style="background:#e8f4f8; border:2px solid #0099cc; padding:14px; margin:12px 0; border-radius:3px;">';
        
        // Kopfzeile mit Toggle
        $html .= '<div style="cursor:pointer; display:flex; align-items:center; justify-content:space-between;" onclick="toggleTirolArchive(\'' . $boxId . '\', \'' . $contentId . '\');">';
        $html .= '<strong style="color:#0099cc; font-size:1.1em;">📚 Tirol-Archiv Familiennamen - Gruppe: <em>' . $groupLabel . '</em></strong>';
        $html .= '<span id="' . $boxId . '-icon" style="color:#0099cc; font-size:1.2em; transition:transform 0.3s; display:inline-block;">▶</span>';
        $html .= '</div>';
        
        // Zusammenfassung (immer sichtbar)
        $html .= '<div style="color:#0c5460; font-size:0.9em; margin-top:8px;">';
        $html .= '<strong>' . count($variantMatches) . ' Varianten (< 100%) gefunden</strong>';
        if (!empty($variantMatches)) {
            $html .= ' - Beste Variante: <strong>' . htmlspecialchars($variantMatches[0]['name'], ENT_QUOTES, 'UTF-8') . '</strong> (' . intval($variantMatches[0]['similarity']) . '%)';
            if (!empty($variantMatches[0]['places']) && is_array($variantMatches[0]['places'])) {
                $placesStr = implode(', ', array_map(function($p) {
                    return htmlspecialchars($p, ENT_QUOTES, 'UTF-8');
                }, $variantMatches[0]['places']));
                $html .= ' - Orte: <strong>' . $placesStr . '</strong>';
            }
        }
        if (!empty($exactMatches)) {
            $html .= '<br><span style="color:#2e7d32;"><strong>Exakte Treffer (100%):</strong> ' . count($exactMatches) . ' gefunden</span>';
        }
        $html .= '<br><small><a href="' . htmlspecialchars($archiveUrl, ENT_QUOTES, 'UTF-8') . '" target="_blank" style="color:#0099cc;">Zur Archiv-Seite ➔</a></small>';
        $html .= '</div>';
        
        // Inhalt (eingeklappt)
        $html .= '<div id="' . $contentId . '" style="display:none; margin-top:14px; padding-top:14px; border-top:2px solid #0099cc;">';
        $html .= '<small style="color:#0c5460;">';

        if (!empty($exactMatches)) {
            $exactNames = implode(', ', array_map(function($item) {
                return htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8');
            }, $exactMatches));
            $html .= '<div style="padding:10px; background:#e8f5e9; margin:8px 0 12px 0; border-radius:3px; border-left:4px solid #2e7d32;">';
            $html .= '<strong>✅ Exakt gefunden (100%):</strong> ' . $exactNames;
            $html .= '</div>';
        }

        if (empty($variantMatches)) {
            $html .= '<div style="padding:10px; background:#e8f5e9; margin:8px 0; border-radius:3px; border-left:4px solid #2e7d32;">';
            $html .= '<strong>Keine Varianten unter 100% gefunden.</strong>';
            $html .= '</div>';
        }
        
        foreach ($variantMatches as $item) {
            if (!isset($item['similarity']) || !isset($item['name'])) {
                continue;
            }
            
            $sim = intval($item['similarity']);
            if ($sim >= 95) {
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
            
            // Vergleichsinfo - zeige welcher Name der Gruppe das Match gefunden hat
            $html .= '<div style="color:#0c5460; font-size:0.85em; background:#ffffff; padding:6px 8px; border-radius:2px; margin-bottom:8px; border-left:3px solid #0099cc;">';
            $html .= '<em>Ähnlichkeit zur Gruppe: ' . $sim . '%</em>';
            if (!empty($item['foundBy'])) {
                $html .= '<br><small style="color:#666;">Gefunden durch: ' . htmlspecialchars($item['foundBy'], ENT_QUOTES, 'UTF-8') . '</small>';
            }
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