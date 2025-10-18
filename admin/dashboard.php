<?php
session_start();
require_once __DIR__.'/../includes/db.php';
require_once __DIR__.'/../includes/functions.php';

if(!is_logged_in() || !is_admin()) {
    header('Location: ../index.php'); exit;
}

// --- CRUD Operations ---
if(isset($_POST['create_service'])){
    $stmt = $db->prepare("INSERT INTO services (service_name, description, price, duration, category) VALUES (:name,:desc,:price,:duration,:cat)");
    $stmt->execute([
        ':name'=>clean_input($_POST['service_name']),
        ':desc'=>clean_input($_POST['description']),
        ':price'=>(float)$_POST['price'],
        ':duration'=>(int)$_POST['duration'],
        ':cat'=>clean_input($_POST['category'])
    ]);
    header("Location: dashboard.php"); exit;
}

if(isset($_POST['update_booking'])){
    $stmt = $db->prepare("UPDATE bookings SET status=:status, booking_time=:time, staff_id=:staff WHERE id=:id");
    $stmt->execute([
        ':status'=>clean_input($_POST['status']),
        ':time'=>$_POST['booking_time'] ?: null,
        ':staff'=>$_POST['staff_id'] ?: null,
        ':id'=>(int)$_POST['booking_id']
    ]);
    header("Location: dashboard.php"); exit;
}

if(isset($_POST['create_user'])){
    $stmt = $db->prepare("INSERT INTO users (name,email,password,role) VALUES (:name,:email,:pass,:role)");
    $stmt->execute([
        ':name'=>clean_input($_POST['new_name']),
        ':email'=>clean_input($_POST['new_email']),
        ':pass'=>password_hash($_POST['new_password'],PASSWORD_DEFAULT),
        ':role'=>clean_input($_POST['new_role'])
    ]);
    header("Location: dashboard.php"); exit;
}

if(isset($_POST['update_password'])){
    $stmt = $db->prepare("UPDATE users SET password=:pass WHERE id=:id");
    $stmt->execute([
        ':pass'=>password_hash($_POST['new_password'],PASSWORD_DEFAULT),
        ':id'=>(int)$_POST['user_id']
    ]);
    header("Location: dashboard.php"); exit;
}

// --- Fetch Stats ---
$total_users = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_bookings = $db->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
$pending_bookings = $db->query("SELECT COUNT(*) FROM bookings WHERE status='pending'")->fetchColumn();
$completed_bookings = $db->query("SELECT COUNT(*) FROM bookings WHERE status='completed'")->fetchColumn();
$rejected_bookings = $db->query("SELECT COUNT(*) FROM bookings WHERE status='rejected'")->fetchColumn();
$total_services = $db->query("SELECT COUNT(*) FROM services")->fetchColumn();

