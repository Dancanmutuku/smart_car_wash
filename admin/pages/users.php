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
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $role = trim($_POST['role'] ?? 'staff');

    try {
        if ($action === 'delete' && $id) {
            $stmt = $db->prepare("DELETE FROM users WHERE id=?");
            $stmt->execute([$id]);
            $_SESSION['success_message'] = "User deleted successfully.";
        } elseif ($name && $email) {
            if ($id) {
                if (!empty($password)) {
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("UPDATE users SET name=?, email=?, password=?, role=? WHERE id=?");
                    $stmt->execute([$name, $email, $hashed, $role, $id]);
                } else {
                    $stmt = $db->prepare("UPDATE users SET name=?, email=?, role=? WHERE id=?");
                    $stmt->execute([$name, $email, $role, $id]);
                }
                $_SESSION['success_message'] = "User updated successfully.";
            } else {
                if (empty($password)) {
                    $_SESSION['error_message'] = "Password is required for new users.";
                } else {
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$name, $email, $hashed, $role]);
                    $_SESSION['success_message'] = "New user added successfully.";
                }
            }
        } else {
            $_SESSION['error_message'] = "Please fill in all required fields.";
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Database error: " . htmlspecialchars($e->getMessage());
    }

    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}

// Fetch users
$users = $db->query("SELECT * FROM users ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Manage Users | Admin</title>
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
        <li><a href="supplies.php" class="nav-link">Supplies</a></li>
        <li><a href="users.php" class="nav-link active">Users</a></li>
        <li><a href="feedback.php" class="nav-link">Feedback</a></li>
        <li><a href="insights.php" class="nav-link">Insights</a></li>
        <li><a href="activity.php" class="nav-link">Activity</a></li>
        <li><a href="logs.php" class="nav-link">Logs</a></li>
    </ul>
</div>

<div class="main-content">
    <h4 class="mb-4">Users Management</h4>

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

    <!-- Add/Edit User Form -->
    <div class="card p-3 mb-4">
        <h5 class="fw-semibold mb-3" id="formTitle">Add New User</h5>
        <form method="POST" class="row g-3">
            <input type="hidden" name="id" id="userId">
            <input type="hidden" name="action" value="save" id="formAction">

            <div class="col-md-6">
                <label class="form-label">Name *</label>
                <input type="text" name="name" id="userName" class="form-control" required>
            </div>

            <div class="col-md-6">
                <label class="form-label">Email *</label>
                <input type="email" name="email" id="userEmail" class="form-control" required>
            </div>

            <div class="col-md-6">
                <label class="form-label">Password</label>
                <input type="password" name="password" id="userPassword" class="form-control" placeholder="Leave blank to keep existing password">
            </div>

            <div class="col-md-6">
                <label class="form-label">Role</label>
                <select name="role" id="userRole" class="form-select">
                    <option value="admin">Admin</option>
                    <option value="staff" selected>Staff</option>
                    <option value="customer">Customer</option>
                </select>
            </div>

            <div class="col-12 d-flex align-items-end">
                <button type="submit" class="btn btn-success w-100" id="saveButton">Save User</button>
            </div>
        </form>
    </div>

    <!-- Users Table -->
    <div class="card p-3">
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle text-center">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($users): ?>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td><?= $u['id'] ?></td>
                                <td><?= htmlspecialchars($u['name']) ?></td>
                                <td><?= htmlspecialchars($u['email']) ?></td>
                                <td><?= htmlspecialchars($u['role']) ?></td>
                                <td class="d-flex justify-content-center gap-2 flex-wrap">
                                    <button 
                                        type="button" 
                                        class="btn btn-outline-primary btn-sm edit-btn"
                                        data-id="<?= $u['id'] ?>"
                                        data-name="<?= htmlspecialchars($u['name'], ENT_QUOTES) ?>"
                                        data-email="<?= htmlspecialchars($u['email'], ENT_QUOTES) ?>"
                                        data-role="<?= htmlspecialchars($u['role'], ENT_QUOTES) ?>">
                                        Edit
                                    </button>

                                    <form method="POST" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                        <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <button type="submit" class="btn btn-outline-danger btn-sm">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-muted">No users found.</td></tr>
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
        document.getElementById('formTitle').textContent = "Edit User";
        document.getElementById('userId').value = btn.dataset.id;
        document.getElementById('userName').value = btn.dataset.name;
        document.getElementById('userEmail').value = btn.dataset.email;
        document.getElementById('userRole').value = btn.dataset.role;
        document.getElementById('userPassword').value = '';
        document.getElementById('formAction').value = 'save';
        document.getElementById('saveButton').textContent = "Update User";
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
});
</script>
</body>
</html>
