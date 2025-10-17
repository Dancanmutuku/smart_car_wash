<?php
if (session_status() == PHP_SESSION_NONE) session_start();

// --- Clean Input ---
function clean_input($data) {
    return htmlspecialchars(stripslashes(trim($data)));
}

// --- Check Login ---
function is_logged_in() {
    return isset($_SESSION['user']);
}

// --- Admin Check ---
function is_admin() {
    return is_logged_in() && $_SESSION['user']['role'] === 'admin';
}

// --- Staff Check ---
function is_staff() {
    return is_logged_in() && $_SESSION['user']['role'] === 'staff';
}
?>
