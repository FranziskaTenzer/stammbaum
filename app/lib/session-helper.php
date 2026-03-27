<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ===========================
// SESSION MANAGEMENT HELPER
// ===========================

define('ADMIN_PASSWORD', 'admin123');  // ⚠️ ÄNDERN SIE DIESES PASSWORT!

/**
 * Berechnet die Basis-URL der Anwendung dynamisch
 */
function getBaseUrl() {
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);
    $parts = explode('/', $script);
    $result = [];
    foreach ($parts as $part) {
        if ($part === 'app' || $part === 'public') break;
        $result[] = $part;
    }
    return rtrim(implode('/', $result), '/');
}

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
        header('Location: ' . getBaseUrl() . '/public/login.php');
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
