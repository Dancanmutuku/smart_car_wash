<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

// Ensure admin
if (!is_logged_in() || !is_admin()) {
    header('Location: /smart_car_wash/admin/index.php');
    exit;
}

// Handle add/edit/delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $action = $_POST['action'] ?? 'add';
    $name = trim($_POST['name'] ?? '');
    $quantity = intval($_POST['quantity'] ?? 0);
    $unit = trim($_POST['unit'] ?? '');

    try {
        if ($action === 'delete' && $id) {
            $stmt = $db->prepare("DELETE FROM supplies WHERE id=?");
            $stmt->execute([$id]);
            $_SESSION['success_message'] = "Supply deleted successfully.";
        } elseif ($name && $quantity > 0 && $unit) {
            if ($action === 'edit' && $id) {
                $stmt = $db->prepare("UPDATE supplies SET name=?, quantity=?, unit=? WHERE id=?");
                $stmt->execute([$name, $quantity, $unit, $id]);
                $_SESSION['success_message'] = "Supply updated successfully.";
            } else {
                $stmt = $db->prepare("INSERT INTO supplies (name, quantity, unit) VALUES (?, ?, ?)");
                $stmt->execute([$name, $quantity, $unit]);
                $_SESSION['success_message'] = "New supply added successfully.";
            }
        } else {
            $_SESSION['error_message'] = "Please fill all fields correctly.";
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Database error: " . htmlspecialchars($e->getMessage());
    }

    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}

// Fetch supplies
$supplies = $db->query("SELECT * FROM supplies ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Supplies | Admin</title>
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
        <li><a href="services.php" class="nav-link">Services</a></li>
        <li><a href="supplies.php" class="nav-link active">Supplies</a></li>
        <li><a href="users.php" class="nav-link">Users</a></li>
        <li><a href="feedback.php" class="nav-link">Feedback</a></li>
        <li><a href="insights.php" class="nav-link">Insights</a></li>
        <li><a href="activity.php" class="nav-link">Activity</a></li>
        <li><a href="logs.php" class="nav-link">Logs</a></li>
    </ul>
</div>

<div class="main-content">
    <h4 class="mb-4">Supplies Management</h4>

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

    <!-- Add/Edit Supply Form -->
    <div class="card p-3 mb-4">
        <h5 class="fw-semibold mb-3" id="formTitle">Add New Supply</h5>
        <form method="POST" class="row g-2">
            <input type="hidden" name="id" id="supplyId">
            <input type="hidden" name="action" value="add" id="formAction">

            <div class="col-md-4">
                <input name="name" id="supplyName" class="form-control" placeholder="Item name" required>
            </div>
            <div class="col-md-3">
                <input name="quantity" id="supplyQty" type="number" class="form-control" placeholder="Qty" required>
            </div>
            <div class="col-md-3">
                <input name="unit" id="supplyUnit" class="form-control" placeholder="Unit (L, pcs, kg)" required>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-success w-100" id="saveButton">Add Supply</button>
            </div>
        </form>
    </div>

    <!-- Supplies Table -->
    <div class="card p-3">
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle text-center">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Quantity</th>
                        <th>Unit</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($supplies): ?>
                        <?php foreach ($supplies as $s): ?>
                            <tr>
                                <td><?= $s['id'] ?></td>
                                <td><?= htmlspecialchars($s['name']) ?></td>
                                <td><?= $s['quantity'] ?></td>
                                <td><?= htmlspecialchars($s['unit']) ?></td>
                                <td class="d-flex justify-content-center gap-2 flex-wrap">
                                    <button 
                                        type="button" 
                                        class="btn btn-outline-primary btn-sm edit-btn"
                                        data-id="<?= $s['id'] ?>"
                                        data-name="<?= htmlspecialchars($s['name'], ENT_QUOTES) ?>"
                                        data-qty="<?= $s['quantity'] ?>"
                                        data-unit="<?= htmlspecialchars($s['unit'], ENT_QUOTES) ?>">
                                        Edit
                                    </button>

                                    <form method="POST" onsubmit="return confirm('Are you sure you want to delete this supply?');">
                                        <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <button type="submit" class="btn btn-outline-danger btn-sm">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-muted">No supplies found.</td></tr>
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
        document.getElementById('formTitle').textContent = "Edit Supply";
        document.getElementById('supplyId').value = btn.dataset.id;
        document.getElementById('supplyName').value = btn.dataset.name;
        document.getElementById('supplyQty').value = btn.dataset.qty;
        document.getElementById('supplyUnit').value = btn.dataset.unit;
        document.getElementById('formAction').value = 'edit';
        document.getElementById('saveButton').textContent = "Update Supply";
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
});
</script>
</body>
</html>
