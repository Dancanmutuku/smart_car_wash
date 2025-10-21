<?php
session_start();
require_once __DIR__.'/../includes/db.php';
require_once __DIR__.'/../includes/functions.php';

// Ensure admin
if(!is_logged_in() || !is_admin()){
    header('Location: ../index.php'); exit;
}

// Create optional tables if missing
try {
    $db->exec("CREATE TABLE IF NOT EXISTS supplies (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        quantity INTEGER DEFAULT 0,
        unit TEXT,
        last_updated DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS activity_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        action TEXT,
        details TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
} catch (PDOException $e) {
    // ignore non-fatal
}

function log_activity($db, $user_id, $action, $details=''){
    $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (:uid,:action,:details)");
    $stmt->execute([':uid'=>$user_id,':action'=>$action,':details'=>$details]);
}
$admin_id = $_SESSION['user']['id'];

// --- Handle inline booking actions (approve / reject / assign) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Booking action (approve/reject/assign)
    if (isset($_POST['booking_action']) && isset($_POST['booking_id'])) {
        $booking_id = (int)$_POST['booking_id'];
        $action = $_POST['booking_action'];
        $staff_id = isset($_POST['staff_id']) && $_POST['staff_id'] !== '' ? (int)$_POST['staff_id'] : null;

        // Verify booking exists
        $exists = $db->prepare("SELECT COUNT(*) FROM bookings WHERE id = :id");
        $exists->execute([':id'=>$booking_id]);
        if (!$exists->fetchColumn()) {
            $_SESSION['flash_message'] = "Booking #$booking_id not found.";
            header('Location: dashboard.php'); exit;
        }

        if ($action === 'approve'){
            $stmt = $db->prepare("UPDATE bookings SET status='approved' WHERE id=:id");
            $stmt->execute([':id'=>$booking_id]);
            log_activity($db,$admin_id,'approve_booking','Approved booking #'.$booking_id);
            $_SESSION['flash_message'] = "Booking #$booking_id approved.";
        } elseif ($action === 'reject'){
            $stmt = $db->prepare("UPDATE bookings SET status='rejected' WHERE id=:id");
            $stmt->execute([':id'=>$booking_id]);
            log_activity($db,$admin_id,'reject_booking','Rejected booking #'.$booking_id);
            $_SESSION['flash_message'] = "Booking #$booking_id rejected.";
        } elseif ($action === 'assign'){
            if ($staff_id) {
                // ensure staff exists and has role staff
                $s = $db->prepare("SELECT COUNT(*) FROM users WHERE id=:id AND role='staff'");
                $s->execute([':id'=>$staff_id]);
                if ($s->fetchColumn()){
                    $stmt = $db->prepare("UPDATE bookings SET staff_id=:staff, status='assigned' WHERE id=:id");
                    $stmt->execute([':staff'=>$staff_id,':id'=>$booking_id]);
                    log_activity($db,$admin_id,'assign_booking','Assigned booking #'.$booking_id.' to staff #'.$staff_id);
                    $_SESSION['flash_message'] = "Booking #$booking_id assigned to staff.";
                } else {
                    $_SESSION['flash_message'] = "Selected staff not found or not a staff member.";
                }
            } else {
                $_SESSION['flash_message'] = "Please select a staff member to assign.";
            }
        }
        header('Location: dashboard.php'); exit;
    }

    // Other handlers: services, supplies, export (kept from previous implementation)
    if(isset($_POST['create_service'])){
        $stmt = $db->prepare("INSERT INTO services (service_name, description, price, duration, category) VALUES (:name,:desc,:price,:duration,:cat)");
        $stmt->execute([
            ':name'=>clean_input($_POST['service_name']),
            ':desc'=>clean_input($_POST['description']),
            ':price'=> (float)$_POST['price'],
            ':duration'=> (int)$_POST['duration'],
            ':cat'=> clean_input($_POST['category'])
        ]);
        log_activity($db,$admin_id,'create_service','Created service: '.clean_input($_POST['service_name']));
        header('Location: dashboard.php'); exit;
    }

    if(isset($_POST['edit_service'])){
        $stmt = $db->prepare("UPDATE services SET service_name=:name, description=:desc, price=:price, duration=:duration, category=:cat WHERE id=:id");
        $stmt->execute([
            ':name'=>clean_input($_POST['service_name']),
            ':desc'=>clean_input($_POST['description']),
            ':price'=> (float)$_POST['price'],
            ':duration'=> (int)$_POST['duration'],
            ':cat'=> clean_input($_POST['category']),
            ':id'=> (int)$_POST['service_id']
        ]);
        log_activity($db,$admin_id,'edit_service','Edited service ID '.(int)$_POST['service_id']);
        header('Location: dashboard.php'); exit;
    }

    if(isset($_POST['delete_service'])){
        $stmt = $db->prepare("DELETE FROM services WHERE id=:id");
        $stmt->execute([':id'=>(int)$_POST['service_id']]);
        log_activity($db,$admin_id,'delete_service','Deleted service ID '.(int)$_POST['service_id']);
        header('Location: dashboard.php'); exit;
    }

    if(isset($_POST['save_supply'])){
        $id = (int)($_POST['supply_id'] ?? 0);
        $name = clean_input($_POST['supply_name']);
        $qty = (int)$_POST['quantity'];
        $unit = clean_input($_POST['unit']);
        if($id>0){
            $stmt = $db->prepare("UPDATE supplies SET name=:name, quantity=:qty, unit=:unit, last_updated=CURRENT_TIMESTAMP WHERE id=:id");
            $stmt->execute([':name'=>$name,':qty'=>$qty,':unit'=>$unit,':id'=>$id]);
            log_activity($db,$admin_id,'update_supply','Updated supply '.$name);
        } else {
            $stmt = $db->prepare("INSERT INTO supplies (name, quantity, unit) VALUES (:name,:qty,:unit)");
            $stmt->execute([':name'=>$name,':qty'=>$qty,':unit'=>$unit]);
            log_activity($db,$admin_id,'create_supply','Created supply '.$name);
        }
        header('Location: dashboard.php'); exit;
    }

    if(isset($_POST['delete_supply'])){
        $stmt = $db->prepare("DELETE FROM supplies WHERE id=:id");
        $stmt->execute([':id'=>(int)$_POST['supply_id']]);
        log_activity($db,$admin_id,'delete_supply','Deleted supply ID '.(int)$_POST['supply_id']);
        header('Location: dashboard.php'); exit;
    }

    if(isset($_POST['export_bookings'])){
        $stmt = $db->query("SELECT b.id, u.name as customer, s.service_name, b.car_model, b.license_plate, b.booking_time, b.status, b.created_at
            FROM bookings b
            LEFT JOIN users u ON b.user_id=u.id
            LEFT JOIN services s ON b.service_id=s.id ORDER BY b.created_at DESC");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="bookings_export_'.date('Ymd').'.csv"');
        $out = fopen('php://output','w');
        fputcsv($out,array_keys($rows[0] ?? []));
        foreach($rows as $r) fputcsv($out,$r);
        fclose($out);
        exit;
    }
}

// --- JSON endpoint for calendar events ---
if(isset($_GET['action']) && $_GET['action']==='events'){
    $stmt = $db->query("SELECT b.id, b.booking_time, b.status, u.name as customer, s.service_name FROM bookings b LEFT JOIN users u ON b.user_id=u.id LEFT JOIN services s ON b.service_id=s.id WHERE b.booking_time IS NOT NULL");
    $events = [];
    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $r){
        $events[] = [
            'id'=>$r['id'],
            'title'=>($r['service_name']? $r['service_name'] . ' - ' : '') . ($r['customer'] ?? 'Customer'),
            'start'=>$r['booking_time'],
            'color'=> ($r['status']=='completed'?'#198754':($r['status']=='rejected'?'#dc3545':'#ffc107'))
        ];
    }
    header('Content-Type: application/json');
    echo json_encode($events);
    exit;
}

