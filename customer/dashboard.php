<?php
session_start();
require_once __DIR__.'/../includes/db.php';
require_once __DIR__.'/../includes/functions.php';

if(!is_logged_in() || $_SESSION['user']['role']!=='customer'){
    header('Location: ../index.php'); exit;
}

$user = $_SESSION['user'];
$user_id = $user['id'];

// Handle cancel booking
if(isset($_GET['cancel']) && is_numeric($_GET['cancel'])) {
    $stmt = $db->prepare("UPDATE bookings SET status='cancelled' WHERE id=:id AND user_id=:uid");
    $stmt->execute([':id'=>$_GET['cancel'], ':uid'=>$user_id]);
    header('Location: dashboard.php'); exit;
}

// Fetch bookings
$stmt = $db->prepare("SELECT b.*, s.service_name FROM bookings b 
                      LEFT JOIN services s ON b.service_id=s.id 
                      WHERE b.user_id=:uid ORDER BY b.created_at DESC");
$stmt->execute([':uid'=>$user_id]);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch services
$services = $db->query("SELECT * FROM services")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Customer Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<?php include('../includes/topbar.php'); ?>

<div class="container py-4">
    <h2 class="text-center mb-4">Welcome, <?= htmlspecialchars($user['name']) ?></h2>
    
    <div class="row g-4">
        <!-- Bookings List -->
        <div class="col-lg-8">
            <h4>Your Bookings</h4>
            <div class="table-responsive">
                <table class="table table-striped table-bordered align-middle">
                    <thead class="table-dark"><tr>
                        <th>ID</th><th>Service</th><th>Car</th><th>Status</th><th>Booking Time</th><th>Actions</th>
                    </tr></thead>
                    <tbody>
                        <?php if($bookings): foreach($bookings as $b): ?>
                            <tr>
                                <td><?= $b['id'] ?></td>
                                <td><?= htmlspecialchars($b['service_name']) ?></td>
                                <td><?= htmlspecialchars($b['car_model']) ?> (<?= htmlspecialchars($b['license_plate']) ?>)</td>
                                <td><?= htmlspecialchars($b['status']) ?></td>
                                <td><?= $b['booking_time'] ?></td>
                                <td>
                                    <?php if($b['status']=='pending' || $b['status']=='approved'): ?>
                                        <a href="?cancel=<?= $b['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Cancel this booking?')">Cancel</a>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="6" class="text-center">No bookings yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Book New Wash -->
        <div class="col-lg-4">
            <div class="card shadow p-3">
                <h4 class="card-title mb-3">Book New Wash</h4>
                <form method="POST" action="book.php">
                    <div class="mb-3">
                        <label class="form-label">Car Model</label>
                        <input type="text" name="car_model" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">License Plate</label>
                        <input type="text" name="license_plate" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Service</label>
                        <select name="service_id" class="form-select">
                            <?php foreach($services as $s): ?>
                                <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['service_name']) ?> - $<?= $s['price'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Booking Time</label>
                        <input type="datetime-local" name="booking_time" class="form-control" required>
                    </div>
                    <div class="d-grid"><button class="btn btn-primary">Book Now</button></div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
