<?php

// Database configuration
$host = 'localhost';
$user = 'root';
$password = '';  // empty password
$database = 'stammbaum';

// Create connection
$conn = new mysqli($host, $user, $password, $database);

// Check connection
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

// echo 'Connected successfully';
?>