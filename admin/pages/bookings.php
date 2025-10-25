<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

// Ensure admin
if (!is_logged_in() || !is_admin()) {
    header('Location: /smart_car_wash/admin/index.php');
    exit;
}

$current_page = $_SERVER['REQUEST_URI'];

// Handle POST actions (approve, reject, assign, delete)
// ... (Keep your existing POST logic here)

// Fetch bookings
$stmt = $db->query("
    SELECT 
        b.id, u.name AS customer_name, s.service_name,
        b.car_model, b.license_plate, b.booking_time,
        b.status, b.staff_id, st.name AS staff_name
    FROM bookings b
    LEFT JOIN users u ON b.user_id = u.id
    LEFT JOIN services s ON b.service_id = s.id
    LEFT JOIN users st ON b.staff_id = st.id
    ORDER BY b.id DESC
");
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

$staff = $db->query("SELECT id, name FROM users WHERE role='staff'")->fetchAll(PDO::FETCH_ASSOC);
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { font-family: Arial, sans-serif; background: #f5f6f8; }
header {
    position: fixed; top:0; left:0; right:0; height:60px;
    background:#007bff; color:#fff;
    display:flex; justify-content:space-between; align-items:center;
    padding:0 20px; z-index:1000;
}
header h3 { margin:0; font-size:1.2rem; }
header a { color:#fff; }

.sidebar {
    position: fixed; top:60px; left:0;
    width:220px; height:calc(100% - 60px);
    background:#f8f9fa; padding:20px 10px; border-right:1px solid #ddd; overflow-y:auto;
}
.sidebar a { display:block; padding:10px 15px; margin-bottom:5px; border-radius:6px; color:#333; text-decoration:none; }
.sidebar a:hover, .sidebar a.active { background:#007bff; color:#fff; }

.main-content { margin-left:220px; margin-top:60px; padding:20px; }

.card { border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
.table thead { background-color: #0d6efd; color: #fff; }
tr.clickable-row:hover { background-color: #eef4ff; cursor: pointer; }
.status-badge { text-transform: capitalize; font-size: 0.8rem; }
.hidden-delete { display: none; }
.btn-sm { min-width: 80px; font-weight: 500; }
</style>

<header>
    <h3>Admin Dashboard</h3>
    <a href="../../logout.php" class="btn btn-outline-light btn-sm">Logout</a>
</header>

<div class="sidebar">
    <h5 class="text-center mb-4 fw-bold text-primary"></h5>
    <ul class="nav flex-column">
        <li><a href="../dashboard.php" class="nav-link">Dashboard</a></li>
        <li><a href="bookings.php" class="nav-link active">Bookings</a></li>
        <li><a href="services.php" class="nav-link">Services</a></li>
        <li><a href="supplies.php" class="nav-link">Supplies</a></li>
        <li><a href="users.php" class="nav-link">Users</a></li>
        <li><a href="feedback.php" class="nav-link">Feedback</a></li>
        <li><a href="insights.php" class="nav-link">Insights</a></li>
        <li><a href="activity.php" class="nav-link">Activity</a></li>
        <li><a href="logs.php" class="nav-link">Logs</a></li>
    </ul>
</div>

<div class="main-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center flex-wrap mb-4">
            <h3 class="fw-bold text-primary mb-2">Manage Bookings</h3>
            <a href="../dashboard.php" class="btn btn-outline-secondary btn-sm">Back to Dashboard</a>
        </div>

        <!-- Alerts -->
        <?php if (!empty($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show shadow-sm">
                <?= htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if (!empty($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show shadow-sm">
                <?= htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card p-3">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle text-center" id="bookingsTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Customer</th>
                            <th>Service</th>
                            <th>Car Model</th>
                            <th>License Plate</th>
                            <th>Booking Date</th>
                            <th>Status</th>
                            <th>Assigned Staff</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($bookings): ?>
                        <?php foreach ($bookings as $b): ?>
                            <tr class="clickable-row" data-id="<?= $b['id'] ?>">
                                <td><?= htmlspecialchars($b['id']) ?></td>
                                <td><?= htmlspecialchars($b['customer_name'] ?? 'Unknown') ?></td>
                                <td><?= htmlspecialchars($b['service_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($b['car_model'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($b['license_plate'] ?? '-') ?></td>
                                <td><?= htmlspecialchars(date("M d, Y H:i", strtotime($b['booking_time']))) ?></td>
                                <td>
                                    <span class="badge bg-<?= match($b['status']) {
                                        'completed' => 'success',
                                        'approved' => 'primary',
                                        'assigned' => 'info',
                                        'rejected' => 'danger',
                                        default => 'secondary'
                                    } ?> status-badge">
                                        <?= htmlspecialchars(ucfirst($b['status'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <form method="POST" class="d-flex gap-1 align-items-center justify-content-center">
                                        <input type="hidden" name="id" value="<?= $b['id'] ?>">
                                        <input type="hidden" name="action" value="assign">
                                        <select name="staff_id" class="form-select form-select-sm w-auto" required>
                                            <option value="">Select</option>
                                            <?php foreach ($staff as $st): ?>
                                                <option value="<?= $st['id'] ?>" <?= $b['staff_id'] == $st['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($st['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="btn btn-primary btn-sm">Assign</button>
                                    </form>
                                </td>
                                <td>
                                    <div class="d-flex gap-2 justify-content-center">
                                        <form method="POST">
                                            <input type="hidden" name="id" value="<?= $b['id'] ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <button type="submit" class="btn btn-success btn-sm">Approve</button>
                                        </form>
                                        <form method="POST">
                                            <input type="hidden" name="id" value="<?= $b['id'] ?>">
                                            <input type="hidden" name="action" value="reject">
                                            <button type="submit" class="btn btn-danger btn-sm">Reject</button>
                                        </form>
                                    </div>
                                    <form method="POST" class="mt-2 hidden-delete">
                                        <input type="hidden" name="id" value="<?= $b['id'] ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <button type="submit" class="btn btn-outline-dark btn-sm w-100" onclick="return confirm('Delete this booking?');">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="9" class="text-muted">No bookings found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.clickable-row').forEach(row => {
    row.addEventListener('click', () => {
        const deleteBtn = row.querySelector('.hidden-delete');
        if (deleteBtn) {
            deleteBtn.classList.toggle('d-block');
        }
    });
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
