<?php
require_once dirname(__DIR__, 2) . '/config/bootstrap.php';
require_admin();
$basePath = '../..';
$currentPage = 'circulation';
$pageTitle = 'Circulation Control';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) { header('Location: circulation.php'); exit; }
    $action = $_POST['action'] ?? '';

    if ($action === 'approve-return') {
        $borrowingId = (int)($_POST['borrowing_id'] ?? 0);
        if ($borrowingId > 0) {
            $stmt = $pdo->prepare('SELECT user_id FROM borrowings WHERE borrowing_id = ? AND status = ?');
            $stmt->execute([$borrowingId, 'return_requested']);
            $item = $stmt->fetch();
            if ($item) {
                $result = return_borrowed_book($pdo, (int)$item['user_id'], $borrowingId);
                set_flash($result['ok'] ? 'success' : 'error', $result['message']);
            } else {
                set_flash('error', 'No pending return request found for this borrowing.');
            }
        }
    }

    if ($action === 'mark-returned') {
        $borrowingId = (int)($_POST['borrowing_id'] ?? 0);
        if ($borrowingId > 0) {
            $stmt = $pdo->prepare('SELECT user_id FROM borrowings WHERE borrowing_id = ?');
            $stmt->execute([$borrowingId]);
            $item = $stmt->fetch();
            if ($item) {
                $result = return_borrowed_book($pdo, (int)$item['user_id'], $borrowingId);
                set_flash($result['ok'] ? 'success' : 'error', $result['message']);
            }
        }
    }

    if ($action === 'cancel-reservation') {
        $reservationId = (int)($_POST['reservation_id'] ?? 0);
        if ($reservationId > 0) {
            $stmt = $pdo->prepare('SELECT user_id FROM reservations WHERE reservation_id = ?');
            $stmt->execute([$reservationId]);
            $item = $stmt->fetch();
            if ($item) {
                $result = cancel_reservation($pdo, (int)$item['user_id'], $reservationId);
                set_flash($result['ok'] ? 'success' : 'error', $result['message']);
            }
        }
    }

    header('Location: circulation.php');
    exit;
}

$flash = get_flash();
$borrowings = $pdo->query('SELECT br.borrowing_id, u.full_name, b.title, br.borrow_date, br.due_date, br.return_date, br.status FROM borrowings br INNER JOIN users u ON u.user_id = br.user_id INNER JOIN books b ON b.book_id = br.book_id ORDER BY br.borrow_date DESC LIMIT 50')->fetchAll();
$reservations = $pdo->query('SELECT r.reservation_id, u.full_name, b.title, r.reservation_date, r.position, r.status FROM reservations r INNER JOIN users u ON u.user_id = r.user_id INNER JOIN books b ON b.book_id = r.book_id ORDER BY r.reservation_date DESC LIMIT 50')->fetchAll();
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

      <div class="card mb-24">
        <div class="card-header">
          <div>
            <h2><i class="fas fa-exchange-alt" style="color:var(--primary);margin-right:8px"></i>Borrowings</h2>
            <p>Active and returned loan records (last 50)</p>
          </div>
        </div>
        <div class="table-wrapper">
          <table class="data-table">
            <thead>
              <tr><th>Student</th><th>Book</th><th>Borrowed</th><th>Due</th><th>Status</th><th class="text-right">Action</th></tr>
            </thead>
            <tbody>
              <?php foreach ($borrowings as $item) { ?>
                <?php
                  $isReturned = !empty($item['return_date']);
                  $isReturnRequested = !$isReturned && $item['status'] === 'return_requested';
                  $isOverdue = !$isReturned && !$isReturnRequested && strtotime((string)$item['due_date']) < time();
                  if ($isReturned) { $badgeClass = 'badge-success'; $label = 'Returned'; }
                  elseif ($isReturnRequested) { $badgeClass = 'badge-info'; $label = 'Return Requested'; }
                  elseif ($isOverdue) { $badgeClass = 'badge-danger'; $label = 'Overdue'; }
                  else { $badgeClass = 'badge-warning'; $label = 'Borrowed'; }
                ?>
                <tr>
                  <td class="font-semibold"><?php echo htmlspecialchars($item['full_name']); ?></td>
                  <td><?php echo htmlspecialchars($item['title']); ?></td>
                  <td><?php echo htmlspecialchars((string)$item['borrow_date']); ?></td>
                  <td><?php echo htmlspecialchars((string)$item['due_date']); ?></td>
                  <td><span class="badge <?php echo $badgeClass; ?>"><span class="badge-dot"></span> <?php echo $label; ?></span></td>
                  <td class="text-right">
                    <?php if ($isReturnRequested) { ?>
                      <form method="post" action="circulation.php" style="display:inline" data-confirm="Approve this return request?">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="approve-return">
                        <input type="hidden" name="borrowing_id" value="<?php echo (int)$item['borrowing_id']; ?>">
                        <button class="btn btn-success btn-sm" type="submit"><i class="fas fa-check"></i> Approve Return</button>
                      </form>
                    <?php } elseif (!$isReturned) { ?>
                      <form method="post" action="circulation.php" style="display:inline" data-confirm="Mark this book as returned?">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="mark-returned">
                        <input type="hidden" name="borrowing_id" value="<?php echo (int)$item['borrowing_id']; ?>">
                        <button class="btn btn-primary btn-sm" type="submit"><i class="fas fa-undo"></i> Return</button>
                      </form>
                    <?php } else { ?>
                      <span class="text-sm text-muted">Closed</span>
                    <?php } ?>
                  </td>
                </tr>
              <?php } ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <div>
            <h2><i class="fas fa-layer-group" style="color:var(--warning);margin-right:8px"></i>Reservations</h2>
            <p>Queue positions and reservation status (last 50)</p>
          </div>
        </div>
        <div class="table-wrapper">
          <table class="data-table">
            <thead>
              <tr><th>Student</th><th>Book</th><th>Date</th><th>Queue</th><th>Status</th><th class="text-right">Action</th></tr>
            </thead>
            <tbody>
              <?php foreach ($reservations as $item) { ?>
                <?php $badgeClass = $item['status'] === 'active' ? 'badge-warning' : ($item['status'] === 'fulfilled' ? 'badge-success' : 'badge-neutral'); ?>
                <tr>
                  <td class="font-semibold"><?php echo htmlspecialchars($item['full_name']); ?></td>
                  <td><?php echo htmlspecialchars($item['title']); ?></td>
                  <td><?php echo htmlspecialchars((string)$item['reservation_date']); ?></td>
                  <td>#<?php echo (int)$item['position']; ?></td>
                  <td><span class="badge <?php echo $badgeClass; ?>"><span class="badge-dot"></span> <?php echo ucfirst(htmlspecialchars((string)$item['status'])); ?></span></td>
                  <td class="text-right">
                    <?php if ($item['status'] === 'active') { ?>
                      <form method="post" action="circulation.php" style="display:inline" data-confirm="Cancel this reservation?">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="cancel-reservation">
                        <input type="hidden" name="reservation_id" value="<?php echo (int)$item['reservation_id']; ?>">
                        <button class="btn btn-danger btn-sm" type="submit"><i class="fas fa-times"></i> Cancel</button>
                      </form>
                    <?php } else { ?>
                      <span class="text-sm text-muted">Closed</span>
                    <?php } ?>
                  </td>
                </tr>
              <?php } ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
    <?php include dirname(__DIR__, 2) . '/partials/footer.php'; ?>
  </div>
  <script src="<?php echo $basePath; ?>/assets/js/app.js"></script>
</body>
</html>