<?php
session_start();
require_once __DIR__.'/../includes/db.php';
require_once __DIR__.'/../includes/functions.php';

if(!is_logged_in() || !is_admin()) {
    header('Location: ../index.php'); exit;
}

// --- Handle Service Creation ---
if(isset($_POST['create_service'])) {
    $name = clean_input($_POST['service_name']);
    $desc = clean_input($_POST['description']);
    $price = (float)$_POST['price'];
    $duration = (int)$_POST['duration'];

    $stmt = $db->prepare("INSERT INTO services (service_name, description, price, duration) VALUES (:name, :desc, :price, :duration)");
    $stmt->execute([':name'=>$name, ':desc'=>$desc, ':price'=>$price, ':duration'=>$duration]);
}

// --- Handle Booking Updates ---
if(isset($_POST['update_booking'])) {
    $id = (int)$_POST['booking_id'];
    $status = clean_input($_POST['status']);
    $time = $_POST['booking_time'] ?? null;

    if($time) {
        $stmt = $db->prepare("UPDATE bookings SET status=:status, booking_time=:time WHERE id=:id");
        $stmt->execute([':status'=>$status, ':time'=>$time, ':id'=>$id]);
    } else {
        $stmt = $db->prepare("UPDATE bookings SET status=:status WHERE id=:id");
        $stmt->execute([':status'=>$status, ':id'=>$id]);
    }
}

// --- Handle User Creation ---
if(isset($_POST['create_user'])){
    $name = clean_input($_POST['new_name']);
    $email = clean_input($_POST['new_email']);
    $password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
    $role = in_array($_POST['new_role'], ['admin','customer']) ? $_POST['new_role'] : 'customer';

    $stmt = $db->prepare("INSERT INTO users (name, email, password, role) VALUES (:name, :email, :password, :role)");
    $stmt->execute([':name'=>$name, ':email'=>$email, ':password'=>$password, ':role'=>$role]);
}

// --- Handle Password Update ---
if(isset($_POST['update_password']) && isset($_POST['user_id']) && !empty($_POST['new_password'])){
    $new_pass = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
    $uid = (int)$_POST['user_id'];
    $stmt = $db->prepare("UPDATE users SET password=:password WHERE id=:id");
    $stmt->execute([':password'=>$new_pass, ':id'=>$uid]);
}

// --- Fetch Stats ---
$users_count = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$bookings_count = $db->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
$pending_count = $db->query("SELECT COUNT(*) FROM bookings WHERE status='pending'")->fetchColumn();
$services_count = $db->query("SELECT COUNT(*) FROM services")->fetchColumn();

