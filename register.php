<?php
require_once __DIR__ . '/includes/db.php';
$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    try {
        $stmt = $db->prepare("INSERT INTO users (name, email, password) VALUES (:name, :email, :password)");
        $stmt->execute([':name' => $name, ':email' => $email, ':password' => $password]);
        $message = 'Registration successful! <a href="index.php">Login</a>';
    } catch (PDOException $e) {
        $message = 'Email already exists!';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register - Smart Car Wash</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card shadow p-4">
                <h3 class="text-center mb-4">Create Account</h3>
                <?php if ($message): ?><div class="alert alert-info"><?= $message ?></div><?php endif; ?>
                <form method="POST">
                    <input name="name" class="form-control mb-2" placeholder="Full Name" required>
                    <input name="email" type="email" class="form-control mb-2" placeholder="Email" required>
                    <input name="password" type="password" class="form-control mb-3" placeholder="Password" required>
                    <button class="btn btn-success w-100">Register</button>
                </form>
                <p class="text-center mt-3">
                    Have an account? <a href="index.php">Login</a>
                </p>
            </div>
        </div>
    </div>
</div>
</body>
</html>
