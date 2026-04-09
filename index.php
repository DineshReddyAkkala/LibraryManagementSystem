<?php
session_start();
$isLoggedIn = !empty($_SESSION['user_id']);
$isAdmin = ($_SESSION['role_name'] ?? '') === 'admin';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>UniLibrary - University Library Management System</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
  <nav class="landing-nav">
    <div class="nav-brand">
      <div class="nav-logo"><i class="fas fa-book-open"></i></div>
      <span>UniLibrary</span>
    </div>
    <div class="nav-links">
      <a href="pages/books/search-books.php">Catalogue</a>
      <?php if ($isLoggedIn) { ?>
        <?php if ($isAdmin) { ?>
          <a href="pages/admin/dashboard.php">Admin Panel</a>
        <?php } else { ?>
          <a href="pages/dashboard/student-dashboard.php">Dashboard</a>
        <?php } ?>
        <a href="pages/auth/logout.php">Sign Out</a>
      <?php } else { ?>
        <a href="pages/auth/login.php">Sign In</a>
        <a href="pages/auth/register.php" class="btn-landing">Register</a>
      <?php } ?>
    </div>
  </nav>

  <section class="hero">
    <h1>Your University Library,<br>Reimagined</h1>
    <p>Search, borrow, reserve, and manage your reading from a single modern platform built for students and staff.</p>
    <div class="hero-actions">
      <a href="pages/books/search-books.php" class="btn-hero btn-hero-primary"><i class="fas fa-search"></i> Browse Catalogue</a>
      <?php if (!$isLoggedIn) { ?>
        <a href="pages/auth/register.php" class="btn-hero btn-hero-outline"><i class="fas fa-user-plus"></i> Create Account</a>
      <?php } else { ?>
        <a href="<?php echo $isAdmin ? 'pages/admin/dashboard.php' : 'pages/dashboard/student-dashboard.php'; ?>" class="btn-hero btn-hero-outline"><i class="fas fa-th-large"></i> Go to Dashboard</a>
      <?php } ?>
    </div>
  </section>

  <section class="features">
    <h2>Everything You Need</h2>
    <p class="features-sub">A complete library management system designed for modern universities</p>
    <div class="features-grid">
      <div class="card feature-card">
        <div class="feature-icon kpi-icon blue"><i class="fas fa-search"></i></div>
        <h3>Smart Search</h3>
        <p>Search by title, author, or category. Filter results and find exactly what you need across the entire catalogue.</p>
      </div>
      <div class="card feature-card">
        <div class="feature-icon kpi-icon green"><i class="fas fa-book"></i></div>
        <h3>Borrow &amp; Reserve</h3>
        <p>Borrow available books instantly or reserve checked-out titles. Track due dates and manage returns online.</p>
      </div>
      <div class="card feature-card">
        <div class="feature-icon kpi-icon amber"><i class="fas fa-lightbulb"></i></div>
        <h3>Personalised Recommendations</h3>
        <p>Get book suggestions based on your search history, filter preferences, and borrowing patterns.</p>
      </div>
      <div class="card feature-card">
        <div class="feature-icon kpi-icon red"><i class="fas fa-bell"></i></div>
        <h3>Notifications &amp; Alerts</h3>
        <p>Receive alerts for due dates, reservation updates, and overdue reminders to stay on track.</p>
      </div>
      <div class="card feature-card">
        <div class="feature-icon kpi-icon cyan"><i class="fas fa-user-shield"></i></div>
        <h3>Admin Control Panel</h3>
        <p>Administrators can manage inventory, users, circulation, and monitor system activity from one dashboard.</p>
      </div>
      <div class="card feature-card">
        <div class="feature-icon kpi-icon blue"><i class="fas fa-receipt"></i></div>
        <h3>Fine Tracking</h3>
        <p>Transparent fine management for overdue returns. Students can view and track outstanding balances.</p>
      </div>
    </div>
  </section>

  <footer class="landing-footer">
    &copy; <?php echo date('Y'); ?> University Library Management System. All rights reserved.
  </footer>
  <script src="assets/js/app.js"></script>
</body>
</html>