// --- Fetch Data ---
$recent_bookings = $db->query("SELECT b.*, u.name, s.service_name 
                               FROM bookings b 
                               LEFT JOIN users u ON b.user_id=u.id 
                               LEFT JOIN services s ON b.service_id=s.id 
                               ORDER BY b.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

$all_users = $db->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$all_services = $db->query("SELECT * FROM services ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
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

    <!-- Stats Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-3"><div class="card shadow p-3 text-center"><h3><?= $users_count ?></h3><p>Users</p></div></div>
        <div class="col-md-3"><div class="card shadow p-3 text-center"><h3><?= $bookings_count ?></h3><p>Total Bookings</p></div></div>
        <div class="col-md-3"><div class="card shadow p-3 text-center"><h3><?= $pending_count ?></h3><p>Pending Bookings</p></div></div>
        <div class="col-md-3"><div class="card shadow p-3 text-center"><h3><?= $services_count ?></h3><p>Services</p></div></div>
    </div>

    <!-- Tabs -->
    <ul class="nav nav-tabs" id="adminTabs" role="tablist">
        <li class="nav-item"><button class="nav-link active" id="bookings-tab" data-bs-toggle="tab" data-bs-target="#bookings">Bookings</button></li>
        <li class="nav-item"><button class="nav-link" id="services-tab" data-bs-toggle="tab" data-bs-target="#services">Services</button></li>
        <li class="nav-item"><button class="nav-link" id="users-tab" data-bs-toggle="tab" data-bs-target="#users">Users</button></li>
    </ul>

    <div class="tab-content mt-3">
        <!-- Bookings Tab -->
        <div class="tab-pane fade show active" id="bookings">
            <h4>Manage Bookings</h4>
            <div class="table-responsive">
                <table class="table table-striped table-bordered">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th><th>Customer</th><th>Service</th><th>Car</th><th>Status</th><th>Booking Time</th><th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach($recent_bookings as $b): ?>
                        <tr>
                            <td><?= $b['id'] ?></td>
                            <td><?= htmlspecialchars($b['name']) ?></td>
                            <td><?= htmlspecialchars($b['service_name']) ?></td>
                            <td><?= htmlspecialchars($b['car_model']) ?> (<?= htmlspecialchars($b['license_plate']) ?>)</td>
                            <td><?= htmlspecialchars($b['status']) ?></td>
                            <td><?= $b['booking_time'] ?></td>
                            <td>
                                <form method="POST" class="d-flex flex-column gap-1">
                                    <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                                    <input type="datetime-local" name="booking_time" class="form-control mb-1" value="<?= date('Y-m-d\TH:i', strtotime($b['booking_time'])) ?>">
                                    <select name="status" class="form-select mb-1">
                                        <option value="pending" <?= $b['status']=='pending'?'selected':'' ?>>Pending</option>
                                        <option value="approved" <?= $b['status']=='approved'?'selected':'' ?>>Approved</option>
                                        <option value="rejected" <?= $b['status']=='rejected'?'selected':'' ?>>Rejected</option>
                                    </select>
                                    <button name="update_booking" class="btn btn-sm btn-primary">Update</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if(empty($recent_bookings)) echo '<tr><td colspan="7">No bookings</td></tr>'; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Services Tab -->
        <div class="tab-pane fade" id="services">
            <h4>Manage Services</h4>
            <form method="POST" class="mb-4">
                <div class="row g-2">
                    <div class="col-md-3"><input type="text" name="service_name" class="form-control" placeholder="Service Name" required></div>
                    <div class="col-md-3"><input type="text" name="description" class="form-control" placeholder="Description" required></div>
                    <div class="col-md-2"><input type="number" step="0.01" name="price" class="form-control" placeholder="Price" required></div>
                    <div class="col-md-2"><input type="number" name="duration" class="form-control" placeholder="Duration (min)" required></div>
                    <div class="col-md-2"><button name="create_service" class="btn btn-success w-100">Add Service</button></div>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-striped table-bordered">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th><th>Name</th><th>Description</th><th>Price</th><th>Duration</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($all_services as $s): ?>
                            <tr>
                                <td><?= $s['id'] ?></td>
                                <td><?= htmlspecialchars($s['service_name']) ?></td>
                                <td><?= htmlspecialchars($s['description']) ?></td>
                                <td><?= $s['price'] ?></td>
                                <td><?= $s['duration'] ?> min</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Users Tab -->
        <div class="tab-pane fade" id="users">
            <h4>Users Management</h4>

            <!-- Create New User -->
            <form method="POST" class="mb-4">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3"><input type="text" name="new_name" class="form-control" placeholder="Full Name" required></div>
                    <div class="col-md-3"><input type="email" name="new_email" class="form-control" placeholder="Email" required></div>
                    <div class="col-md-2"><input type="password" name="new_password" class="form-control" placeholder="Password" required></div>
                    <div class="col-md-2">
                        <select name="new_role" class="form-select" required>
                            <option value="customer">Customer</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="col-md-2"><button name="create_user" class="btn btn-success w-100">Add User</button></div>
                </div>
            </form>

            <!-- Users Table -->
            <div class="table-responsive">
                <table class="table table-striped table-bordered align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Registered At</th><th>Update Password</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($all_users as $u): ?>
                        <tr>
                            <td><?= $u['id'] ?></td>
                            <td><?= htmlspecialchars($u['name']) ?></td>
                            <td><?= htmlspecialchars($u['email']) ?></td>
                            <td><?= htmlspecialchars($u['role']) ?></td>
                            <td><?= $u['created_at'] ?></td>
                            <td>
                                <form method="POST" class="d-flex gap-1">
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <input type="password" name="new_password" class="form-control form-control-sm" placeholder="New Password" required>
                                    <button name="update_password" class="btn btn-sm btn-primary">Update</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
