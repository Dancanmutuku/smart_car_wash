<?php
session_start();
require_once __DIR__.'/../includes/db.php';
require_once __DIR__.'/../includes/functions.php';

if(!is_logged_in() || !is_admin()){
    header('Location: ../index.php'); exit;
}

// Optional table creation
try {
    $db->exec("CREATE TABLE IF NOT EXISTS supplies (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        quantity INTEGER DEFAULT 0,
        unit TEXT,
        last_updated DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS activity_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        action TEXT,
        details TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
} catch (PDOException $e) {}

$admin_id = $_SESSION['user']['id'];

function log_activity($db, $user_id, $action, $details=''){
    $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (:uid,:action,:details)");
    $stmt->execute([':uid'=>$user_id,':action'=>$action,':details'=>$details]);
}

// Handle POST actions
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(isset($_POST['booking_action']) || isset($_POST['create_service']) || isset($_POST['edit_service']) || isset($_POST['delete_service'])
        || isset($_POST['save_supply']) || isset($_POST['delete_supply']) || isset($_POST['create_user']) || isset($_POST['delete_user'])
        || isset($_POST['export_bookings'])){
        include('actions.php');
        exit;
    }
}

// Include tab content files
$tab_files = [
    'bookings' => 'bookings.php',
    'services' => 'services.php',
    'users' => 'users.php',
    'feedback' => 'feedback.php',
    'supplies' => 'supplies.php',
    'insights' => 'insights.php',
    'logs' => 'logs.php',
    'activity' => 'activity.php',
];

// Render dashboard layout
include('dashboard_layout.php');
