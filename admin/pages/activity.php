<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!is_logged_in() || !is_admin()) {
    header('Location: ../index.php');
    exit;
}

$recentLogs = $db->query("
    SELECT l.*, u.name AS username  
    FROM logs l
    LEFT JOIN users u ON l.user_id = u.id
    ORDER BY l.created_at DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

$recentBookings = $db->query("
    SELECT b.id, u.name AS customer_name, s.service_name, b.status, b.booking_time
    FROM bookings b
    LEFT JOIN users u ON b.user_id = u.id
    LEFT JOIN services s ON b.service_id = s.id
    ORDER BY b.id DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

$recentUsers = $db->query("
    SELECT *
    FROM users
    ORDER BY id DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body {
    font-family: Arial, sans-serif;
}
header {
    position: fixed;
    top:0; left:0; right:0;
    height:60px;
    background:#007bff;
    color:#fff;
    display:flex;
    align-items:center;
    justify-content:space-between;
    padding:0 20px;
    z-index:1000;
}
header h3 { margin:0; font-size:1.2rem; }
header a { color:#fff; }
.sidebar {
    position: fixed;
    top:60px;
    left:0;
    width:220px;
    height:calc(100% - 60px);
    background:#f8f9fa;
    padding:20px 10px;
    overflow-y:auto;
    border-right:1px solid #ddd;
}
.sidebar a {
    display:block;
    padding:10px 15px;
    margin-bottom:5px;
    border-radius:6px;
    color:#333;
    text-decoration:none;
}
.sidebar a.active, .sidebar a:hover { background:#007bff; color:#fff; }
.main-content {
    margin-left:220px;
    margin-top:60px;
    padding:20px;
}
.card { border-radius:10px; }
.list-group-item { font-size:0.875rem; }
@media (max-width: 767px) {
    .list-group-item small { display:block; }
}
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
        <li><a href="feedback.php" class="nav-link">Feedback</a></li>
        <li><a href="insights.php" class="nav-link">Insights</a></li>
        <li><a href="activity.php" class="nav-link active">Activity</a></li>
        <li><a href="logs.php" class="nav-link">Logs</a></li>
    </ul>
</div>

<div class="main-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center flex-wrap mb-4">
            <h3 class="fw-bold text-primary mb-2">Recent Activity</h3>
            <a href="../dashboard.php" class="btn btn-outline-secondary btn-sm">Back to Dashboard</a>
        </div>
        <div class="row g-3">
            <!-- Latest Logs -->
            <div class="col-12 col-md-4">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-primary text-white fw-bold">Latest Logs</div>
                    <div class="card-body p-2">
                        <ul class="list-group list-group-flush">
                        <?php if ($recentLogs): foreach($recentLogs as $log): ?>
                            <li class="list-group-item py-2">
                                <strong><?= htmlspecialchars($log['username'] ?? 'System') ?>:</strong> <?= htmlspecialchars($log['action']) ?>
                                <br><small class="text-muted"><?= htmlspecialchars($log['created_at']) ?></small>
                            </li>
                        <?php endforeach; else: ?>
                            <li class="list-group-item text-muted">No recent logs.</li>
                        <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
            <!-- Latest Bookings -->
            <div class="col-12 col-md-4">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-success text-white fw-bold">Latest Bookings</div>
                    <div class="card-body p-2">
                        <ul class="list-group list-group-flush">
                        <?php if ($recentBookings): foreach($recentBookings as $b): ?>
                            <li class="list-group-item py-2 d-flex justify-content-between align-items-center">
                                <div>
                                    <strong><?= htmlspecialchars($b['customer_name'] ?? 'Unknown') ?></strong> — <?= htmlspecialchars($b['service_name'] ?? 'Unknown Service') ?>
                                    <br><small class="text-muted"><?= htmlspecialchars($b['booking_time']) ?></small>
                                </div>
                                <span class="badge <?= $b['status']=='completed'?'bg-success':($b['status']=='pending'?'bg-warning text-dark':'bg-secondary') ?>">
                                    <?= htmlspecialchars(ucfirst($b['status'])) ?>
                                </span>
                            </li>
                        <?php endforeach; else: ?>
                            <li class="list-group-item text-muted">No recent bookings.</li>
                        <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
            <!-- New Users -->
            <div class="col-12 col-md-4">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-info text-white fw-bold">New Users</div>
                    <div class="card-body p-2">
                        <ul class="list-group list-group-flush">
                        <?php if ($recentUsers): foreach($recentUsers as $u): ?>
                            <li class="list-group-item py-2">
                                <strong><?= htmlspecialchars($u['name']) ?></strong>
                                <br><small class="text-muted"><?= htmlspecialchars($u['role']) ?> — <?= htmlspecialchars($u['created_at'] ?? '') ?></small>
                            </li>
                        <?php endforeach; else: ?>
                            <li class="list-group-item text-muted">No new users.</li>
                        <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
