<?php
session_start();
require_once __DIR__.'/../includes/db.php';
require_once __DIR__.'/../includes/functions.php';

if(!is_logged_in() || $_SESSION['user']['role']!=='customer'){
    header('Location: ../index.php'); exit;
}

$user = $_SESSION['user'];
$user_id = $user['id'];

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
  <div class="row">
    <div class="col-md-8">
      <h3>Your Bookings</h3>
      <div class="table-responsive">
        <table class="table table-striped table-bordered">
          <thead><tr><th>ID</th><th>Service</th><th>Car</th><th>Status</th><th>Time</th></tr></thead>
          <tbody>
            <?php if($bookings): foreach($bookings as $b): ?>
            <tr>
              <td><?= $b['id'] ?></td>
              <td><?= htmlspecialchars($b['service_name']) ?></td>
              <td><?= htmlspecialchars($b['car_model']) ?> (<?= htmlspecialchars($b['license_plate']) ?>)</td>
              <td><?= htmlspecialchars($b['status']) ?></td>
              <td><?= $b['booking_time'] ?></td>
            </tr>
            <?php endforeach; else: ?>
              <tr><td colspan="5">No bookings yet.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <div class="col-md-4">
      <h4>Book New Wash</h4>
      <form method="POST" action="book.php">
        <div class="mb-2"><label>Car Model</label><input type="text" name="car_model" class="form-control" required></div>
        <div class="mb-2"><label>License Plate</label><input type="text" name="license_plate" class="form-control" required></div>
        <div class="mb-2"><label>Service</label>
          <select name="service_id" class="form-select">
            <?php foreach($services as $s): ?>
              <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['service_name']) ?> - $<?= $s['price'] ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-2"><label>Booking Time</label><input type="datetime-local" name="booking_time" class="form-control" required></div>
        <div class="d-grid mt-2"><button class="btn btn-primary">Book Now</button></div>
      </form>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
