<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

// ✅ Restrict access to logged-in staff only
if (!is_logged_in() || !is_staff()) {
    header('Location: ../index.php');
    exit;
}

// ✅ Handle booking status updates
if (isset($_POST['update_booking'])) {
    $booking_id = (int)$_POST['booking_id'];
    $status = clean_input($_POST['status']);
    $staff_id = $_SESSION['user']['id'];

    $stmt = $db->prepare("
        UPDATE bookings
        SET status = :status
        WHERE id = :id AND staff_id = :staff_id
    ");
    $stmt->execute([
        ':status' => $status,
        ':id' => $booking_id,
        ':staff_id' => $staff_id
    ]);

    $_SESSION['success_message'] = "✅ Booking #$booking_id status updated to '$status'.";
    header("Location: dashboard.php");
    exit;
}

// ✅ Fetch bookings assigned to this staff member
$stmt = $db->prepare("
    SELECT 
        b.*, 
        u.name AS customer_name, 
        s.service_name, 
        s.category
    FROM bookings b
    LEFT JOIN users u ON b.user_id = u.id
    LEFT JOIN services s ON b.service_id = s.id
    WHERE b.staff_id = :staff_id
    ORDER BY b.booking_time DESC
");
$stmt->execute([':staff_id' => $_SESSION['user']['id']]);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Staff Dashboard - Smart Car Wash</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
    body { background-color: #f8f9fa; }
    .card { border-radius: 10px; }
    .badge-status {
        text-transform: capitalize;
        font-size: 0.85rem;
        padding: 0.4em 0.8em;
    }
</style>
</head>
<body>

<div class="container my-5">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold">👨‍🔧 Staff Dashboard</h2>
        <a href="../logout.php" class="btn btn-danger btn-sm">Logout</a>
    </div>

    <!-- Success Message -->
    <?php if (!empty($_SESSION['success_message'])): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
        </div>
    <?php endif; ?>

    <!-- Assigned Bookings Table -->
    <div class="card shadow-sm">
        <div class="card-body">
            <h5 class="card-title mb-3">Assigned Bookings</h5>
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
                            <?php foreach ($bookings as $b): ?>
                                <?php 
                                    $status = $b['status'] ?? 'pending';
                                    $badgeClass = match($status) {
                                        'approved' => 'bg-info',
                                        'completed' => 'bg-success',
                                        'not attended' => 'bg-warning text-dark',
                                        'rejected' => 'bg-danger',
                                        default => 'bg-secondary'
                                    };
                                ?>
                                <tr>
                                    <td><?= $b['id'] ?></td>
                                    <td><?= htmlspecialchars($b['customer_name'] ?? 'Unknown') ?></td>
                                    <td><?= htmlspecialchars($b['service_name'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($b['category'] ?? 'General') ?></td>
                                    <td><?= htmlspecialchars($b['car_model'] ?? '') ?> (<?= htmlspecialchars($b['license_plate'] ?? '') ?>)</td>
                                    <td><span class="badge badge-status <?= $badgeClass ?>"><?= $status ?></span></td>
                                    <td><?= htmlspecialchars($b['booking_time'] ?? '') ?></td>
                                    <td>
                                        <form method="POST" class="d-flex flex-column align-items-center gap-2">
                                            <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                                            <select name="status" class="form-select form-select-sm w-auto">
                                                <?php 
                                                $options = ['approved', 'completed', 'not attended', 'rejected'];
                                                foreach ($options as $option):
                                                ?>
                                                    <option value="<?= $option ?>" <?= $status == $option ? 'selected' : '' ?>>
                                                        <?= ucfirst($option) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button name="update_booking" class="btn btn-primary btn-sm">Update</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="8" class="text-muted">No assigned bookings.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

</body>
</html>
