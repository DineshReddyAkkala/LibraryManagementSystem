<?php
require_once dirname(__DIR__, 2) . '/config/bootstrap.php';
require_admin();
$basePath = '../..';
$currentPage = 'dashboard';
$pageTitle = 'Admin Dashboard';

$books = (int)$pdo->query('SELECT COUNT(*) FROM books')->fetchColumn();
$studentsStmt = $pdo->prepare('SELECT COUNT(*) FROM users u INNER JOIN roles r ON r.role_id = u.role_id WHERE r.role_name = ?');
$studentsStmt->execute(['student']);
$students = (int)$studentsStmt->fetchColumn();
$activeBorrowings = (int)$pdo->query('SELECT COUNT(*) FROM borrowings WHERE return_date IS NULL')->fetchColumn();
$activeReservations = (int)$pdo->query("SELECT COUNT(*) FROM reservations WHERE status = 'active'")->fetchColumn();
$overdue = (int)$pdo->query('SELECT COUNT(*) FROM borrowings WHERE return_date IS NULL AND due_date < CURDATE()')->fetchColumn();
$returnRequests = (int)$pdo->query("SELECT COUNT(*) FROM borrowings WHERE return_date IS NULL AND status = 'return_requested'")->fetchColumn();
$unpaidFines = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM fines WHERE paid_status = 'unpaid'")->fetchColumn();

$recentStmt = $pdo->query('SELECT n.message, n.created_at, u.full_name FROM notifications n INNER JOIN users u ON u.user_id = n.user_id ORDER BY n.created_at DESC LIMIT 8');
$recentNotifications = $recentStmt->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?php echo $pageTitle; ?> - UniLibrary</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="<?php echo $basePath; ?>/assets/css/style.css">
</head>
<body class="app-layout">
  <?php include dirname(__DIR__, 2) . '/partials/sidebar-admin.php'; ?>
  <div class="main-content">
    <?php include dirname(__DIR__, 2) . '/partials/topbar.php'; ?>
    <div class="content-area">

      <div class="kpi-grid mb-24">
        <div class="card kpi-card">
          <div class="kpi-icon blue"><i class="fas fa-book"></i></div>
          <div class="kpi-data">
            <div class="kpi-label">Total Books</div>
            <div class="kpi-value"><?php echo $books; ?></div>
          </div>
        </div>
        <div class="card kpi-card">
          <div class="kpi-icon green"><i class="fas fa-users"></i></div>
          <div class="kpi-data">
            <div class="kpi-label">Students</div>
            <div class="kpi-value"><?php echo $students; ?></div>
          </div>
        </div>
        <div class="card kpi-card">
          <div class="kpi-icon amber"><i class="fas fa-hand-holding"></i></div>
          <div class="kpi-data">
            <div class="kpi-label">Active Borrowings</div>
            <div class="kpi-value"><?php echo $activeBorrowings; ?></div>
          </div>
        </div>
        <div class="card kpi-card">
          <div class="kpi-icon cyan"><i class="fas fa-bookmark"></i></div>
          <div class="kpi-data">
            <div class="kpi-label">Reservations</div>
            <div class="kpi-value"><?php echo $activeReservations; ?></div>
          </div>
        </div>
        <div class="card kpi-card">
          <div class="kpi-icon red"><i class="fas fa-exclamation-triangle"></i></div>
          <div class="kpi-data">
            <div class="kpi-label">Overdue</div>
            <div class="kpi-value"><?php echo $overdue; ?></div>
          </div>
        </div>
        <div class="card kpi-card">
          <div class="kpi-icon <?php echo $returnRequests > 0 ? 'amber' : 'green'; ?>"><i class="fas fa-undo"></i></div>
          <div class="kpi-data">
            <div class="kpi-label">Return Requests</div>
            <div class="kpi-value"><?php echo $returnRequests; ?></div>
          </div>
        </div>
        <div class="card kpi-card">
          <div class="kpi-icon red"><i class="fas fa-sterling-sign"></i></div>
          <div class="kpi-data">
            <div class="kpi-label">Unpaid Fines</div>
            <div class="kpi-value">&pound;<?php echo number_format($unpaidFines, 2); ?></div>
          </div>
        </div>
      </div>

      <div class="tile-grid mb-24">
        <div class="tile" onclick="goTo('manage-books.php')">
          <div class="tile-icon blue"><i class="fas fa-book-medical"></i></div>
          <div class="tile-body">
            <div class="tile-title">Manage Books</div>
            <div class="tile-sub">Add, edit, delete & adjust inventory</div>
          </div>
        </div>
        <div class="tile" onclick="goTo('manage-users.php')">
          <div class="tile-icon green"><i class="fas fa-user-cog"></i></div>
          <div class="tile-body">
            <div class="tile-title">Manage Users</div>
            <div class="tile-sub">Edit, activate & delete accounts</div>
          </div>
        </div>
        <div class="tile" onclick="goTo('circulation.php')">
          <div class="tile-icon amber"><i class="fas fa-exchange-alt"></i></div>
          <div class="tile-body">
            <div class="tile-title">Circulation</div>
            <div class="tile-sub">Returns & reservation management</div>
          </div>
        </div>
        <div class="tile" onclick="goTo('manage-fines.php')">
          <div class="tile-icon red"><i class="fas fa-receipt"></i></div>
          <div class="tile-body">
            <div class="tile-title">Manage Fines</div>
            <div class="tile-sub">Issue, track & collect fines</div>
          </div>
        </div>
        <div class="tile" onclick="goTo('../books/search-books.php')">
          <div class="tile-icon cyan"><i class="fas fa-search"></i></div>
          <div class="tile-body">
            <div class="tile-title">Search Catalogue</div>
            <div class="tile-sub">Browse & find books</div>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <div>
            <h2><i class="fas fa-stream" style="color:var(--primary);margin-right:8px"></i>Recent Activity</h2>
            <p>Latest system notifications across all users</p>
          </div>
        </div>
        <?php if (!$recentNotifications) { ?>
          <div class="empty-state"><i class="fas fa-inbox"></i><p>No recent activity.</p></div>
        <?php } else { ?>
          <?php foreach ($recentNotifications as $item) { ?>
            <div class="notification-item">
              <div>
                <div class="notif-type"><i class="fas fa-user" style="margin-right:6px;color:var(--primary)"></i><?php echo htmlspecialchars($item['full_name']); ?></div>
                <div class="notif-message"><?php echo htmlspecialchars($item['message']); ?></div>
              </div>
              <div class="notif-time"><?php echo htmlspecialchars($item['created_at']); ?></div>
            </div>
          <?php } ?>
        <?php } ?>
      </div>

    </div>
    <?php include dirname(__DIR__, 2) . '/partials/footer.php'; ?>
  </div>
  <script src="<?php echo $basePath; ?>/assets/js/app.js"></script>
</body>
</html>