<?php
session_start();
require_once __DIR__.'/../includes/db.php';
require_once __DIR__.'/../includes/functions.php';

if(!is_logged_in() || $_SESSION['user']['role'] !== 'customer'){
    header('Location: ../index.php'); exit;
}

$user = $_SESSION['user'];
$user_id = $user['id'];

// --- Handle profile update ---
if(isset($_POST['update_profile'])){
    $name = clean_input($_POST['name']);
    $email = clean_input($_POST['email']);
    $password = !empty($_POST['password']) ? password_hash($_POST['password'], PASSWORD_DEFAULT) : null;

    if($password){
        $stmt = $db->prepare("UPDATE users SET name=:name,email=:email,password=:password WHERE id=:id");
        $stmt->execute([':name'=>$name, ':email'=>$email, ':password'=>$password, ':id'=>$user_id]);
    } else {
        $stmt = $db->prepare("UPDATE users SET name=:name,email=:email WHERE id=:id");
        $stmt->execute([':name'=>$name, ':email'=>$email, ':id'=>$user_id]);
    }
    $_SESSION['user']['name'] = $name;
    $_SESSION['user']['email'] = $email;
    header('Location: dashboard.php'); exit;
}

// --- Handle cancel booking ---
if(isset($_GET['cancel']) && is_numeric($_GET['cancel'])) {
    $stmt = $db->prepare("UPDATE bookings SET status='cancelled' WHERE id=:id AND user_id=:uid");
    $stmt->execute([':id'=>$_GET['cancel'], ':uid'=>$user_id]);
    header('Location: dashboard.php'); exit;
}

// --- Handle feedback submission ---
if(isset($_POST['submit_feedback'])){
    $booking_id = (int)$_POST['booking_id'];
    $rating = (int)$_POST['rating'];
    $comment = clean_input($_POST['comment']);
    $stmt = $db->prepare("INSERT INTO feedback (booking_id, user_id, rating, comment) VALUES (:bid, :uid, :rating, :comment)");
    $stmt->execute([':bid'=>$booking_id, ':uid'=>$user_id, ':rating'=>$rating, ':comment'=>$comment]);
    header('Location: dashboard.php'); exit;
}

// --- Fetch bookings with assigned staff ---
$stmt = $db->prepare("
    SELECT b.*, s.service_name, st.name AS staff_name
    FROM bookings b
    LEFT JOIN services s ON b.service_id = s.id
    LEFT JOIN users st ON b.staff_id = st.id
    WHERE b.user_id=:uid
    ORDER BY b.created_at DESC
");
$stmt->execute([':uid'=>$user_id]);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Fetch services for new bookings ---
$services = $db->query("SELECT * FROM services")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Customer Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<?php include('../includes/topbar.php'); ?>

<div class="container py-4">
    <h2 class="text-center mb-4">Welcome, <?= htmlspecialchars($user['name']) ?></h2>

    <div class="row g-4">
        <!-- Profile & New Booking -->
        <div class="col-lg-4">
            <!-- Profile Card -->
            <div class="card shadow p-3 mb-4">
                <h4 class="card-title mb-3">Profile</h4>
                <form method="POST">
                    <div class="mb-2">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($user['name']) ?>" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">New Password</label>
                        <input type="password" name="password" class="form-control" placeholder="Leave blank to keep current">
                    </div>
                    <div class="d-grid"><button name="update_profile" class="btn btn-primary">Update Profile</button></div>
                </form>
            </div>

            <!-- Book New Service -->
            <div class="card shadow p-3">
                <h4 class="card-title mb-3">Book New Wash</h4>
                <form method="POST" action="book.php">
                    <div class="mb-2">
                        <label class="form-label">Car Model</label>
                        <input type="text" name="car_model" class="form-control" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">License Plate</label>
                        <input type="text" name="license_plate" class="form-control" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Service</label>
                        <select name="service_id" class="form-select" required>
                            <?php foreach($services as $s): ?>
                                <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['service_name']) ?> - $<?= $s['price'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Booking Time</label>
                        <input type="datetime-local" name="booking_time" class="form-control" required>
                    </div>
                    <div class="d-grid"><button class="btn btn-success">Book Now</button></div>
                </form>
            </div>
        </div>

        <!-- Booking History -->
        <div class="col-lg-8">
            <h4>Your Bookings</h4>
            <div class="table-responsive">
                <table class="table table-striped table-bordered align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Service</th>
                            <th>Car</th>
                            <th>Status</th>
                            <th>Booking Time</th>
                            <th>Staff</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($bookings): foreach($bookings as $b): ?>
                        <tr>
                            <td><?= $b['id'] ?></td>
                            <td><?= htmlspecialchars($b['service_name']) ?></td>
                            <td><?= htmlspecialchars($b['car_model']) ?> (<?= htmlspecialchars($b['license_plate']) ?>)</td>
                            <td><?= htmlspecialchars($b['status']) ?></td>
                            <td><?= $b['booking_time'] ?></td>
                            <td><?= htmlspecialchars($b['staff_name'] ?? 'Not assigned') ?></td>
                            <td>
                                <?php if($b['status']=='pending' || $b['status']=='approved'): ?>
                                    <a href="?cancel=<?= $b['id'] ?>" class="btn btn-sm btn-danger mb-1" onclick="return confirm('Cancel this booking?')">Cancel</a>
                                <?php endif; ?>
                                <?php if($b['status']=='completed'): ?>
                                    <button class="btn btn-sm btn-info mb-1" data-bs-toggle="modal" data-bs-target="#feedbackModal<?= $b['id'] ?>">Feedback</button>

                                    <!-- Feedback Modal -->
                                    <div class="modal fade" id="feedbackModal<?= $b['id'] ?>" tabindex="-1">
                                      <div class="modal-dialog">
                                        <div class="modal-content">
                                          <form method="POST">
                                            <div class="modal-header">
                                              <h5 class="modal-title">Feedback for Booking #<?= $b['id'] ?></h5>
                                              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                                                <div class="mb-2">
                                                    <label class="form-label">Rating (1-5)</label>
                                                    <input type="number" name="rating" min="1" max="5" class="form-control" required>
                                                </div>
                                                <div class="mb-2">
                                                    <label class="form-label">Comment</label>
                                                    <textarea name="comment" class="form-control" rows="3"></textarea>
                                                </div>
                                                <div class="mb-1"><strong>Staff:</strong> <?= htmlspecialchars($b['staff_name'] ?? 'Not assigned') ?></div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="submit" name="submit_feedback" class="btn btn-primary">Submit</button>
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                            </div>
                                          </form>
                                        </div>
                                      </div>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr><td colspan="7" class="text-center">No bookings yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
