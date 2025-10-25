<?php
require_once __DIR__ . '/../../includes/db.php';

// --- KPI Data ---
$totalBookings = $db->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
$pendingBookings = $db->query("SELECT COUNT(*) FROM bookings WHERE status='pending'")->fetchColumn();
$completedBookings = $db->query("SELECT COUNT(*) FROM bookings WHERE status='completed'")->fetchColumn();
$totalUsers = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalServices = $db->query("SELECT COUNT(*) FROM services")->fetchColumn();
$totalSupplies = $db->query("SELECT COUNT(*) FROM supplies")->fetchColumn();
$totalFeedback = $db->query("SELECT COUNT(*) FROM feedback")->fetchColumn();

// --- Revenue from completed bookings ---
$totalRevenue = $db->query("
    SELECT IFNULL(SUM(s.price), 0)
    FROM bookings b
    LEFT JOIN services s ON b.service_id = s.id
    WHERE b.status='completed'
")->fetchColumn();

// --- Monthly Revenue (last 12 months) ---
$monthlyRevenue = $db->query("
    SELECT strftime('%b %Y', booking_time) AS month, IFNULL(SUM(s.price),0) AS revenue
    FROM bookings b
    LEFT JOIN services s ON b.service_id = s.id
    WHERE b.status='completed'
    GROUP BY month
    ORDER BY booking_time ASC
")->fetchAll(PDO::FETCH_ASSOC);

// --- Bookings by Status ---
$bookingStatuses = $db->query("
    SELECT status, COUNT(*) as count
    FROM bookings
    GROUP BY status
")->fetchAll(PDO::FETCH_KEY_PAIR);
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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
.bg-purple { background: linear-gradient(135deg, #6f42c1, #a16eff); }
.bg-gray { background: linear-gradient(135deg, #6c757d, #a6abb1); }
.chart-container { background: #fff; border-radius: 12px; padding: 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); margin-top:20px; }
h4 { font-weight:600; color:#2c3e50; margin-bottom:20px; }
</style>

<header>
    <h3>Admin Dashboard</h3>
    <a href="../../logout.php" class="btn btn-outline-light btn-sm">Logout</a>
</header>

<div class="sidebar">
    <h5 class="text-center mb-4 fw-bold text-primary"></h5>
    <ul class="nav flex-column">
        <li><a href="../dashboard.php" class="nav-link active">Dashboard</a></li>
        <li><a href="bookings.php" class="nav-link">Bookings</a></li>
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
    <h4>Admin Insights</h4>

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="insights-card bg-blue">
                <div class="insight-value"><?= $totalBookings ?></div>
                <div class="insight-label">Total Bookings</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="insights-card bg-green">
                <div class="insight-value"><?= $completedBookings ?></div>
                <div class="insight-label">Completed Bookings</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="insights-card bg-orange">
                <div class="insight-value"><?= $pendingBookings ?></div>
                <div class="insight-label">Pending Bookings</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="insights-card bg-purple">
                <div class="insight-value"><?= $totalUsers ?></div>
                <div class="insight-label">Registered Users</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="insights-card bg-gray">
                <div class="insight-value"><?= $totalSupplies ?></div>
                <div class="insight-label">Supplies in Stock</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="insights-card bg-green">
                <div class="insight-value"><?= number_format($totalRevenue,2) ?></div>
                <div class="insight-label">Total Revenue (KSh)</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="insights-card bg-orange">
                <div class="insight-value"><?= $totalFeedback ?></div>
                <div class="insight-label">Customer Feedback</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="insights-card bg-purple">
                <div class="insight-value"><?= $totalServices ?></div>
                <div class="insight-label">Available Services</div>
            </div>
        </div>
    </div>

    <!-- Trend Charts -->
    <div class="row g-4">
        <div class="col-md-6">
            <div class="chart-container">
                <h6 class="fw-bold mb-3">Booking Status Overview</h6>
                <canvas id="bookingStatusChart" height="150"></canvas>
            </div>
        </div>
        <div class="col-md-6">
            <div class="chart-container">
                <h6 class="fw-bold mb-3">Monthly Revenue</h6>
                <canvas id="monthlyRevenueChart" height="150"></canvas>
            </div>
        </div>
    </div>
</div>

<script>
// Booking Status Pie Chart
new Chart(document.getElementById('bookingStatusChart'), {
    type:'pie',
    data:{
        labels: <?= json_encode(array_keys($bookingStatuses)) ?>,
        datasets:[{
            data: <?= json_encode(array_values($bookingStatuses)) ?>,
            backgroundColor:['#fd7e14','#28a745','#007bff','#6f42c1','#dc3545']
        }]
    },
    options:{ responsive:true, plugins:{ legend:{position:'bottom'} } }
});

// Monthly Revenue Line Chart
new Chart(document.getElementById('monthlyRevenueChart'), {
    type:'line',
    data:{
        labels: <?= json_encode(array_column($monthlyRevenue,'month')) ?>,
        datasets:[{
            data: <?= json_encode(array_column($monthlyRevenue,'revenue')) ?>,
            borderColor:'#007bff',
            backgroundColor:'rgba(0,123,255,0.2)',
            fill:true,
            tension:0.3,
            pointRadius:4
        }]
    },
    options:{ responsive:true, plugins:{ legend:{display:false} } }
});
</script>
