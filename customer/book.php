<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Only logged-in customers can book
if (!is_logged_in() || $_SESSION['user']['role'] !== 'customer') {
    header('Location: ../index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $car_model = clean_input($_POST['car_model']);
    $license_plate = clean_input($_POST['license_plate']);
    $service_id = (int)$_POST['service_id'];
    $booking_time = $_POST['booking_time'];
    $user_id = $_SESSION['user']['id'];

    $stmt = $db->prepare("INSERT INTO bookings (user_id, car_model, license_plate, service_id, booking_time) 
                          VALUES (:uid, :cm, :lp, :sid, :bt)");
    $stmt->execute([
        ':uid' => $user_id,
        ':cm' => $car_model,
        ':lp' => $license_plate,
        ':sid'=> $service_id,
        ':bt' => $booking_time
    ]);

    header('Location: dashboard.php');
    exit;
}
?>
