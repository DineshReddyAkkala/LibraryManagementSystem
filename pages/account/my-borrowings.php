<?php
require_once dirname(__DIR__, 2) . '/config/bootstrap.php';
require_login();
if (is_admin()) { header('Location: ../admin/dashboard.php'); exit; }
$name = current_user_name();
$basePath = '../..';
$currentPage = 'borrowings';
$pageTitle = 'My Borrowings';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!verify_csrf()) { header('Location: my-borrowings.php'); exit; }
  $action = $_POST['action'] ?? '';
  $borrowingId = (int)($_POST['borrowing_id'] ?? 0);
  if ($action === 'request_return' && $borrowingId > 0) {
    $result = request_return($pdo, (int)$_SESSION['user_id'], $borrowingId);
    set_flash($result['ok'] ? 'success' : 'error', $result['message']);
  }
  header('Location: my-borrowings.php');
  exit;
}

$flash = get_flash();
$statement = $pdo->prepare('SELECT br.borrowing_id, b.title, br.borrow_date, br.due_date, br.return_date, br.status, CASE WHEN br.return_date IS NOT NULL THEN "returned" WHEN br.status = "return_requested" THEN "return_requested" WHEN br.due_date < CURDATE() THEN "overdue" ELSE "borrowed" END AS display_status FROM borrowings br INNER JOIN books b ON b.book_id = br.book_id WHERE br.user_id = ? ORDER BY br.borrow_date DESC');
$statement->execute([(int)$_SESSION['user_id']]);
$borrowings = $statement->fetchAll();
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
      <div class="card">
        <div class="card-header">
          <div>
            <h2><i class="fas fa-book" style="color:var(--primary);margin-right:8px"></i>My Borrowings</h2>
            <p>Track your current and past borrowed books</p>
          </div>
        </div>
        <?php if ($flash) { ?>
          <div class="alert <?php echo $flash['type'] === 'success' ? 'alert-success' : 'alert-error'; ?>">
            <i class="fas fa-<?php echo $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
            <?php echo htmlspecialchars($flash['message']); ?>
          </div>
        <?php } ?>
        <?php if (!$borrowings) { ?>
          <div class="empty-state">
            <i class="fas fa-book-open"></i>
            <p>You haven't borrowed any books yet.</p>
            <a href="../books/search-books.php" class="btn btn-primary mt-16"><i class="fas fa-search"></i> Browse Catalogue</a>
          </div>
        <?php } else { ?>
        <div class="table-wrapper">
          <table class="data-table">
            <thead>
              <tr><th>Book Title</th><th>Borrowed</th><th>Due Date</th><th>Status</th><th class="text-right">Action</th></tr>
            </thead>
            <tbody>
              <?php foreach ($borrowings as $b) { ?>
                <?php
                  $badgeClass = 'badge-info';
                  if ($b['display_status'] === 'returned') $badgeClass = 'badge-success';
                  elseif ($b['display_status'] === 'overdue') $badgeClass = 'badge-danger';
                  elseif ($b['display_status'] === 'return_requested') $badgeClass = 'badge-info';
                  elseif ($b['display_status'] === 'borrowed') $badgeClass = 'badge-warning';
                ?>
                <tr>
                  <td class="font-semibold"><?php echo htmlspecialchars($b['title']); ?></td>
                  <td><?php echo htmlspecialchars($b['borrow_date']); ?></td>
                  <td><?php echo htmlspecialchars($b['due_date']); ?></td>
                  <td><span class="badge <?php echo $badgeClass; ?>"><span class="badge-dot"></span> <?php echo $b['display_status'] === 'return_requested' ? 'Return Requested' : ucfirst(htmlspecialchars($b['display_status'])); ?></span></td>
                  <td class="text-right">
                    <?php if ($b['display_status'] === 'return_requested') { ?>
                      <span class="text-sm text-muted"><i class="fas fa-clock"></i> Awaiting Approval</span>
                    <?php } elseif (empty($b['return_date'])) { ?>
                      <form method="post" action="my-borrowings.php" style="display:inline" data-confirm="Request return for this book?">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="request_return">
                        <input type="hidden" name="borrowing_id" value="<?php echo (int)$b['borrowing_id']; ?>">
                        <button class="btn btn-primary btn-sm" type="submit"><i class="fas fa-undo"></i> Request Return</button>
                      </form>
                    <?php } else { ?>
                      <span class="text-sm text-muted">Returned <?php echo htmlspecialchars($b['return_date']); ?></span>
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