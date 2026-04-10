<?php

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ===========================
// SESSION MANAGEMENT HELPER
// ===========================

define('ADMIN_PASSWORD', 'admin123');  // ⚠️ ÄNDERN SIE DIESES PASSWORT!
define('ROLE_USER', 0);
define('ROLE_ADMIN', 1);
define('ROLE_SUPER_ADMIN', 2);

/**
 * Prüft ob Benutzer angemeldet ist
 */
function isLoggedIn() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

/**
 * Liefert die Admin-Rolle als Integer (0=User, 1=Admin, 2=Super Admin)
 */
function getAdminRole() {
    if (!isset($_SESSION['is_admin'])) {
        return ROLE_USER;
    }

    $rawRole = $_SESSION['is_admin'];
    if ($rawRole === true) {
        return ROLE_ADMIN;
    }
    if ($rawRole === false || $rawRole === null) {
        return ROLE_USER;
    }

    $role = (int) $rawRole;
    if ($role < ROLE_USER) {
        return ROLE_USER;
    }
    if ($role > ROLE_SUPER_ADMIN) {
        return ROLE_SUPER_ADMIN;
    }
    return $role;
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
    return getAdminRole() >= ROLE_ADMIN;
}

/**
 * Prüft ob Super-Admin-Rechte vorhanden sind
 */
function isSuperAdmin() {
    return getAdminRole() >= ROLE_SUPER_ADMIN;
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
    $_SESSION['is_admin'] = ROLE_ADMIN;
}

/**
 * Erzwingt Super-Admin-Rechte
 */
function requireSuperAdmin() {
    if (!isSuperAdmin()) {
        die('❌ Zugriff verweigert! Nur für Super-Administratoren.');
    }
}
