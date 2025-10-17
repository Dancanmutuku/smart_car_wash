<?php
session_start();
require_once __DIR__.'/../includes/db.php';
require_once __DIR__.'/../includes/functions.php';

if(!is_logged_in() || !is_admin()) {
    header('Location: ../index.php'); exit;
}

// Fetch stats
$users = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$bookings = $db->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
$pending = $db->query("SELECT COUNT(*) FROM bookings WHERE status='pending'")->fetchColumn();
$services = $db->query("SELECT COUNT(*) FROM services")->fetchColumn();

// Recent bookings
$stmt = $db->query("SELECT b.*, u.name, s.service_name 
                    FROM bookings b 
                    LEFT JOIN users u ON b.user_id=u.id 
                    LEFT JOIN services s ON b.service_id=s.id
                    ORDER BY b.created_at DESC LIMIT 10");
$recent = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<?php include('../includes/topbar.php'); ?>
<div class="container my-5">
    <h2 class="mb-4 text-center">Admin Dashboard</h2>
    <div class="row g-4">
        <div class="col-md-3"><div class="card shadow p-3 text-center"><h3><?= $users ?></h3><p>Users</p></div></div>
        <div class="col-md-3"><div class="card shadow p-3 text-center"><h3><?= $bookings ?></h3><p>Total Bookings</p></div></div>
        <div class="col-md-3"><div class="card shadow p-3 text-center"><h3><?= $pending ?></h3><p>Pending Bookings</p></div></div>
        <div class="col-md-3"><div class="card shadow p-3 text-center"><h3><?= $services ?></h3><p>Services</p></div></div>
    </div>
    <hr class="my-4">
    <h4>Recent Bookings</h4>
    <div class="table-responsive">
        <table class="table table-striped table-bordered">
            <thead class="table-dark"><tr><th>ID</th><th>Customer</th><th>Service</th><th>Car</th><th>Status</th><th>Time</th></tr></thead>
            <tbody>
                <?php foreach($recent as $r): ?>
                    <tr>
                        <td><?= $r['id'] ?></td>
                        <td><?= htmlspecialchars($r['name']) ?></td>
                        <td><?= htmlspecialchars($r['service_name']) ?></td>
                        <td><?= htmlspecialchars($r['car_model']) ?> (<?= htmlspecialchars($r['license_plate']) ?>)</td>
                        <td><?= htmlspecialchars($r['status']) ?></td>
                        <td><?= $r['created_at'] ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if(empty($recent)) echo '<tr><td colspan="6">No recent bookings</td></tr>'; ?>
            </tbody>
        </table>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
