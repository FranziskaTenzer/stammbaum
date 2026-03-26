<?php

include 'KI-include.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);

$DEBUG = true;

function debug($msg) {
    global $DEBUG;
    if ($DEBUG) echo "<div style='color:#555'>$msg</div>";
}

$pdo = getPDO();

// Function to find similar names
function findSimilarNames($name) {
    global $pdo;
    // SQL query to find names similar to the input
    $sql = "SELECT first_name, last_name, variation, church_book FROM person_table WHERE first_name LIKE ? OR last_name LIKE ?";
    $stmt = $pdo->prepare($sql);
    $likeName = '%' . $pdo->real_escape_string($name) . '%';
    $stmt->bind_param('ss', $likeName, $likeName);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Fetching all similar names
    $similarNames = [];
    while ($row = $result->fetch_assoc()) {
        $similarNames[] = $row;
    }
    $stmt->close();
    return $similarNames;
}

// Example usage
$nameToSearch = 'example_name'; // Change to input name for search
$results = findSimilarNames($nameToSearch);

// Display results
if (count($results) > 0) {
    echo '<h1>Similar Names</h1>';
    foreach ($results as $record) {
        echo '<p>' . htmlentities($record['first_name']) . ' ' . htmlentities($record['last_name']) . ' - Variation: ' . htmlentities($record['variation']) . ', Found in: ' . htmlentities($record['church_book']) . '</p>';
    }
} else {
    echo '<p>No similar names found.</p>';
}

?>