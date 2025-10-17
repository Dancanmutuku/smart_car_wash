<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) session_start();

/**
 * Clean input to prevent XSS
 */
function clean_input($data) {
    return htmlspecialchars(stripslashes(trim($data)));
}

/**
 * Check if user is logged in
 */
function is_logged_in() {
    return isset($_SESSION['user']);
}

/**
 * Check if current user is admin
 */
function is_admin() {
    return isset($_SESSION['user']) && $_SESSION['user']['role'] === 'admin';
}
?>
