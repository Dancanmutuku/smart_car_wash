<?php
require_once __DIR__ . '/../includes/db.php';

// --- KPI Data ---
$totalBookings = $db->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
$completedBookings = $db->query("SELECT COUNT(*) FROM bookings WHERE status='completed'")->fetchColumn();
$pendingBookings = $db->query("SELECT COUNT(*) FROM bookings WHERE status='pending'")->fetchColumn();
$totalRevenue = $db->query("SELECT IFNULL(SUM(s.price),0) FROM bookings b LEFT JOIN services s ON b.service_id=s.id WHERE b.status='completed'")->fetchColumn();
$totalUsers = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalServices = $db->query("SELECT COUNT(*) FROM services")->fetchColumn();
$totalSupplies = $db->query("SELECT COUNT(*) FROM supplies")->fetchColumn();
$totalFeedback = $db->query("SELECT COUNT(*) FROM feedback")->fetchColumn();

// --- Booking Status ---
$bookingStatuses = $db->query("
    SELECT status, COUNT(*) as count 
    FROM bookings 
    GROUP BY status
")->fetchAll(PDO::FETCH_KEY_PAIR);

// --- Monthly Revenue (last 12 months) ---
$monthlyRevenue = $db->query("
    SELECT strftime('%b %Y', booking_time) AS month, IFNULL(SUM(s.price),0) AS revenue
    FROM bookings b
    LEFT JOIN services s ON b.service_id = s.id
    WHERE b.status='completed'
    GROUP BY month
    ORDER BY booking_time ASC
")->fetchAll(PDO::FETCH_ASSOC);

// --- Recent Bookings ---
$recentBookings = $db->query("
    SELECT b.id, u.name AS customer, s.service_name, b.status, b.booking_time
    FROM bookings b
    LEFT JOIN users u ON b.user_id=u.id
    LEFT JOIN services s ON b.service_id=s.id
    ORDER BY b.booking_time DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// --- Top 3 Frequent Customers ---
$topCustomers = $db->query("
    SELECT 
        u.name AS customer, 
        COUNT(b.id) AS total_bookings
    FROM bookings b
    LEFT JOIN users u ON b.user_id = u.id
    GROUP BY b.user_id
    ORDER BY total_bookings DESC
    LIMIT 3
")->fetchAll(PDO::FETCH_ASSOC);


// --- Recent Feedback ---
$recentFeedback = $db->query("
    SELECT f.id, u.name AS customer, f.comment, f.rating, f.created_at
    FROM feedback f
    LEFT JOIN users u ON f.user_id=u.id
    ORDER BY f.created_at DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
body {
  font-family: 'Segoe UI', sans-serif;
  background-color: #f8f9fa;
}
.navbar {
  background-color: #007bff;
}
.navbar-brand, .navbar-nav .nav-link, .navbar-toggler-icon {
  color: #fff !important;
}
.sidebar {
  height: 100vh;
  background-color: #fff;
  border-right: 1px solid #dee2e6;
}
.sidebar a {
  color: #333;
  text-decoration: none;
  display: block;
  padding: 10px 20px;
  border-radius: 5px;
}
.sidebar a.active, .sidebar a:hover {
  background-color: #007bff;
  color: #fff;
}
.card {
  border: none;
  border-radius: 12px;
  box-shadow: 0 2px 6px rgba(0,0,0,0.08);
}
.card h6 {
  font-size: 0.9rem;
  font-weight: 600;
  margin-bottom: 5px;
}
.card .value {
  font-size: 1.6rem;
  font-weight: bold;
}
@media (max-width: 768px) {
  .sidebar {
    position: fixed;
    z-index: 1000;
    width: 220px;
    top: 56px;
    left: -220px;
    transition: all 0.3s;
  }
  .sidebar.show {
    left: 0;
  }
}
</style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark fixed-top">
  <div class="container-fluid">
    <button class="navbar-toggler" type="button" id="sidebarToggle">
      <span class="navbar-toggler-icon"></span>
    </button>
    <a class="navbar-brand fw-bold" href="#">Admin Dashboard</a>
    <a href="?logout=1" class="btn btn-outline-light btn-sm">Logout</a>
  </div>
</nav>

<div class="container-fluid">
  <div class="row">
    <!-- Sidebar -->
    <nav id="sidebar" class="col-md-2 d-md-block sidebar pt-3">
      <div class="list-group list-group-flush">
        <a href="pages/bookings.php" class="list-group-item list-group-item-action">Bookings</a>
        <a href="pages/services.php" class="list-group-item list-group-item-action">Services</a>
        <a href="pages/supplies.php" class="list-group-item list-group-item-action">Supplies</a>
        <a href="pages/users.php" class="list-group-item list-group-item-action">Users</a>
        <a href="pages/feedback.php" class="list-group-item list-group-item-action">Feedback</a>
        <a href="pages/insights.php" class="list-group-item list-group-item-action">Insights</a>
        <a href="pages/activity.php" class="list-group-item list-group-item-action">System Activity</a>
        <a href="pages/logs.php" class="list-group-item list-group-item-action">Logs</a>
      </div>
    </nav>

    <!-- Main Content -->
    <main class="col-md-10 ms-sm-auto px-md-4 mt-5 pt-3">

      <!-- KPI Cards -->
      <div class="row g-3 mb-4">
        <?php
        $cards = [
          ['Total Bookings', $totalBookings, 'primary'],
          ['Completed Bookings', $completedBookings, 'success'],
          ['Pending Bookings', $pendingBookings, 'warning'],
          ['Total Users', $totalUsers, 'info'],
          ['Services', $totalServices, 'secondary'],
          ['Supplies', $totalSupplies, 'dark'],
          ['Revenue (KSh)', number_format($totalRevenue,2), 'success'],
          ['Feedback', $totalFeedback, 'danger']
        ];
        foreach ($cards as $c): ?>
          <div class="col-6 col-md-3">
            <div class="card text-center text-white bg-<?= $c[2] ?>">
              <div class="card-body">
                <h6><?= $c[0] ?></h6>
                <div class="value"><?= $c[1] ?></div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- Charts + Top Customers -->
      <div class="row g-4 mb-4">
        <div class="col-md-4">
          <div class="card p-3">
            <h6>Booking Status</h6>
            <canvas id="bookingStatusChart" height="150"></canvas>
          </div>
        </div>
        <div class="col-md-5">
          <div class="card p-3">
            <h6>Monthly Revenue</h6>
            <canvas id="monthlyRevenueChart" height="150"></canvas>
          </div>
        </div>
        <div class="col-md-3">
          <div class="card p-3">
            <h6>Top 3 Customers</h6>
            <ul class="list-group list-group-flush">
              <?php if (!empty($topCustomers)): ?>
                <?php foreach ($topCustomers as $index => $cust): 
                  $medal = ['','',''][$index] ?? '';
                ?>
                  <li class="list-group-item d-flex justify-content-between align-items-center">
                    <?= $medal ?> <?= htmlspecialchars($cust['customer']) ?>
                    <span class="badge bg-primary rounded-pill"><?= $cust['total_bookings'] ?> bookings</span>
                  </li>
                <?php endforeach; ?>
              <?php else: ?>
                <li class="list-group-item text-muted">No customer data</li>
              <?php endif; ?>
            </ul>
          </div>
        </div>
      </div>

      <!-- Recent Bookings -->
      <div class="card mb-4">
        <div class="card-header bg-light fw-bold">Recent Bookings</div>
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead class="table-light">
              <tr>
                <th>ID</th><th>Customer</th><th>Service</th><th>Status</th><th>Date</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach($recentBookings as $b): ?>
              <tr>
                <td><?= $b['id'] ?></td>
                <td><?= htmlspecialchars($b['customer']) ?></td>
                <td><?= htmlspecialchars($b['service_name']) ?></td>
                <td><span class="badge bg-<?= $b['status']=='completed'?'success':'warning' ?>">
                    <?= ucfirst($b['status']) ?></span></td>
                <td><?= date("M d, Y", strtotime($b['booking_time'])) ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Recent Feedback -->
      <div class="card mb-5">
        <div class="card-header bg-light fw-bold">Recent Feedback</div>
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead class="table-light">
              <tr>
                <th>ID</th><th>Customer</th><th>Comment</th><th>Rating</th><th>Date</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach($recentFeedback as $f): ?>
              <tr>
                <td><?= $f['id'] ?></td>
                <td><?= htmlspecialchars($f['customer']) ?></td>
                <td><?= htmlspecialchars($f['comment']) ?></td>
                <td><?= $f['rating'] ?>/5</td>
                <td><?= date("M d, Y", strtotime($f['created_at'])) ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

    </main>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Sidebar Toggle
document.getElementById('sidebarToggle').addEventListener('click', () => {
  document.getElementById('sidebar').classList.toggle('show');
});

// Booking Status Chart
new Chart(document.getElementById('bookingStatusChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode(array_keys($bookingStatuses)) ?>,
    datasets: [{
      data: <?= json_encode(array_values($bookingStatuses)) ?>,
      backgroundColor: ['#007bff','#28a745','#fd7e14','#6f42c1','#dc3545']
    }]
  },
  options: { plugins:{legend:{display:false}}, responsive:true }
});

// Monthly Revenue Chart
new Chart(document.getElementById('monthlyRevenueChart'), {
  type: 'line',
  data: {
    labels: <?= json_encode(array_column($monthlyRevenue,'month')) ?>,
    datasets: [{
      data: <?= json_encode(array_column($monthlyRevenue,'revenue')) ?>,
      borderColor:'#28a745',
      backgroundColor:'rgba(40,167,69,0.2)',
      fill:true,
      tension:0.3
    }]
  },
  options: { plugins:{legend:{display:false}}, responsive:true }
});
</script>
</body>
</html>