// --- Dashboard data ---
$total_users = (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_bookings = (int)$db->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
$pending_bookings = (int)$db->query("SELECT COUNT(*) FROM bookings WHERE status='pending'")->fetchColumn();
$completed_bookings = (int)$db->query("SELECT COUNT(*) FROM bookings WHERE status='completed'")->fetchColumn();
$rejected_bookings = (int)$db->query("SELECT COUNT(*) FROM bookings WHERE status='rejected'")->fetchColumn();
$total_services = (int)$db->query("SELECT COUNT(*) FROM services")->fetchColumn();

$total_revenue = (float)$db->query("SELECT COALESCE(SUM(s.price),0) FROM bookings b LEFT JOIN services s ON b.service_id=s.id WHERE b.status='completed'")->fetchColumn();
$today_bookings = (int)$db->query("SELECT COUNT(*) FROM bookings WHERE DATE(created_at)=DATE('now')")->fetchColumn();
$avg_rating = (float)$db->query("SELECT COALESCE(AVG(rating),0) FROM feedback")->fetchColumn();

// Monthly bookings
$months = [];$monthData=[];
$stmt = $db->query("SELECT strftime('%Y-%m', created_at) as month, COUNT(*) as total FROM bookings GROUP BY month ORDER BY month DESC LIMIT 12");
$rows = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
foreach($rows as $r){ $months[]=$r['month']; $monthData[]=(int)$r['total']; }

// Service counts
$service_counts=[];
$stmt = $db->query("SELECT s.service_name, COUNT(b.id) as cnt FROM services s LEFT JOIN bookings b ON b.service_id=s.id GROUP BY s.id ORDER BY cnt DESC");
foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $r){ $service_counts[$r['service_name']] = (int)$r['cnt']; }

