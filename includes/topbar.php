<?php
if (session_status() == PHP_SESSION_NONE) session_start();

// Logout handling
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header('Location: ../index.php');
    exit;
}

// Current user info
$user = $_SESSION['user'] ?? null;
?>
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
  <div class="container-fluid">
    <a class="navbar-brand" href="#">Smart Car Wash</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navMenu">
      <ul class="navbar-nav ms-auto">
        <?php if($user): ?>
          <li class="nav-item">
            <span class="nav-link">Hello, <?= htmlspecialchars($user['name']) ?></span>
          </li>
          <?php if ($user['role'] === 'admin'): ?>
            <li class="nav-item">
              <a class="nav-link" href="../admin/dashboard.php">Dashboard</a>
            </li>
          <?php else: ?>
            <li class="nav-item">
              <a class="nav-link" href="../customer/dashboard.php">Dashboard</a>
            </li>
          <?php endif; ?>
          <li class="nav-item">
            <a class="nav-link" href="?logout=1">Logout</a>
          </li>
        <?php else: ?>
          <li class="nav-item"><a class="nav-link" href="../index.php">Login</a></li>
          <li class="nav-item"><a class="nav-link" href="../register.php">Register</a></li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>

