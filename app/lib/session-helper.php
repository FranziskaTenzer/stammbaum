<?php

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ===========================
// SESSION MANAGEMENT HELPER
// ===========================

define('ADMIN_PASSWORD', 'admin123');  // ⚠️ ÄNDERN SIE DIESES PASSWORT!

/**
 * Prüft ob Benutzer angemeldet ist
 */
function isLoggedIn() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

/**
 * Erzwingt Login - leitet zur Login-Seite um wenn nicht angemeldet
 */
function requireLogin() {
    if (!isLoggedIn()) {
        // Compute path to public/login.php relative to the web root
        $docRoot = rtrim(str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'])), '/');
        $projectPath = str_replace('\\', '/', dirname(dirname(realpath(__DIR__))));
        $projectUrl = rtrim(str_replace($docRoot, '', $projectPath), '/');
        header('Location: ' . $projectUrl . '/public/login.php');
        exit;
    }
}

/**
 * Gibt den aktuellen Benutzernamen zurück
 */
function getCurrentUser() {
    return isset($_SESSION['username']) ? $_SESSION['username'] : 'Guest';
}

/**
 * Prüft ob Admin-Rechte vorhanden sind
 */
function isAdmin() {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
}

/**
 * Prüft ob Benutzer Admin-Passwort korrekt eingegeben hat
 */
function checkAdminPassword($password) {
    return $password === ADMIN_PASSWORD;
}

/**
 * Meldet Benutzer an
 */
function loginUser($username) {
    $_SESSION['logged_in'] = true;
    $_SESSION['username'] = $username;
    $_SESSION['login_time'] = time();
}

/**
 * Meldet Benutzer als Admin an
 */
function loginAsAdmin($username) {
    loginUser($username);
    $_SESSION['is_admin'] = true;
}