// Staff counts
$staff_counts=[];
$stmt = $db->query("SELECT st.name, COUNT(b.id) as cnt FROM users st LEFT JOIN bookings b ON b.staff_id=st.id WHERE st.role='staff' GROUP BY st.id");
foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $r){ $staff_counts[$r['name'] ?? 'Unassigned'] = (int)$r['cnt']; }

$recent_bookings = $db->query("SELECT b.*, u.name AS customer_name, s.service_name, s.category, st.name AS staff_name FROM bookings b LEFT JOIN users u ON b.user_id=u.id LEFT JOIN services s ON b.service_id=s.id LEFT JOIN users st ON b.staff_id=st.id ORDER BY b.created_at DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);

$all_users = $db->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$all_services = $db->query("SELECT * FROM services ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$feedback_data = $db->query("SELECT f.*, u.name AS customer_name, b.service_id, s.service_name FROM feedback f LEFT JOIN users u ON f.user_id = u.id LEFT JOIN bookings b ON f.booking_id = b.id LEFT JOIN services s ON b.service_id = s.id ORDER BY f.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$all_supplies = $db->query("SELECT * FROM supplies ORDER BY last_updated DESC")->fetchAll(PDO::FETCH_ASSOC);
$activity_logs = $db->query("SELECT al.*, u.name as user_name FROM activity_logs al LEFT JOIN users u ON al.user_id=u.id ORDER BY al.created_at DESC LIMIT 200")->fetchAll(PDO::FETCH_ASSOC);

$top_customers = $db->query("SELECT u.id, u.name, u.email, COUNT(b.id) as total FROM users u LEFT JOIN bookings b ON u.id=b.user_id WHERE u.role='customer' GROUP BY u.id ORDER BY total DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);

function val($v){ return htmlspecialchars($v ?? ''); }
?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Dashboard - Smart Car Wash</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/main.min.css' rel='stylesheet' />
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/main.min.js'></script>
  <style>
    body{font-family:Inter, system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial; background:#f5f7fb}
    .card{border-radius:12px}
    .badge-status.pending{background:#ffc107;color:#000}
    .badge-status.approved{background:#0d6efd}
    .badge-status.assigned{background:#6c757d}
    .badge-status.completed{background:#198754}
    .badge-status.rejected{background:#dc3545}
    .table-responsive{max-height:520px; overflow:auto}
    .insight-card{min-height:160px}
  </style>
</head>
<body>
<?php include('../includes/topbar.php'); ?>

<div class="container-fluid p-4">
  <?php if(!empty($_SESSION['flash_message'])): ?>
    <div class="alert alert-info alert-dismissible fade show" role="alert">
      <?= val($_SESSION['flash_message']); unset($_SESSION['flash_message']); ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="m-0">Admin Dashboard</h2>
    <div class="d-flex gap-2">
      <form method="POST" class="d-inline"><button name="export_bookings" class="btn btn-outline-secondary btn-sm">Export Bookings CSV</button></form>
      <a class="btn btn-primary btn-sm" href="#services-tab" onclick="showTab('services')">Manage Services</a>
    </div>
  </div>

  <!-- KPIs -->
  <div class="row g-3 mb-4">
    <div class="col-sm-6 col-md-3"><div class="card p-3 shadow-sm text-center"><small class="text-muted">Total Users</small><h4 class="mb-0"><?= $total_users ?></h4></div></div>
    <div class="col-sm-6 col-md-3"><div class="card p-3 shadow-sm text-center"><small class="text-muted">Total Bookings</small><h4 class="mb-0"><?= $total_bookings ?></h4></div></div>
    <div class="col-sm-6 col-md-2"><div class="card p-3 shadow-sm text-center"><small class="text-muted">Pending</small><h4 class="mb-0"><?= $pending_bookings ?></h4></div></div>
    <div class="col-sm-6 col-md-2"><div class="card p-3 shadow-sm text-center"><small class="text-muted">Revenue</small><h4 class="mb-0">Kes.<?= number_format($total_revenue,2) ?></h4></div></div>
    <div class="col-sm-12 col-md-2"><div class="card p-3 shadow-sm text-center"><small class="text-muted">Avg Rating</small><h4 class="mb-0"><?= number_format($avg_rating,2) ?>/5</h4></div></div>
  </div>

  <div class="row">
    <div class="col-xl-8">
      <div class="card p-3 mb-3">
        <h6>Monthly Bookings</h6>
        <canvas id="monthlyChart" height="120"></canvas>
      </div>

      <div class="card p-3 mb-3">
        <h6>Bookings per Service</h6>
        <canvas id="servicesChart" height="100"></canvas>
      </div>

      <div class="card p-3 mb-3">
        <h6>Schedule</h6>
        <div id='calendar'></div>
      </div>
    </div>

    <div class="col-xl-4">
      <div class="card p-3 mb-3 insight-card">
        <h6 class="mb-2">Top Customers</h6>
        <ul class="list-group list-group-flush">
          <?php foreach($top_customers as $c): ?>
            <li class="list-group-item d-flex justify-content-between align-items-center">
              <div>
                <strong><?= val($c['name']) ?></strong><br>
                <small class="text-muted"><?= val($c['email']) ?></small>
              </div>
              <span class="badge bg-primary rounded-pill"><?= $c['total'] ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  </div>

  <!-- Tabs -->
  <ul class="nav nav-tabs mt-4" id="adminTabs">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#bookings-tab">Bookings</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#services-tab">Services</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#users-tab">Users</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#feedback-tab">Feedback</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#supplies-tab">Supplies</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#insights-tab">Insights</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#logs-tab">Logs</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#activity-tab">Activity</button></li>
  </ul>

  <div class="tab-content p-3 border border-top-0 bg-white">

    <!-- BOOKINGS TAB -->
    <div class="tab-pane fade show active" id="bookings-tab">
      <div class="d-flex justify-content-between mb-2">
        <input id="bookingSearch" class="form-control search-input" placeholder="Search by customer, plate, service...">
        <div>
          <select id="filterStatus" class="form-select form-select-sm">
            <option value="">All statuses</option>
            <option value="pending">Pending</option>
            <option value="approved">Approved</option>
            <option value="assigned">Assigned</option>
            <option value="completed">Completed</option>
            <option value="rejected">Rejected</option>
          </select>
        </div>
      </div>

      <div class="table-responsive">
        <table class="table table-striped align-middle">
          <thead><tr><th>ID</th><th>Customer</th><th>Service</th><th>Car</th><th>Time</th><th>Status</th><th>Staff</th><th>Actions</th></tr></thead>
          <tbody id="bookingsTbody">
            <?php foreach($recent_bookings as $b): ?>
              <tr>
                <td><?= $b['id'] ?></td>
                <td><?= val($b['customer_name']) ?></td>
                <td><?= val($b['service_name']) ?></td>
                <td><?= val($b['car_model']) ?><br><small><?= val($b['license_plate']) ?></small></td>
                <td><?= val($b['booking_time']) ?></td>
                <td><span class="badge badge-status <?= strtolower($b['status'] ?? 'pending') ?>"><?= val(ucfirst($b['status'] ?? 'pending')) ?></span></td>
                <td><?= val($b['staff_name'] ?? '-') ?></td>
                <td>
                  <div class="d-flex gap-1">
                    <form method="POST" class="m-0">
                      <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                      <button name="booking_action" value="approve" class="btn btn-sm btn-success">Approve</button>
                    </form>

                    <form method="POST" class="m-0">
                      <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                      <button name="booking_action" value="reject" class="btn btn-sm btn-danger">Reject</button>
                    </form>

                    <form method="POST" class="m-0 d-flex align-items-center">
                      <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                      <select name="staff_id" class="form-select form-select-sm" style="width:140px">
                        <option value="">Select staff</option>
                        <?php
                          $staffs = $db->prepare("SELECT id, name FROM users WHERE role='staff' ORDER BY name ASC");
                          $staffs->execute();
                          foreach($staffs->fetchAll(PDO::FETCH_ASSOC) as $s){
                            echo '<option value="'.intval($s['id']).'">'.htmlspecialchars($s['name']).'</option>';
                          }
                        ?>
                      </select>
                      <button name="booking_action" value="assign" class="btn btn-sm btn-primary ms-1">Assign</button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- SERVICES TAB -->
    <div class="tab-pane fade" id="services-tab">
      <form method="POST" class="row g-2 mb-3">
        <div class="col-md-3"><input type="text" name="service_name" class="form-control" placeholder="Service Name" required></div>
        <div class="col-md-4"><input type="text" name="description" class="form-control" placeholder="Description" required></div>
        <div class="col-md-2"><input type="number" step="0.01" name="price" class="form-control" placeholder="Price" required></div>
        <div class="col-md-1"><input type="number" name="duration" class="form-control" placeholder="Min" required></div>
        <div class="col-md-2"><select name="category" class="form-select" required>
          <option value="Exterior">Exterior</option>
          <option value="Interior">Interior</option>
          <option value="Full">Full</option>
        </select></div>
        <div class="col-12"><button name="create_service" class="btn btn-success">Add Service</button></div>
      </form>

      <div class="table-responsive">
        <table class="table table-striped">
          <thead><tr><th>ID</th><th>Name</th><th>Description</th><th>Price</th><th>Duration</th><th>Category</th><th>Actions</th></tr></thead>
          <tbody>
            <?php foreach($all_services as $s): ?>
              <tr>
                <td><?= $s['id'] ?></td>
                <td><?= val($s['service_name']) ?></td>
                <td><?= val($s['description']) ?></td>
                <td><?= number_format($s['price'],2) ?></td>
                <td><?= val($s['duration']) ?> min</td>
                <td><?= val($s['category'] ?? 'General') ?></td>
                <td>
                  <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editServiceModal<?= $s['id'] ?>">Edit</button>
                  <form method="POST" class="d-inline" onsubmit="return confirm('Delete service?');">
                    <input type="hidden" name="service_id" value="<?= $s['id'] ?>">
                    <button name="delete_service" class="btn btn-sm btn-danger">Delete</button>
                  </form>

                  <!-- Edit Modal -->
                  <div class="modal fade" id="editServiceModal<?= $s['id'] ?>" tabindex="-1">
                    <div class="modal-dialog">
                      <div class="modal-content p-3">
                        <div class="modal-header"><h5>Edit Service #<?= $s['id'] ?></h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
                        <div class="modal-body">
                          <form method="POST">
                            <input type="hidden" name="service_id" value="<?= $s['id'] ?>">
                            <div class="mb-2"><input type="text" name="service_name" class="form-control" value="<?= val($s['service_name']) ?>" required></div>
                            <div class="mb-2"><input type="text" name="description" class="form-control" value="<?= val($s['description']) ?>"></div>
                            <div class="row g-2">
                              <div class="col"><input type="number" step="0.01" name="price" class="form-control" value="<?= val($s['price']) ?>"></div>
                              <div class="col"><input type="number" name="duration" class="form-control" value="<?= val($s['duration']) ?>"></div>
                              <div class="col"><input type="text" name="category" class="form-control" value="<?= val($s['category']) ?>"></div>
                            </div>
                            <div class="mt-2 text-end"><button class="btn btn-primary" name="edit_service">Save</button></div>
                          </form>
                        </div>
                      </div>
                    </div>
                  </div>

                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

<!-- USERS TAB -->
<div class="tab-pane fade" id="users-tab">
  <!-- Add User Form -->
  <form method="POST" class="row g-2 mb-3 align-items-end">
    <div class="col-md-3">
      <input type="text" name="new_name" class="form-control" placeholder="Full Name" required>
    </div>
    <div class="col-md-3">
      <input type="email" name="new_email" class="form-control" placeholder="Email" required>
    </div>
    <div class="col-md-2">
      <input type="password" name="new_password" class="form-control" placeholder="Password" required>
    </div>
    <div class="col-md-2">
      <select name="new_role" class="form-select" required>
        <option value="customer">Customer</option>
        <option value="staff">Staff</option>
        <option value="admin">Admin</option>
      </select>
    </div>
    <div class="col-md-2">
      <button name="create_user" class="btn btn-success w-100">Add User</button>
    </div>
  </form>

  <!-- Filter + Search -->
  <form method="GET" class="row g-2 mb-3">
    <div class="col-md-3">
      <select name="filter_role" class="form-select">
        <option value="">All Roles</option>
        <option value="customer" <?= (isset($_GET['filter_role']) && $_GET['filter_role']=='customer')?'selected':'' ?>>Customer</option>
        <option value="staff" <?= (isset($_GET['filter_role']) && $_GET['filter_role']=='staff')?'selected':'' ?>>Staff</option>
        <option value="admin" <?= (isset($_GET['filter_role']) && $_GET['filter_role']=='admin')?'selected':'' ?>>Admin</option>
      </select>
    </div>
    <div class="col-md-4">
      <input type="text" name="search_user" class="form-control" placeholder="Search by name or email" value="<?= htmlspecialchars($_GET['search_user'] ?? '') ?>">
    </div>
    <div class="col-md-2">
      <button class="btn btn-primary w-100">Filter</button>
    </div>
    <div class="col-md-2">
      <a href="?tab=users-tab" class="btn btn-secondary w-100">Reset</a>
    </div>
  </form>

  <?php
    // --- Fetch users dynamically based on filters ---
    $where = [];
    $params = [];

    if (!empty($_GET['filter_role'])) {
      $where[] = "role = :role";
      $params[':role'] = $_GET['filter_role'];
    }

    if (!empty($_GET['search_user'])) {
      $where[] = "(name LIKE :search OR email LIKE :search)";
      $params[':search'] = "%" . $_GET['search_user'] . "%";
    }

    $sql = "SELECT * FROM users";
    if ($where) $sql .= " WHERE " . implode(" AND ", $where);
    $sql .= " ORDER BY created_at DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $filtered_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- Delete user ---
    if (isset($_POST['delete_user'])) {
      $id = intval($_POST['delete_id']);
      if ($id != $_SESSION['user']['id']) { // prevent self-delete
        $db->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
        echo "<script>alert('User deleted successfully');window.location.href='?tab=users-tab';</script>";
      } else {
        echo "<script>alert('You cannot delete your own account.');</script>";
      }
    }
  ?>

  <!-- Users Table -->
  <div class="table-responsive">
    <table class="table table-striped align-middle">
      <thead class="table-dark">
        <tr>
          <th>ID</th>
          <th>Name</th>
          <th>Email</th>
          <th>Role</th>
          <th>Joined</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (count($filtered_users) > 0): ?>
          <?php foreach($filtered_users as $u): ?>
            <tr>
              <td><?= $u['id'] ?></td>
              <td><?= htmlspecialchars($u['name']) ?></td>
              <td><?= htmlspecialchars($u['email']) ?></td>
              <td>
                <span class="badge 
                  <?= $u['role']=='admin'?'bg-danger':
                      ($u['role']=='staff'?'bg-warning text-dark':'bg-info text-dark') ?>">
                  <?= ucfirst($u['role']) ?>
                </span>
              </td>
              <td><?= htmlspecialchars($u['created_at']) ?></td>
              <td>
                <form method="POST" onsubmit="return confirm('Delete this user?');" class="d-inline">
                  <input type="hidden" name="delete_id" value="<?= $u['id'] ?>">
                  <button name="delete_user" class="btn btn-sm btn-danger">Delete</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="6" class="text-center text-muted py-3">No users found</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

    <!-- FEEDBACK TAB -->
    <div class="tab-pane fade" id="feedback-tab">
      <div class="table-responsive">
        <table class="table table-striped">
          <thead><tr><th>ID</th><th>Booking</th><th>Customer</th><th>Service</th><th>Rating</th><th>Comment</th><th>When</th></tr></thead>
          <tbody>
            <?php foreach($feedback_data as $f): ?>
              <tr>
                <td><?= $f['id'] ?></td>
                <td><?= $f['booking_id'] ?></td>
                <td><?= val($f['customer_name']) ?></td>
                <td><?= val($f['service_name'] ?? 'Unknown') ?></td>
                <td><?= $f['rating'] ?>/5</td>
                <td><?= nl2br(val($f['comment'])) ?></td>
                <td><?= $f['created_at'] ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- SUPPLIES TAB -->
    <div class="tab-pane fade" id="supplies-tab">
      <form method="POST" class="row g-2 mb-3">
        <input type="hidden" name="supply_id" id="supply_id">
        <div class="col-md-4"><input type="text" name="supply_name" id="supply_name" class="form-control" placeholder="Supply name" required></div>
        <div class="col-md-3"><input type="number" name="quantity" id="quantity" class="form-control" placeholder="Quantity" required></div>
        <div class="col-md-3"><input type="text" name="unit" id="unit" class="form-control" placeholder="Unit e.g. liters, pcs"></div>
        <div class="col-md-2"><button name="save_supply" class="btn btn-success">Save Supply</button></div>
      </form>

      <div class="table-responsive">
        <table class="table table-striped">
          <thead><tr><th>ID</th><th>Name</th><th>Qty</th><th>Unit</th><th>Last Updated</th><th>Actions</th></tr></thead>
          <tbody>
            <?php foreach($all_supplies as $s): ?>
              <tr>
                <td><?= $s['id'] ?></td>
                <td><?= val($s['name']) ?></td>
                <td><?= $s['quantity'] ?></td>
                <td><?= val($s['unit']) ?></td>
                <td><?= $s['last_updated'] ?></td>
                <td>
                  <button class="btn btn-sm btn-outline-primary" onclick="editSupply(<?= $s['id'] ?>,'<?= val($s['name']) ?>',<?= $s['quantity'] ?>,'<?= val($s['unit']) ?>')">Edit</button>
                  <form method="POST" class="d-inline" onsubmit="return confirm('Delete supply?');">
                    <input type="hidden" name="supply_id" value="<?= $s['id'] ?>">
                    <button name="delete_supply" class="btn btn-sm btn-danger">Delete</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

 <!-- INSIGHTS TAB -->
<div class="tab-pane fade show active" id="insights-tab">
  <div class="row g-3">
    <?php
      // --- Fetch Service Popularity Data ---
      $serviceQuery = $db->query("
        SELECT s.service_name, COUNT(b.id) AS total
        FROM services s
        LEFT JOIN bookings b ON b.service_id = s.id
        GROUP BY s.service_name
      ");
      $serviceNames = [];
      $serviceCounts = [];
      foreach ($serviceQuery as $row) {
          $serviceNames[] = $row['service_name'];
          $serviceCounts[] = $row['total'];
      }

      // --- Fetch Staff Workload Data ---
      $staffQuery = $db->query("
        SELECT u.name, COUNT(b.id) AS total
        FROM users u
        LEFT JOIN bookings b ON b.staff_id = u.id
        WHERE u.role = 'staff'
        GROUP BY u.name
      ");
      $staffNames = [];
      $staffCounts = [];
      foreach ($staffQuery as $row) {
          $staffNames[] = $row['name'];
          $staffCounts[] = $row['total'];
      }
    ?>

    <!-- Service Popularity -->
    <div class="col-lg-6 col-md-12">
      <div class="card shadow-sm border-0 p-4 h-100">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h5 class="fw-semibold mb-0">Service Popularity</h5>
          <span class="badge bg-info text-dark">Analytics</span>
        </div>
        <p class="text-muted small mb-3">
          Top-performing car wash services by number of bookings.
        </p>
        <canvas id="servicePopularityLarge" height="180"></canvas>
      </div>
    </div>

    <!-- Staff Workload -->
    <div class="col-lg-6 col-md-12">
      <div class="card shadow-sm border-0 p-4 h-100">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h5 class="fw-semibold mb-0">Staff Workload</h5>
          <span class="badge bg-warning text-dark">Performance</span>
        </div>
        <p class="text-muted small mb-3">
          Distribution of assigned bookings among staff members.
        </p>
        <canvas id="staffChartLarge" height="180"></canvas>
      </div>
    </div>
  </div>

  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script>
  document.addEventListener("DOMContentLoaded", () => {
    // --- Service Popularity Chart ---
    const serviceCtx = document.getElementById("servicePopularityLarge").getContext("2d");
    new Chart(serviceCtx, {
      type: "bar",
      data: {
        labels: <?php echo json_encode($serviceNames); ?>,
        datasets: [{
          label: "Bookings",
          data: <?php echo json_encode($serviceCounts); ?>,
          backgroundColor: "#17a2b8",
          borderRadius: 6
        }]
      },
      options: {
        responsive: true,
        plugins: {
          legend: { display: false },
          title: {
            display: true,
            text: "Most Booked Services",
            font: { size: 16 }
          }
        },
        scales: { y: { beginAtZero: true } }
      }
    });

    // --- Staff Workload Chart ---
    const staffCtx = document.getElementById("staffChartLarge").getContext("2d");
    new Chart(staffCtx, {
      type: "doughnut",
      data: {
        labels: <?php echo json_encode($staffNames); ?>,
        datasets: [{
          data: <?php echo json_encode($staffCounts); ?>,
          backgroundColor: ["#007bff", "#ffc107", "#28a745", "#dc3545", "#6f42c1"],
        }]
      },
      options: {
        responsive: true,
        plugins: {
          legend: { position: "bottom" },
          title: {
            display: true,
            text: "Staff Booking Distribution",
            font: { size: 16 }
          }
        }
      }
    });
  });
  </script>
</div>


    <!-- LOGS TAB -->
    <div class="tab-pane fade" id="logs-tab">
      <div class="table-responsive">
        <table class="table table-striped">
          <thead><tr><th>When</th><th>User</th><th>Action</th><th>Details</th></tr></thead>
          <tbody>
            <?php foreach($activity_logs as $l): ?>
              <tr>
                <td><?= $l['created_at'] ?></td>
                <td><?= val($l['user_name']) ?></td>
                <td><?= val($l['action']) ?></td>
                <td><?= val($l['details']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ACTIVITY TAB -->
    <div class="tab-pane fade" id="activity-tab">
      <div class="card p-3">
        <h6>Activity Feed</h6>
        <div style="max-height:500px; overflow:auto">
          <?php foreach($activity_logs as $log): ?>
            <div class="border-bottom py-2">
              <div class="small-muted"><?= $log['created_at'] ?> · <?= val($log['user_name'] ?? 'System') ?></div>
              <div><?= val($log['action']) ?></div>
              <div class="small-muted"><?= val($log['details']) ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

  </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Charts
const monthlyCtx = document.getElementById('monthlyChart');
new Chart(monthlyCtx, {type:'line', data:{labels: <?= json_encode($months) ?>, datasets:[{label:'Bookings', data: <?= json_encode($monthData) ?>, tension:0.3, fill:false}]}});

new Chart(document.getElementById('servicesChart'), {type:'bar', data:{ labels: <?= json_encode(array_keys($service_counts)) ?>, datasets:[{label:'Bookings', data: <?= json_encode(array_values($service_counts)) ?>}]}, options:{scales:{y:{beginAtZero:true}}}});

new Chart(document.getElementById('servicePopularity'), {type:'pie', data:{labels:<?= json_encode(array_keys($service_counts)) ?>, datasets:[{data:<?= json_encode(array_values($service_counts)) ?>}]}});
new Chart(document.getElementById('staffChart'), {type:'bar', data:{ labels: <?= json_encode(array_keys($staff_counts)) ?>, datasets:[{data: <?= json_encode(array_values($staff_counts)) ?>}]}, options:{scales:{y:{beginAtZero:true}}}});

new Chart(document.getElementById('servicePopularityLarge'), {type:'doughnut', data:{labels:<?= json_encode(array_keys($service_counts)) ?>, datasets:[{data:<?= json_encode(array_values($service_counts)) ?>}]}});
new Chart(document.getElementById('staffChartLarge'), {type:'bar', data:{labels: <?= json_encode(array_keys($staff_counts)) ?>, datasets:[{data: <?= json_encode(array_values($staff_counts)) ?>}]}, options:{scales:{y:{beginAtZero:true}}}});

// Calendar
document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');
    if (calendarEl) {
      var calendar = new FullCalendar.Calendar(calendarEl, {
          initialView: 'dayGridMonth',
          headerToolbar: { left:'prev,next today', center:'title', right:'dayGridMonth,timeGridWeek,timeGridDay' },
          events: 'dashboard.php?action=events'
      });
      calendar.render();
    }
});

// Search and filter
const bookingSearch = document.getElementById('bookingSearch');
if(bookingSearch) bookingSearch.addEventListener('input', filterBookings);
const filterStatus = document.getElementById('filterStatus');
if(filterStatus) filterStatus.addEventListener('change', filterBookings);
function filterBookings(){
    const q = (bookingSearch.value||'').toLowerCase();
    const s = (filterStatus.value||'').toLowerCase();
    document.querySelectorAll('#bookingsTbody tr').forEach(r=>{
        const text = r.innerText.toLowerCase();
        const status = r.cells[5].innerText.trim().toLowerCase();
        r.style.display = ((q==''||text.includes(q)) && (s==''||status===s)) ? '' : 'none';
    });
}

function showTab(id){
    const target = document.querySelector("[data-bs-target='#"+id+"-tab']") || document.querySelector("[data-bs-target='#"+id+"']");
    if(target) new bootstrap.Tab(target).show();
}

function editSupply(id,name,qty,unit){
    document.getElementById('supply_id').value = id;
    document.getElementById('supply_name').value = name;
    document.getElementById('quantity').value = qty;
    document.getElementById('unit').value = unit;
    showTab('supplies');
}
</script>
</body>
</html>