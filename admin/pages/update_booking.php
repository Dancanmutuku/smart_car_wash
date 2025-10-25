<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
session_start();

// Admin-only check
if (!is_logged_in() || !is_admin()) {
    header("Location: ../index.php");
    exit;
}

$id = $_POST['id'] ?? null;
$action = $_POST['action'] ?? null;
$staff_id = $_POST['staff_id'] ?? null;

if (!$id || !$action) {
    header("Location: bookings.php");
    exit;
}

// Only allow change if booking is NOT completed
$status = $db->query("SELECT status FROM bookings WHERE id = $id")->fetchColumn();
if ($status === 'completed') {
    header("Location: bookings.php?msg=completed_locked");
    exit;
}

switch ($action) {
    case 'approve':
        $stmt = $db->prepare("UPDATE bookings SET status='approved' WHERE id=?");
        $stmt->execute([$id]);
        log_action($_SESSION['user_id'], "Approved booking #$id");
        break;

    case 'reject':
        $stmt = $db->prepare("UPDATE bookings SET status='rejected' WHERE id=?");
        $stmt->execute([$id]);
        log_action($_SESSION['user_id'], "Rejected booking #$id");
        break;

    case 'assign':
        if ($staff_id) {
            $stmt = $db->prepare("UPDATE bookings SET staff_id=?, status='assigned' WHERE id=?");
            $stmt->execute([$staff_id, $id]);
            log_action($_SESSION['user_id'], "Assigned booking #$id to staff #$staff_id");
        }
        break;

    case 'delete':
        $stmt = $db->prepare("DELETE FROM bookings WHERE id=?");
        $stmt->execute([$id]);
        log_action($_SESSION['user_id'], "Deleted booking #$id");
        break;
}

header("Location: bookings.php");
exit;
