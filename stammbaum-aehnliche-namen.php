<?php

include 'KI-include.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);

$pdo = getPDO();

// Levenshtein distance function for similarity matching
function levenshteinSimilarity($str1, $str2) {
    $distance = levenshtein(strtolower($str1), strtolower($str2));
    $maxLen = max(strlen($str1), strlen($str2));
    if ($maxLen == 0) return 100;
    return round((1 - ($distance / $maxLen)) * 100);
}

// Get all unique first names with their similarity groups
function getSimilarVornamen($pdo) {
    $stmt = $pdo->prepare("SELECT DISTINCT vorname FROM person ORDER BY vorname");
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
        
        if (count($group) > 0) {
            $groups[] = $group;
            $processed[] = $name;
        }
    }
    
    return $groups;
}

// Get all unique last names with their similarity groups
function getSimilarNachnamen($pdo) {
    $stmt = $pdo->prepare("SELECT DISTINCT nachname FROM person ORDER BY nachname");
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
        
        if (count($group) > 0) {
            $groups[] = $group;
            $processed[] = $name;
        }
    }
    
    return $groups;
}

// Get Traubücher for a specific vorname
function getTraubueherForVorname($pdo, $vorname) {
    $sql = "
        SELECT DISTINCT ehe.traubuch
        FROM person
        JOIN ehe ON (person.id = ehe.vater_id OR person.id = ehe.mutter_id)
        WHERE person.vorname = ?
        AND ehe.traubuch IS NOT NULL
        ORDER BY ehe.traubuch
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$vorname]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Get Traubücher for a specific nachname
function getTraubueherForNachname($pdo, $nachname) {
    $sql = "
        SELECT DISTINCT ehe.traubuch
        FROM person
        JOIN ehe ON (person.id = ehe.vater_id OR person.id = ehe.mutter_id)
        WHERE person.nachname = ?
        AND ehe.traubuch IS NOT NULL
        ORDER BY ehe.traubuch
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$nachname]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
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
        .traubuch-list {
            margin: 10px 0 0 0;
            font-size: 0.95em;
            color: #666;
        }
        .traubuch-item {
            margin: 5px 0 0 0;
            padding: 6px 10px;
            background-color: #fff3cd;
            border-left: 3px solid #ff9800;
            display: block;
            border-radius: 3px;
            font-weight: 500;
            color: #856404;
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
        .count-badge {
            display: inline-block;
            background-color: #007bff;
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.85em;
            margin-left: 8px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="back-link">
            <a href="stammbaum.php">← Zurück zur Hauptseite</a>
        </div>
        
        <h1>📋 Ähnliche Namen im Stammbaum</h1>
        <div class="info-text">
            Diese Übersicht zeigt Namen mit ähnlicher Schreibweise (ab 80% Übereinstimmung) und listet die Traubücher auf, in denen sie vorkommen.
        </div>
        
        <!-- Vornamen Section -->
        <h2>👤 Ähnliche Vornamen</h2>
        <?php
        $vornamenGroups = getSimilarVornamen($pdo);
        $hasVornamen = false;
        
        foreach ($vornamenGroups as $group) {
            if (count($group) > 1) {
                $hasVornamen = true;
                echo "<div class='name-group'>";
                
                foreach ($group as $vorname) {
                    $traubuecher = getTraubueherForVorname($pdo, $vorname);
                    echo "<div class='name-variant'>";
                    echo "<strong>📝 " . htmlspecialchars($vorname) . "</strong>";
                    
                    if (count($traubuecher) > 0) {
                        echo "<div class='traubuch-list'>";
                        echo "<strong>Traubücher:</strong><br>";
                        foreach ($traubuecher as $tb) {
                            echo "<span class='traubuch-item'>" . htmlspecialchars($tb) . "</span>";
                        }
                        echo "</div>";
                    } else {
                        echo "<div class='traubuch-list'><em>Keine Traubücher zugeordnet</em></div>";
                    }
                    
                    echo "</div>";
                }
                
                echo "</div>";
            }
        }
        
        if (!$hasVornamen) {
            echo "<div class='no-results'>❌ Keine ähnlichen Vornamen gefunden.</div>";
        }
        ?>
        
        <!-- Nachnamen Section -->
        <h2>👨‍👩‍👧‍👦 Ähnliche Nachnamen</h2>
        <?php
        $nachnamenGroups = getSimilarNachnamen($pdo);
        $hasNachnamen = false;
        
        foreach ($nachnamenGroups as $group) {
            if (count($group) > 1) {
                $hasNachnamen = true;
                echo "<div class='name-group'>";
                
                foreach ($group as $nachname) {
                    $traubuecher = getTraubueherForNachname($pdo, $nachname);
                    echo "<div class='name-variant'>";
                    echo "<strong>📝 " . htmlspecialchars($nachname) . "</strong>";
                    
                    if (count($traubuecher) > 0) {
                        echo "<div class='traubuch-list'>";
                        echo "<strong>Traubücher:</strong><br>";
                        foreach ($traubuecher as $tb) {
                            echo "<span class='traubuch-item'>" . htmlspecialchars($tb) . "</span>";
                        }
                        echo "</div>";
                    } else {
                        echo "<div class='traubuch-list'><em>Keine Traubücher zugeordnet</em></div>";
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