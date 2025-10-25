<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

// Ensure admin
if (!is_logged_in() || !is_admin()) {
    header('Location: /smart_car_wash/admin/index.php');
    exit;
}

// Handle bulk delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_selected'])) {
    $selected = $_POST['selected_logs'] ?? [];
    if (!empty($selected)) {
        $placeholders = implode(',', array_fill(0, count($selected), '?'));
        $stmt = $db->prepare("DELETE FROM logs WHERE id IN ($placeholders)");
        $stmt->execute($selected);
        $_SESSION['success_message'] = count($selected) . " log(s) deleted successfully.";
    } else {
        $_SESSION['error_message'] = "No logs were selected.";
    }
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}

// Fetch logs
$stmt = $db->query("
    SELECT l.*, u.name AS username
    FROM logs l
    LEFT JOIN users u ON l.user_id = u.id
    ORDER BY l.created_at DESC
    LIMIT 100
");
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Optional KPI summary for logs
$totalLogs = count($logs);
$systemLogs = count(array_filter($logs, fn($l) => $l['username'] === null));
$userLogs = $totalLogs - $systemLogs;
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>System Logs | Admin</title>
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

.insights-card {
    border-radius: 10px;
    box-shadow: 0 3px 12px rgba(0,0,0,0.08);
    transition: transform 0.2s, box-shadow 0.2s;
    color: #fff; text-align:center; padding:20px;
}
.insights-card:hover { transform: translateY(-3px); box-shadow: 0 6px 18px rgba(0,0,0,0.1); }
.insight-value { font-size: 1.9rem; font-weight: 700; }
.insight-label { font-size: 0.85rem; opacity: 0.85; margin-top: 0.2rem; }
.bg-blue { background: linear-gradient(135deg, #007bff, #3399ff); }
.bg-green { background: linear-gradient(135deg, #28a745, #6fdc8c); }
.bg-orange { background: linear-gradient(135deg, #fd7e14, #ffb36d); }
.card-table { background:#fff; border-radius:12px; padding:20px; box-shadow:0 4px 12px rgba(0,0,0,0.05); }
.table thead { background-color: #0d6efd; color: #fff; }
.table-hover tbody tr:hover { background-color: #e9f2ff; }
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
        <li><a href="users.php" class="nav-link">Users</a></li>
        <li><a href="feedback.php" class="nav-link">Feedback</a></li>
        <li><a href="insights.php" class="nav-link">Insights</a></li>
        <li><a href="activity.php" class="nav-link">Activity</a></li>
        <li><a href="logs.php" class="nav-link active">Logs</a></li>
    </ul>
</div>

<div class="main-content">
    <h4 class="mb-4">System Logs</h4>

    <!-- Optional KPI Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="insights-card bg-blue">
                <div class="insight-value"><?= $totalLogs ?></div>
                <div class="insight-label">Total Logs</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="insights-card bg-green">
                <div class="insight-value"><?= $userLogs ?></div>
                <div class="insight-label">User Logs</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="insights-card bg-orange">
                <div class="insight-value"><?= $systemLogs ?></div>
                <div class="insight-label">System Logs</div>
            </div>
        </div>
    </div>

    <!-- Logs Table -->
    <div class="card-table">
        <form method="POST" id="deleteForm">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <p class="text-muted mb-0">Select logs to delete.</p>
                <button type="submit" name="delete_selected" id="deleteBtn" class="btn btn-danger btn-sm d-none"
                        onclick="return confirm('Delete selected logs?')">Delete Selected</button>
            </div>
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle text-center">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="selectAll" class="form-check-input"></th>
                            <th>ID</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Details</th>
                            <th>Timestamp</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($logs): ?>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td><input type="checkbox" name="selected_logs[]" value="<?= $log['id'] ?>" class="form-check-input select-checkbox"></td>
                                    <td><?= $log['id'] ?></td>
                                    <td><?= htmlspecialchars($log['username'] ?? 'System') ?></td>
                                    <td><?= htmlspecialchars($log['action']) ?></td>
                                    <td><?= htmlspecialchars($log['details'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars(date("M d, Y H:i", strtotime($log['created_at']))) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="text-muted">No logs found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
const selectAll = document.getElementById('selectAll');
const checkboxes = document.querySelectorAll('.select-checkbox');
const deleteBtn = document.getElementById('deleteBtn');

function updateDeleteButton() {
    const anyChecked = Array.from(checkboxes).some(cb => cb.checked);
    deleteBtn.classList.toggle('d-none', !anyChecked);
}

selectAll.addEventListener('change', () => {
    checkboxes.forEach(cb => cb.checked = selectAll.checked);
    updateDeleteButton();
});

checkboxes.forEach(cb => cb.addEventListener('change', updateDeleteButton));
</script>
</body>
</html>
