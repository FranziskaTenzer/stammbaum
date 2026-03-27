<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../app/lib/session-helper.php';

// Destroy session
session_destroy();

// Redirect to login
header('Location: login.php');
exit;
