<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

// Ensure admin
if (!is_logged_in() || !is_admin()) {
    header('Location: /smart_car_wash/admin/index.php');
    exit;
}

// Handle add/edit/delete actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $action = $_POST['action'] ?? 'save';
    $service_name = trim($_POST['service_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $price = (float) ($_POST['price'] ?? 0);
    $duration = (int) ($_POST['duration'] ?? 0);

    try {
        if ($action === 'delete' && $id) {
            $stmt = $db->prepare("DELETE FROM services WHERE id=?");
            $stmt->execute([$id]);
            $_SESSION['success_message'] = "Service deleted successfully.";
        } elseif ($service_name && $price > 0) {
            if ($id) {
                $stmt = $db->prepare("UPDATE services SET service_name=?, description=?, category=?, price=?, duration=? WHERE id=?");
                $stmt->execute([$service_name, $description, $category, $price, $duration, $id]);
                $_SESSION['success_message'] = "Service updated successfully.";
            } else {
                $stmt = $db->prepare("INSERT INTO services (service_name, description, category, price, duration) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$service_name, $description, $category, $price, $duration]);
                $_SESSION['success_message'] = "New service added successfully.";
            }
        } else {
            $_SESSION['error_message'] = "Please fill all required fields (Service Name and Price).";
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Database error: " . htmlspecialchars($e->getMessage());
    }

    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}

// Fetch all services
$stmt = $db->query("SELECT * FROM services ORDER BY id DESC");
$services = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Manage Services | Admin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { font-family:'Segoe UI', sans-serif; background:#f4f5f7; margin:0; }
header {
    position: fixed; top:0; left:0; right:0; height:60px;
    background:#007bff; color:#fff; display:flex; justify-content:space-between; align-items:center;
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

.card { border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); margin-bottom:20px; }
.table thead { background-color: #0d6efd; color: #fff; }
.table-hover tbody tr:hover { background-color: #e9f2ff; }
.btn-sm { border-radius:6px; font-weight:500; }
textarea { resize:none; }
</style>
</head>
<body>

<header>
    <h3>Admin Dashboard</h3>
    <a href="../../logout.php" class="btn btn-outline-light btn-sm">Logout</a>
</header>

<div class="sidebar">
    <ul class="nav flex-column">
        <li><a href="../dashboard.php" class="nav-link">Dashboard</a></li>
        <li><a href="bookings.php" class="nav-link">Bookings</a></li>
        <li><a href="services.php" class="nav-link active">Services</a></li>
        <li><a href="supplies.php" class="nav-link">Supplies</a></li>
        <li><a href="users.php" class="nav-link">Users</a></li>
        <li><a href="feedback.php" class="nav-link">Feedback</a></li>
        <li><a href="insights.php" class="nav-link">Insights</a></li>
        <li><a href="activity.php" class="nav-link">Activity</a></li>
        <li><a href="logs.php" class="nav-link">Logs</a></li>
    </ul>
</div>

<div class="main-content">
    <h4 class="mb-4">Manage Services</h4>

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

    <!-- Add/Edit Service -->
    <div class="card p-3">
        <h5 class="fw-semibold mb-3" id="formTitle">Add New Service</h5>
        <form method="POST" class="row g-3">
            <input type="hidden" name="id" id="serviceId">
            <input type="hidden" name="action" value="save">

            <div class="col-md-6">
                <label class="form-label">Service Name *</label>
                <input type="text" name="service_name" id="serviceName" class="form-control" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Category</label>
                <input type="text" name="category" id="serviceCategory" class="form-control" placeholder="e.g. Car Wash, Interior">
            </div>

            <div class="col-12">
                <label class="form-label">Description</label>
                <textarea name="description" id="serviceDescription" class="form-control" rows="2"></textarea>
            </div>

            <div class="col-md-4">
                <label class="form-label">Price (Ksh) *</label>
                <input type="number" step="0.01" name="price" id="servicePrice" class="form-control" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Duration (minutes)</label>
                <input type="number" name="duration" id="serviceDuration" class="form-control">
            </div>

            <div class="col-md-4 d-flex align-items-end">
                <button type="submit" class="btn btn-success w-100" id="saveButton">Save Service</button>
            </div>
        </form>
    </div>

    <!-- Services Table -->
    <div class="card p-3">
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle text-center">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Service Name</th>
                        <th>Description</th>
                        <th>Category</th>
                        <th>Price (Ksh)</th>
                        <th>Duration (min)</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($services): ?>
                        <?php foreach ($services as $s): ?>
                            <tr>
                                <td><?= $s['id'] ?></td>
                                <td><?= htmlspecialchars($s['service_name']) ?></td>
                                <td><?= htmlspecialchars($s['description'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($s['category'] ?? 'General') ?></td>
                                <td><?= number_format($s['price'], 2) ?></td>
                                <td><?= htmlspecialchars($s['duration'] ?? '-') ?></td>
                                <td class="d-flex justify-content-center gap-2 flex-wrap">
                                    <button type="button" class="btn btn-outline-primary btn-sm edit-btn"
                                        data-id="<?= $s['id'] ?>"
                                        data-name="<?= htmlspecialchars($s['service_name'], ENT_QUOTES) ?>"
                                        data-desc="<?= htmlspecialchars($s['description'], ENT_QUOTES) ?>"
                                        data-cat="<?= htmlspecialchars($s['category'], ENT_QUOTES) ?>"
                                        data-price="<?= htmlspecialchars($s['price']) ?>"
                                        data-duration="<?= htmlspecialchars($s['duration']) ?>">
                                        Edit
                                    </button>

                                    <form method="POST" onsubmit="return confirm('Are you sure you want to delete this service?');">
                                        <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <button type="submit" class="btn btn-outline-danger btn-sm">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="text-muted">No services found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.querySelectorAll('.edit-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('formTitle').textContent = "Edit Service";
        document.getElementById('serviceId').value = btn.dataset.id;
        document.getElementById('serviceName').value = btn.dataset.name;
        document.getElementById('serviceDescription').value = btn.dataset.desc;
        document.getElementById('serviceCategory').value = btn.dataset.cat;
        document.getElementById('servicePrice').value = btn.dataset.price;
        document.getElementById('serviceDuration').value = btn.dataset.duration;
        document.getElementById('saveButton').textContent = "Update Service";
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
});
</script>
</body>
</html>
