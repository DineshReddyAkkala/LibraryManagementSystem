<?php
require_once dirname(__DIR__, 2) . '/config/bootstrap.php';
require_admin();
$basePath = '../..';
$currentPage = 'manage-fines';
$pageTitle = 'Manage Fines';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) { header('Location: manage-fines.php'); exit; }
    $action = $_POST['action'] ?? '';

    if ($action === 'add-fine') {
        $borrowingId = (int)($_POST['borrowing_id'] ?? 0);
        $amount = round((float)($_POST['amount'] ?? 0), 2);

        if ($borrowingId < 1 || $amount <= 0) {
            set_flash('error', 'Valid borrowing and amount are required.');
        } else {
            $insert = $pdo->prepare('INSERT INTO fines (borrowing_id, amount, paid_status) VALUES (?, ?, ?)');
            $insert->execute([$borrowingId, $amount, 'unpaid']);

            $bStmt = $pdo->prepare('SELECT br.user_id, b.title FROM borrowings br INNER JOIN books b ON b.book_id = br.book_id WHERE br.borrowing_id = ?');
            $bStmt->execute([$borrowingId]);
            $bRow = $bStmt->fetch();
            if ($bRow) {
                notify_user($pdo, (int)$bRow['user_id'], 'Fine of £' . number_format($amount, 2) . ' issued for "' . $bRow['title'] . '".', 'fine', 'Fine Issued - £' . number_format($amount, 2));
            }
            set_flash('success', 'Fine issued successfully.');
        }
    }

    if ($action === 'mark-paid') {
        $fineId = (int)($_POST['fine_id'] ?? 0);
        if ($fineId > 0) {
            $pdo->prepare("UPDATE fines SET paid_status = 'paid' WHERE fine_id = ?")->execute([$fineId]);
            set_flash('success', 'Fine marked as paid.');
        }
    }

    header('Location: manage-fines.php');
    exit;
}

$flash = get_flash();

$overdueBorrowings = $pdo->query('SELECT br.borrowing_id, u.full_name, b.title, br.due_date FROM borrowings br INNER JOIN users u ON u.user_id = br.user_id INNER JOIN books b ON b.book_id = br.book_id WHERE br.return_date IS NULL AND br.due_date < CURDATE() AND br.borrowing_id NOT IN (SELECT f.borrowing_id FROM fines f) ORDER BY br.due_date ASC')->fetchAll();

$fines = $pdo->query('SELECT f.fine_id, f.amount, f.paid_status, u.full_name, b.title, br.due_date, br.return_date, br.borrow_date FROM fines f INNER JOIN borrowings br ON br.borrowing_id = f.borrowing_id INNER JOIN users u ON u.user_id = br.user_id INNER JOIN books b ON b.book_id = br.book_id ORDER BY f.fine_id DESC LIMIT 100')->fetchAll();

