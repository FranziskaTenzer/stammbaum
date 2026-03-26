<?php

// Database connection parameters
$host = 'localhost'; // Change according to your database configuration
$db_name = 'your_database_name'; // Set your database name
$username = 'your_username'; // Set your database username
$password = 'your_password'; // Set your database password

// Create connection to the database
$conn = new mysqli($host, $username, $password, $db_name);

// Check connection
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

// Function to find similar names
function findSimilarNames($name) {
    global $conn;
    // SQL query to find names similar to the input
    $sql = "SELECT first_name, last_name, variation, church_book FROM person_table WHERE first_name LIKE ? OR last_name LIKE ?";
    $stmt = $conn->prepare($sql);
    $likeName = '%' . $conn->real_escape_string($name) . '%';
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

// Close connection
$conn->close();
?>