<?php
// =========================
// 🧩 SESSION & UTILITIES
// =========================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- Clean user input safely ---
function clean_input($data): string {
    return htmlspecialchars(trim(stripslashes($data ?? '')), ENT_QUOTES, 'UTF-8');
}

// --- Check if user is logged in ---
function is_logged_in(): bool {
    return isset($_SESSION['user']) && !empty($_SESSION['user']['id']);
}

// --- Role check helpers ---
function is_admin(): bool {
    return is_logged_in() && ($_SESSION['user']['role'] ?? '') === 'admin';
}

function is_staff(): bool {
    return is_logged_in() && ($_SESSION['user']['role'] ?? '') === 'staff';
}

function is_customer(): bool {
    return is_logged_in() && ($_SESSION['user']['role'] ?? '') === 'customer';
}

// =========================
// 🧾 SYSTEM LOGGING
// =========================
/**
 * Records an action into the logs table.
 *
 * @param int $user_id      ID of the acting user
 * @param string $action    Description of what happened
 * @param string $details   Optional details (e.g. affected record)
 * @param PDO|null $pdo     Optional PDO instance; uses global $db if null
 */
function log_action(int $user_id, string $action, string $details = '', PDO $pdo = null): void {
    if ($pdo === null) {
        global $db;
        $pdo = $db ?? null;
    }

    if (!$pdo) return; // gracefully skip if db not ready

    try {
        $stmt = $pdo->prepare("
            INSERT INTO logs (user_id, action, details)
            VALUES (:uid, :action, :details)
        ");
        $stmt->execute([
            ':uid' => $user_id,
            ':action' => $action,
            ':details' => $details
        ]);
    } catch (PDOException $e) {
        // Optional: you can log to PHP error log instead of breaking the app
        // error_log('Log failed: ' . $e->getMessage());
    }
}

/**
 * Backward-compatible alias for older calls: log_activity($db, $user_id, ...)
 */
function log_activity($db_or_user, $user_id = null, $action = '', $details = ''): void {
    if ($db_or_user instanceof PDO && $user_id !== null) {
        log_action((int)$user_id, $action, $details, $db_or_user);
    } elseif (is_int($db_or_user)) {
        log_action($db_or_user, $action, $details);
    } else {
        global $db;
        log_action($_SESSION['user']['id'] ?? 0, $action, $details, $db);
    }
}

// =========================
// 🔐 REDIRECT HELPERS
// =========================
function require_login(): void {
    if (!is_logged_in()) {
        header('Location: ../index.php');
        exit;
    }
}

function require_admin(): void {
    if (!is_admin()) {
        header('Location: ../index.php');
        exit;
    }
}

function require_staff(): void {
    if (!is_staff()) {
        header('Location: ../index.php');
        exit;
    }
}
?>
