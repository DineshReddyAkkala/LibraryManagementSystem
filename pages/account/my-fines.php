<?php
require_once dirname(__DIR__, 2) . '/config/bootstrap.php';
require_login();
if (is_admin()) { header('Location: ../admin/dashboard.php'); exit; }
$name = current_user_name();
$userId = (int)$_SESSION['user_id'];
$basePath = '../..';
$currentPage = 'fines';
$pageTitle = 'My Fines';

$fineStmt = $pdo->prepare('SELECT f.fine_id, f.amount, f.paid_status, b.title, br.due_date, br.return_date FROM fines f INNER JOIN borrowings br ON br.borrowing_id = f.borrowing_id INNER JOIN books b ON b.book_id = br.book_id WHERE br.user_id = ? ORDER BY f.fine_id DESC');
$fineStmt->execute([$userId]);
$fines = $fineStmt->fetchAll();

$totalUnpaidStmt = $pdo->prepare('SELECT COALESCE(SUM(f.amount),0) FROM fines f INNER JOIN borrowings br ON br.borrowing_id = f.borrowing_id WHERE br.user_id = ? AND f.paid_status = ?');
$totalUnpaidStmt->execute([$userId, 'unpaid']);
$totalUnpaid = (float)$totalUnpaidStmt->fetchColumn();
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
  <?php include dirname(__DIR__, 2) . '/partials/sidebar-student.php'; ?>
  <div class="main-content">
    <?php include dirname(__DIR__, 2) . '/partials/topbar.php'; ?>
    <div class="content-area">
      <div class="kpi-grid mb-24">
        <div class="card kpi-card">
          <div class="kpi-icon red"><i class="fas fa-pound-sign"></i></div>
          <div class="kpi-data">
            <div class="kpi-label">Outstanding Balance</div>
            <div class="kpi-value">&pound;<?php echo number_format($totalUnpaid, 2); ?></div>
            <div class="kpi-sub">Please settle at the library counter</div>
          </div>
        </div>
      </div>
      <div class="card">
        <div class="card-header">
          <div>
            <h2><i class="fas fa-receipt" style="color:var(--danger);margin-right:8px"></i>Fine History</h2>
            <p>Penalties from overdue book returns</p>
          </div>
        </div>
        <?php if (!$fines) { ?>
          <div class="empty-state">
            <i class="fas fa-check-circle" style="color:var(--success)"></i>
            <p>No fines on your account. Keep up the good work!</p>
          </div>
        <?php } else { ?>
        <div class="table-wrapper">
          <table class="data-table">
            <thead>
              <tr><th>Book</th><th>Due Date</th><th>Returned</th><th>Amount</th><th>Status</th></tr>
            </thead>
            <tbody>
              <?php foreach ($fines as $f) { ?>
                <tr>
                  <td class="font-semibold"><?php echo htmlspecialchars($f['title']); ?></td>
                  <td><?php echo htmlspecialchars((string)$f['due_date']); ?></td>
                  <td><?php echo htmlspecialchars((string)($f['return_date'] ?? 'Not returned')); ?></td>
                  <td class="font-semibold">&pound;<?php echo number_format((float)$f['amount'], 2); ?></td>
                  <td>
                    <span class="badge <?php echo $f['paid_status'] === 'paid' ? 'badge-success' : 'badge-danger'; ?>">
                      <span class="badge-dot"></span>
                      <?php echo ucfirst(htmlspecialchars($f['paid_status'])); ?>
                    </span>
                  </td>
                </tr>
              <?php } ?>
            </tbody>
          </table>
        </div>
        <?php } ?>
      </div>
    </div>
    <?php include dirname(__DIR__, 2) . '/partials/footer.php'; ?>
  </div>
  <script src="<?php echo $basePath; ?>/assets/js/app.js"></script>
</body>
</html>