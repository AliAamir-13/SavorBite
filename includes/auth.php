<?php
/**
 * Authentication and Access Control Helper
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if a user is currently logged in
 */
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Check if the logged-in user is an administrator
 */
function is_admin() {
    return is_logged_in() && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

/**
 * Retrieve current user session payload
 */
function current_user() {
    if (!is_logged_in()) {
        return null;
    }
    return [
        'user_id'   => $_SESSION['user_id'],
        'full_name' => $_SESSION['full_name'] ?? 'Guest',
        'email'     => $_SESSION['user_email'] ?? '',
        'role'      => $_SESSION['user_role'] ?? 'customer'
    ];
}

/**
 * Require login or redirect to login.php
 */
function require_login($redirect_url = null) {
    if (!is_logged_in()) {
        if ($redirect_url === null) {
            $redirect_url = $_SERVER['REQUEST_URI'];
        }
        $_SESSION['redirect_after_login'] = $redirect_url;
        header("Location: login.php");
        exit();
    }
}

/**
 * Require admin privileges or redirect
 */
function require_admin() {
    if (!is_logged_in()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header("Location: ../login.php");
        exit();
    }
    if (!is_admin()) {
        header("Location: ../index.php?error=unauthorized");
        exit();
    }
}

/**
 * Log in a user and establish session
 */
function log_in_user($user) {
    session_regenerate_id(true); // Prevent session fixation attacks
    $_SESSION['user_id']    = $user['user_id'];
    $_SESSION['full_name']  = $user['full_name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role']  = $user['role'];
}

/**
 * Log out user and destroy session
 */
function log_out_user() {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}