$totalUnpaid = 0;
$totalPaid = 0;
foreach ($fines as $f) {
    if ($f['paid_status'] === 'unpaid') $totalUnpaid += (float)$f['amount'];
    elseif ($f['paid_status'] === 'paid') $totalPaid += (float)$f['amount'];
}
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

      <?php if ($flash) { ?>
        <div class="alert <?php echo $flash['type'] === 'success' ? 'alert-success' : 'alert-error'; ?> mb-24">
          <i class="fas fa-<?php echo $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
          <?php echo htmlspecialchars($flash['message']); ?>
        </div>
      <?php } ?>

      <div class="kpi-grid mb-24">
        <div class="card kpi-card">
          <div class="kpi-icon red"><i class="fas fa-exclamation-circle"></i></div>
          <div class="kpi-data">
            <div class="kpi-label">Outstanding Fines</div>
            <div class="kpi-value">£<?php echo number_format($totalUnpaid, 2); ?></div>
          </div>
        </div>
        <div class="card kpi-card">
          <div class="kpi-icon green"><i class="fas fa-check-circle"></i></div>
          <div class="kpi-data">
            <div class="kpi-label">Collected</div>
            <div class="kpi-value">£<?php echo number_format($totalPaid, 2); ?></div>
          </div>
        </div>
        <div class="card kpi-card">
          <div class="kpi-icon amber"><i class="fas fa-clock"></i></div>
          <div class="kpi-data">
            <div class="kpi-label">Overdue Books</div>
            <div class="kpi-value"><?php echo count($overdueBorrowings); ?></div>
          </div>
        </div>
      </div>

      <?php if ($overdueBorrowings) { ?>
      <div class="card mb-24">
        <div class="card-header">
          <div>
            <h2><i class="fas fa-gavel" style="color:var(--danger);margin-right:8px"></i>Issue Fines</h2>
            <p>Currently overdue borrowings that can be fined</p>
          </div>
        </div>
        <div class="table-wrapper">
          <table class="data-table">
            <thead>
              <tr><th>Student</th><th>Book</th><th>Due Date</th><th>Days Overdue</th><th class="text-right">Issue Fine</th></tr>
            </thead>
            <tbody>
              <?php foreach ($overdueBorrowings as $item) { ?>
                <?php $daysOverdue = (int)((time() - strtotime((string)$item['due_date'])) / 86400); ?>
                <tr>
                  <td class="font-semibold"><?php echo htmlspecialchars($item['full_name']); ?></td>
                  <td><?php echo htmlspecialchars($item['title']); ?></td>
                  <td><?php echo htmlspecialchars((string)$item['due_date']); ?></td>
                  <td><span class="badge badge-danger"><?php echo $daysOverdue; ?> days</span></td>
                  <td class="text-right">
                    <form method="post" action="manage-fines.php" style="display:inline-flex;gap:6px;align-items:center" data-confirm="Issue this fine?">
                      <?php echo csrf_field(); ?>
                      <input type="hidden" name="action" value="add-fine">
                      <input type="hidden" name="borrowing_id" value="<?php echo (int)$item['borrowing_id']; ?>">
                      <input type="number" name="amount" step="0.01" min="0.01" value="<?php echo number_format($daysOverdue * 0.50, 2); ?>" style="width:90px" title="Fine amount (£)">
                      <button class="btn btn-sm btn-danger" type="submit"><i class="fas fa-gavel"></i> Fine</button>
                    </form>
                  </td>
                </tr>
              <?php } ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php } ?>

      <div class="card">
        <div class="card-header">
          <div>
            <h2><i class="fas fa-receipt" style="color:var(--primary);margin-right:8px"></i>All Fines (<?php echo count($fines); ?>)</h2>
            <p>Fine records and payment status</p>
          </div>
        </div>
        <?php if (!$fines) { ?>
          <div class="empty-state"><i class="fas fa-receipt"></i><p>No fines have been issued yet.</p></div>
        <?php } else { ?>
        <div class="table-wrapper">
          <table class="data-table">
            <thead>
              <tr><th>Student</th><th>Book</th><th>Amount</th><th>Status</th><th>Issued</th><th class="text-right">Actions</th></tr>
            </thead>
            <tbody>
              <?php foreach ($fines as $fine) { ?>
                <?php
                  if ($fine['paid_status'] === 'paid') { $fBadge = 'badge-success'; $fLabel = 'Paid'; }
                  else { $fBadge = 'badge-danger'; $fLabel = 'Unpaid'; }
                ?>
                <tr>
                  <td class="font-semibold"><?php echo htmlspecialchars($fine['full_name']); ?></td>
                  <td><?php echo htmlspecialchars($fine['title']); ?></td>
                  <td>£<?php echo number_format((float)$fine['amount'], 2); ?></td>
                  <td><span class="badge <?php echo $fBadge; ?>"><span class="badge-dot"></span> <?php echo $fLabel; ?></span></td>
                  <td class="text-sm text-muted"><?php echo htmlspecialchars(substr((string)$fine['borrow_date'], 0, 10)); ?></td>
                  <td class="text-right">
                    <?php if ($fine['paid_status'] === 'unpaid') { ?>
                      <div style="display:inline-flex;gap:6px">
                        <form method="post" action="manage-fines.php" style="display:inline" data-confirm="Mark this fine as paid?">
                          <?php echo csrf_field(); ?>
                          <input type="hidden" name="action" value="mark-paid">
                          <input type="hidden" name="fine_id" value="<?php echo (int)$fine['fine_id']; ?>">
                          <button class="btn btn-sm btn-primary" type="submit"><i class="fas fa-check"></i> Paid</button>
                        </form>
                      </div>
                    <?php } else { ?>
                      <span class="text-sm text-muted">Closed</span>
                    <?php } ?>
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