// --- Fetch Data ---
$recent_bookings = $db->query("
    SELECT b.*, u.name AS customer_name, s.service_name, s.category, st.name AS staff_name
    FROM bookings b
    LEFT JOIN users u ON b.user_id = u.id
    LEFT JOIN services s ON b.service_id = s.id
    LEFT JOIN users st ON b.staff_id = st.id
    ORDER BY b.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

$all_users = $db->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$all_services = $db->query("SELECT * FROM services ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$all_staff = $db->query("SELECT * FROM users WHERE role='staff'")->fetchAll(PDO::FETCH_ASSOC);

// --- Fetch Feedback ---
$feedback_data = $db->query("
    SELECT f.*, u.name AS customer_name, b.service_id, s.service_name
    FROM feedback f
    LEFT JOIN users u ON f.user_id = u.id
    LEFT JOIN bookings b ON f.booking_id = b.id
    LEFT JOIN services s ON b.service_id = s.id
    ORDER BY f.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// --- Prepare Chart Data ---
$service_counts = [];
$staff_counts = [];
foreach($recent_bookings as $b){
    $service_counts[$b['service_name'] ?? 'Unknown'] = ($service_counts[$b['service_name'] ?? 'Unknown'] ?? 0) + 1;
    $staff_name = $b['staff_name'] ?? 'Unassigned';
    $staff_counts[$staff_name] = ($staff_counts[$staff_name] ?? 0) + 1;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard - Smart Car Wash</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
body { background: linear-gradient(135deg,#f0f2f5,#d9e2ec); font-family:'Segoe UI',sans-serif; }
.card { border-radius: 15px; transition: transform 0.2s, box-shadow 0.2s; cursor:pointer; }
.card:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0,0,0,0.15); }
.nav-tabs .nav-link.active { background: #0d6efd; color: #fff; border-radius: 10px 10px 0 0; }
.table thead { background: #0d6efd; color: #fff; }
.table-striped>tbody>tr:nth-of-type(odd) { background-color: #f8f9fa; }
h2,h4,h5 { color: #343a40; }
canvas { background: #fff; border-radius: 15px; padding: 15px; }
.modal-content { border-radius: 15px; }
</style>
</head>
<body>
<?php include('../includes/topbar.php'); ?>

<div class="container my-5">
<h2 class="text-center mb-4">Admin Dashboard</h2>

<!-- Stats Cards -->
<div class="row g-4 mb-5 text-center">
    <div class="col-md-3"><div class="card shadow p-4 bg-primary text-white"><h3><?= $total_users ?></h3><p>Users</p></div></div>
    <div class="col-md-3"><div class="card shadow p-4 bg-success text-white"><h3><?= $total_bookings ?></h3><p>Total Bookings</p></div></div>
    <div class="col-md-3"><div class="card shadow p-4 bg-warning text-dark"><h3><?= $pending_bookings ?></h3><p>Pending</p></div></div>
    <div class="col-md-3"><div class="card shadow p-4 bg-info text-white"><h3><?= $total_services ?></h3><p>Services</p></div></div>
</div>

<!-- Clickable Charts -->
<div class="row mb-5">
    <div class="col-md-4">
        <div class="card shadow p-4 text-center" data-bs-toggle="modal" data-bs-target="#statusModal">
            <h5>Booking Status</h5><p>Click to view chart</p>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow p-4 text-center" data-bs-toggle="modal" data-bs-target="#serviceModal">
            <h5>Bookings per Service</h5><p>Click to view chart</p>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow p-4 text-center" data-bs-toggle="modal" data-bs-target="#staffModal">
            <h5>Bookings per Staff</h5><p>Click to view chart</p>
        </div>
    </div>
</div>

<!-- Chart Modals -->
<div class="modal fade" id="statusModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content p-3">
<div class="modal-header"><h5>Booking Status</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body"><canvas id="statusChartModal" height="250"></canvas></div></div></div></div>

<div class="modal fade" id="serviceModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content p-3">
<div class="modal-header"><h5>Bookings per Service</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body"><canvas id="serviceChartModal" height="250"></canvas></div></div></div></div>

<div class="modal fade" id="staffModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content p-3">
<div class="modal-header"><h5>Bookings per Staff</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body"><canvas id="staffChartModal" height="250"></canvas></div></div></div></div>

<!-- Tabs -->
<ul class="nav nav-tabs" id="adminTabs">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#bookings">Bookings</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#services">Services</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#users">Users</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#feedback">Feedback</button></li>
</ul>

<div class="tab-content mt-3">

<!-- Bookings Tab -->
<div class="tab-pane fade show active" id="bookings">
<div class="table-responsive"><table class="table table-striped table-bordered align-middle">
<thead><tr>
<th>ID</th><th>Customer</th><th>Service</th><th>Category</th><th>Car</th><th>Status</th><th>Booking Time</th><th>Assign Staff</th><th>Feedback</th>
</tr></thead>
<tbody>
<?php foreach($recent_bookings as $b): ?>
<tr>
<td><?= $b['id'] ?></td>
<td><?= htmlspecialchars($b['customer_name'] ?? 'Unknown') ?></td>
<td><?= htmlspecialchars($b['service_name'] ?? '') ?></td>
<td><?= htmlspecialchars($b['category'] ?? 'General') ?></td>
<td><?= htmlspecialchars($b['car_model'] ?? '') ?> (<?= htmlspecialchars($b['license_plate'] ?? '') ?>)</td>
<td><?= htmlspecialchars($b['status'] ?? '') ?></td>
<td><?= $b['booking_time'] ?? '' ?></td>
<td>
<form method="POST">
<input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
<select name="staff_id" class="form-select form-select-sm mb-1">
<option value="">--None--</option>
<?php foreach($all_staff as $s): ?>
<option value="<?= $s['id'] ?>" <?= ($b['staff_id'] ?? '')==$s['id']?'selected':'' ?>><?= htmlspecialchars($s['name']) ?></option>
<?php endforeach; ?>
</select>
<select name="status" class="form-select form-select-sm mb-1">
<option value="pending" <?= ($b['status']??'')=='pending'?'selected':'' ?>>Pending</option>
<option value="approved" <?= ($b['status']??'')=='approved'?'selected':'' ?>>Approved</option>
<option value="rejected" <?= ($b['status']??'')=='rejected'?'selected':'' ?>>Rejected</option>
</select>
<input type="datetime-local" name="booking_time" class="form-control form-control-sm mb-1" value="<?= isset($b['booking_time']) ? date('Y-m-d\TH:i', strtotime($b['booking_time'])) : '' ?>">
<button name="update_booking" class="btn btn-sm btn-primary w-100">Update</button>
</form>
</td>
<td>
<?php 
$booking_feedback = array_filter($feedback_data, fn($f) => $f['booking_id'] == $b['id']); 
?>
<?php if(!empty($booking_feedback)): ?>
<button class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#feedbackModal<?= $b['id'] ?>">View Feedback</button>

<div class="modal fade" id="feedbackModal<?= $b['id'] ?>" tabindex="-1">
<div class="modal-dialog modal-dialog-centered">
<div class="modal-content p-3">
<div class="modal-header">
<h5>Feedback for Booking #<?= $b['id'] ?></h5>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
<?php foreach($booking_feedback as $fb): ?>
<p><strong><?= htmlspecialchars($fb['customer_name']) ?></strong> (Rating: <?= $fb['rating'] ?>/5)</p>
<p><?= nl2br(htmlspecialchars($fb['comment'])) ?></p><hr>
<?php endforeach; ?>
</div></div></div></div>
<?php else: ?>
<span class="text-muted">No feedback</span>
<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table></div>
</div>

<!-- Services Tab -->
<div class="tab-pane fade" id="services">
<form method="POST" class="row g-2 mb-3">
<div class="col-md-3"><input type="text" name="service_name" class="form-control" placeholder="Service Name" required></div>
<div class="col-md-3"><input type="text" name="description" class="form-control" placeholder="Description" required></div>
<div class="col-md-2"><input type="number" step="0.01" name="price" class="form-control" placeholder="Price" required></div>
<div class="col-md-2"><input type="number" name="duration" class="form-control" placeholder="Duration (min)" required></div>
<div class="col-md-2"><select name="category" class="form-select" required>
<option value="Exterior">Exterior</option>
<option value="Interior">Interior</option>
<option value="Full">Full</option>
</select></div>
<div class="col-md-12 mt-2"><button name="create_service" class="btn btn-success w-100">Add Service</button></div>
</form>
<div class="table-responsive"><table class="table table-striped table-bordered"><thead class="table-dark"><tr><th>ID</th><th>Name</th><th>Description</th><th>Price</th><th>Duration</th><th>Category</th></tr></thead>
<tbody><?php foreach($all_services as $s): ?>
<tr>
<td><?= $s['id'] ?></td>
<td><?= htmlspecialchars($s['service_name']) ?></td>
<td><?= htmlspecialchars($s['description']) ?></td>
<td><?= $s['price'] ?></td>
<td><?= $s['duration'] ?> min</td>
<td><?= htmlspecialchars($s['category'] ?? 'General') ?></td>
</tr><?php endforeach; ?>
</tbody></table></div>
</div>

<!-- Users Tab -->
<div class="tab-pane fade" id="users">
<form method="POST" class="row g-2 mb-3 align-items-end">
<div class="col-md-3"><input type="text" name="new_name" class="form-control" placeholder="Full Name" required></div>
<div class="col-md-3"><input type="email" name="new_email" class="form-control" placeholder="Email" required></div>
<div class="col-md-2"><input type="password" name="new_password" class="form-control" placeholder="Password" required></div>
<div class="col-md-2"><select name="new_role" class="form-select" required>
<option value="customer">Customer</option>
<option value="staff">Staff</option>
<option value="admin">Admin</option>
</select></div>
<div class="col-md-2"><button name="create_user" class="btn btn-success w-100">Add User</button></div>
</form>
<div class="table-responsive"><table class="table table-striped table-bordered"><thead class="table-dark"><tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Registered At</th><th>Update Password</th></tr></thead>
<tbody><?php foreach($all_users as $u): ?>
<tr>
<td><?= $u['id'] ?></td>
<td><?= htmlspecialchars($u['name']) ?></td>
<td><?= htmlspecialchars($u['email']) ?></td>
<td><?= htmlspecialchars($u['role']) ?></td>
<td><?= $u['created_at'] ?></td>
<td><form method="POST" class="d-flex gap-1">
<input type="hidden" name="user_id" value="<?= $u['id'] ?>">
<input type="password" name="new_password" class="form-control form-control-sm" placeholder="New Password" required>
<button name="update_password" class="btn btn-sm btn-primary">Update</button>
</form></td>
</tr><?php endforeach; ?>
</tbody></table></div>
</div>

<!-- Feedback Tab -->
<div class="tab-pane fade" id="feedback">
<div class="table-responsive"><table class="table table-striped table-bordered">
<thead class="table-dark">
<tr><th>ID</th><th>Booking</th><th>Customer</th><th>Service</th><th>Rating</th><th>Comment</th><th>Created At</th></tr>
</thead>
<tbody>
<?php foreach($feedback_data as $f): ?>
<tr>
<td><?= $f['id'] ?></td>
<td><?= $f['booking_id'] ?></td>
<td><?= htmlspecialchars($f['customer_name']) ?></td>
<td><?= htmlspecialchars($f['service_name'] ?? 'Unknown') ?></td>
<td><?= $f['rating'] ?>/5</td>
<td><?= nl2br(htmlspecialchars($f['comment'])) ?></td>
<td><?= $f['created_at'] ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table></div>
</div>

</div> <!-- /tab-content -->
</div> <!-- /container -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
const statusData = { labels:['Pending','Completed','Rejected'], datasets:[{data:[<?= $pending_bookings ?>,<?= $completed_bookings ?>,<?= $rejected_bookings ?>], backgroundColor:['#ffc107','#198754','#dc3545']}] };
const serviceData = { labels:<?= json_encode(array_keys($service_counts)) ?>, datasets:[{label:'Bookings', data:<?= json_encode(array_values($service_counts)) ?>, backgroundColor:'#0d6efd'}] };
const staffData = { labels:<?= json_encode(array_keys($staff_counts)) ?>, datasets:[{label:'Bookings', data:<?= json_encode(array_values($staff_counts)) ?>, backgroundColor:'#6f42c1'}] };

document.getElementById('statusModal').addEventListener('shown.bs.modal', function () { new Chart(document.getElementById('statusChartModal'), { type:'pie', data: statusData }); });
document.getElementById('serviceModal').addEventListener('shown.bs.modal', function () { new Chart(document.getElementById('serviceChartModal'), { type:'bar', data: serviceData, options:{scales:{y:{beginAtZero:true}}} }); });
document.getElementById('staffModal').addEventListener('shown.bs.modal', function () { new Chart(document.getElementById('staffChartModal'), { type:'bar', data: staffData, options:{scales:{y:{beginAtZero:true}}} }); });
</script>
</body>
</html>

