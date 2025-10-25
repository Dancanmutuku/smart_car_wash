<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Restrict access to staff only
if (!is_logged_in() || !is_staff()) {
    header('Location: ../index.php');
    exit;
}

$staff_id = $_SESSION['user']['id'];

// ✅ Handle booking status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_booking'])) {
    $booking_id = (int)$_POST['booking_id'];
    $status = clean_input($_POST['update_booking']); // Button value: completed / not attended

    // Prevent editing completed jobs
    $check = $db->prepare("SELECT status FROM bookings WHERE id = ? AND staff_id = ?");
    $check->execute([$booking_id, $staff_id]);
    $current = $check->fetchColumn();

    if (!$current) {
        $_SESSION['error_message'] = "⚠️ Invalid booking or not assigned to you.";
    } elseif ($current === 'completed') {
        $_SESSION['error_message'] = "✅ Booking #$booking_id is already completed.";
    } else {
        $stmt = $db->prepare("UPDATE bookings SET status = :status WHERE id = :id AND staff_id = :staff_id");
        $stmt->execute([
            ':status' => $status,
            ':id' => $booking_id,
            ':staff_id' => $staff_id
        ]);

        // Optional: record in logs table
        log_action($staff_id, "Marked booking #$booking_id as '$status'");

        $_SESSION['success_message'] = "✅ Booking #$booking_id updated to '$status'.";
    }

    header("Location: dashboard.php");
    exit;
}

// ✅ Handle search & filters
$search_name = trim($_GET['search_name'] ?? '');
$filter_status = trim($_GET['filter_status'] ?? '');

$query = "
    SELECT 
        b.*, 
        u.name AS customer_name, 
        s.service_name, 
        s.category
    FROM bookings b
    LEFT JOIN users u ON b.user_id = u.id
    LEFT JOIN services s ON b.service_id = s.id
    WHERE b.staff_id = :staff_id
";
$params = [':staff_id' => $staff_id];

if ($search_name) {
    $query .= " AND u.name LIKE :search_name";
    $params[':search_name'] = "%$search_name%";
}

if ($filter_status) {
    $query .= " AND b.status = :filter_status";
    $params[':filter_status'] = $filter_status;
}

$query .= " ORDER BY b.booking_time DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Staff Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .card { border-radius: 10px; }
        .badge-status { text-transform: capitalize; font-size: 0.85rem; padding: 0.4em 0.8em; }
    </style>
</head>
<body>
<div class="container my-5">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold">👷 Staff Dashboard</h2>
        <div>
            <span class="me-3 text-muted">Welcome, <?= htmlspecialchars($_SESSION['user']['name']) ?></span>
            <a href="../logout.php" class="btn btn-danger btn-sm">Logout</a>
        </div>
    </div>

    <!-- Notifications -->
    <?php if (!empty($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['error_message'])): ?>
        <div class="alert alert-warning alert-dismissible fade show">
            <?= htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Search / Filter -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label for="search_name" class="form-label">Search by Customer Name</label>
                    <input type="text" name="search_name" id="search_name" class="form-control" value="<?= htmlspecialchars($search_name) ?>">
                </div>
                <div class="col-md-3">
                    <label for="filter_status" class="form-label">Filter by Status</label>
                    <select name="filter_status" id="filter_status" class="form-select">
                        <option value="">All</option>
                        <option value="assigned" <?= $filter_status === 'assigned' ? 'selected' : '' ?>>Assigned</option>
                        <option value="approved" <?= $filter_status === 'approved' ? 'selected' : '' ?>>Approved</option>
                        <option value="completed" <?= $filter_status === 'completed' ? 'selected' : '' ?>>Completed</option>
                        <option value="not attended" <?= $filter_status === 'not attended' ? 'selected' : '' ?>>Not Completed</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Apply</button>
                </div>
                <div class="col-md-2">
                    <a href="dashboard.php" class="btn btn-secondary w-100">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Bookings Table -->
    <div class="card shadow-sm">
        <div class="card-body">
            <h5 class="card-title mb-3">🧾 Assigned Bookings</h5>
            <div class="table-responsive">
                <table class="table table-bordered align-middle text-center">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Customer</th>
                            <th>Service</th>
                            <th>Category</th>
                            <th>Car Details</th>
                            <th>Status</th>
                            <th>Booking Time</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($bookings): ?>
                            <?php foreach ($bookings as $b): 
                                $status = $b['status'] ?? 'pending';
                                $badgeClass = match($status) {
                                    'completed' => 'bg-success',
                                    'not attended' => 'bg-warning text-dark',
                                    'approved' => 'bg-info',
                                    'assigned' => 'bg-primary',
                                    'rejected' => 'bg-danger',
                                    default => 'bg-secondary'
                                };
                            ?>
                                <tr>
                                    <td><?= $b['id'] ?></td>
                                    <td><?= htmlspecialchars($b['customer_name'] ?? 'Unknown') ?></td>
                                    <td><?= htmlspecialchars($b['service_name'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($b['category'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($b['car_model'] ?? '-') ?> (<?= htmlspecialchars($b['license_plate'] ?? '-') ?>)</td>
                                    <td><span class="badge badge-status <?= $badgeClass ?>"><?= $status ?></span></td>
                                    <td><?= htmlspecialchars($b['booking_time'] ?? '-') ?></td>
                                    <td>
                                        <?php if ($status !== 'completed'): ?>
                                            <form method="POST" class="d-flex justify-content-center gap-2">
                                                <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                                                <button type="submit" name="update_booking" value="completed" class="btn btn-success btn-sm">Mark Completed</button>
                                                <button type="submit" name="update_booking" value="not attended" class="btn btn-warning btn-sm">Not Completed</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-muted">✔ Done</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="8" class="text-muted">No assigned bookings found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
