<?php
session_start();

require_once 'session-helper.php';

// Destroy session
session_destroy();

// Redirect to login
header('Location: login.php');
exit;
?>