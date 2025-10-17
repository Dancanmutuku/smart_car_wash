<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

// Redirect if not logged in or not admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

// Fetch stats
$users = $db->query("SELECT COUNT(*) as total FROM users")->fetch(PDO::FETCH_ASSOC)['total'];
$bookings = $db->query("SELECT COUNT(*) as total FROM bookings")->fetch(PDO::FETCH_ASSOC)['total'];
$pending = $db->query("SELECT COUNT(*) as total FROM bookings WHERE status='pending'")->fetch(PDO::FETCH_ASSOC)['total'];
$services = $db->query("SELECT COUNT(*) as total FROM services")->fetch(PDO::FETCH_ASSOC)['total'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - Smart Car Wash</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="#">Smart Car Wash - Admin</a>
        <div>
            <a href="../logout.php" class="btn btn-outline-light btn-sm">Logout</a>
        </div>
    </div>
</nav>

<div class="container my-5">
    <h2 class="mb-4 text-center">Admin Dashboard</h2>
    <div class="row g-4">
        <div class="col-md-3">
            <div class="card shadow text-center">
                <div class="card-body">
                    <h3><?= $users ?></h3>
                    <p>Registered Users</p>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow text-center">
                <div class="card-body">
                    <h3><?= $bookings ?></h3>
                    <p>Total Bookings</p>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow text-center">
                <div class="card-body">
                    <h3><?= $pending ?></h3>
                    <p>Pending Bookings</p>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow text-center">
                <div class="card-body">
                    <h3><?= $services ?></h3>
                    <p>Available Services</p>
                </div>
            </div>
        </div>
    </div>

    <hr class="my-5">

    <h4>Recent Bookings</h4>
    <table class="table table-striped table-bordered">
        <thead class="table-dark">
            <tr>
                <th>ID</th>
                <th>Customer</th>
                <th>Car</th>
                <th>License</th>
                <th>Service</th>
                <th>Status</th>
                <th>Time</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $stmt = $db->query("SELECT b.*, u.name, s.service_name 
                                FROM bookings b
                                LEFT JOIN users u ON b.user_id = u.id
                                LEFT JOIN services s ON b.service_id = s.id
                                ORDER BY b.created_at DESC LIMIT 10");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                echo "<tr>
                        <td>{$row['id']}</td>
                        <td>{$row['name']}</td>
                        <td>{$row['car_model']}</td>
                        <td>{$row['license_plate']}</td>
                        <td>{$row['service_name']}</td>
                        <td>{$row['status']}</td>
                        <td>{$row['created_at']}</td>
                      </tr>";
            }
            ?>
        </tbody>
    </table>
</div>

<script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
