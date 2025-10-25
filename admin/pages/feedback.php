<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

// Ensure admin
if (!is_logged_in() || !is_admin()) {
    header('Location: /smart_car_wash/admin/index.php');
    exit;
}

// Handle delete action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $id = (int) $_POST['delete_id'];
    try {
        $stmt = $db->prepare("DELETE FROM feedback WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['success_message'] = "Feedback entry #$id deleted successfully.";
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Error deleting feedback: " . htmlspecialchars($e->getMessage());
    }
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}

// Fetch feedback
$stmt = $db->query("
    SELECT f.id, u.name AS customer_name, f.comment AS message, f.rating, f.created_at
    FROM feedback f
    LEFT JOIN users u ON f.user_id = u.id
    ORDER BY f.id DESC
");
$feedback = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { font-family: Arial, sans-serif; background: #f5f6f8; }
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

.card { border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
.table thead { background-color: #0d6efd; color: #fff; }
.table-hover tbody tr:hover { cursor: pointer; background-color: #e9f2ff; }
.selected-row { background-color: #d0e2ff !important; }
</style>

<header>
    <h3>Admin Dashboard</h3>
    <a href="../../logout.php" class="btn btn-outline-light btn-sm">Logout</a>
</header>

<div class="sidebar">
    <h5 class="text-center mb-4 fw-bold text-primary"></h5>
    <ul class="nav flex-column">
        <li><a href="../dashboard.php" class="nav-link">Dashboard</a></li>
        <li><a href="bookings.php" class="nav-link">Bookings</a></li>
        <li><a href="services.php" class="nav-link">Services</a></li>
        <li><a href="supplies.php" class="nav-link">Supplies</a></li>
        <li><a href="users.php" class="nav-link">Users</a></li>
        <li><a href="feedback.php" class="nav-link active">Feedback</a></li>
        <li><a href="insights.php" class="nav-link">Insights</a></li>
        <li><a href="activity.php" class="nav-link">Activity</a></li>
        <li><a href="logs.php" class="nav-link">Logs</a></li>
    </ul>
</div>

<div class="main-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center flex-wrap mb-4">
            <h3 class="fw-bold text-primary mb-2">Customer Feedback</h3>
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
            <p class="text-muted">Click on a feedback row to delete.</p>

            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle text-center" id="feedbackTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Customer</th>
                            <th>Message</th>
                            <th>Rating</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($feedback): ?>
                            <?php foreach ($feedback as $f): ?>
                                <tr data-id="<?= $f['id'] ?>">
                                    <td><?= $f['id'] ?></td>
                                    <td><?= htmlspecialchars($f['customer_name'] ?? 'Unknown') ?></td>
                                    <td><?= htmlspecialchars($f['message'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($f['rating']) ?>/5</td>
                                    <td><?= htmlspecialchars(date("M d, Y H:i", strtotime($f['created_at']))) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-muted">No feedback available.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Feedback</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete feedback entry <strong id="feedbackIdText"></strong>?</p>
                <input type="hidden" name="delete_id" id="deleteId">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger">Delete</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.querySelectorAll('#feedbackTable tbody tr').forEach(row => {
    row.addEventListener('click', () => {
        document.querySelectorAll('#feedbackTable tr').forEach(r => r.classList.remove('selected-row'));
        row.classList.add('selected-row');

        const id = row.dataset.id;
        document.getElementById('deleteId').value = id;
        document.getElementById('feedbackIdText').textContent = "#" + id;
        const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
        modal.show();
    });
});
</script>
