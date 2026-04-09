<?php
require_once dirname(__DIR__, 2) . '/config/bootstrap.php';
require_login();
if (is_admin()) { header('Location: ../admin/dashboard.php'); exit; }
$name = current_user_name();
$basePath = '../..';
$currentPage = 'reservations';
$pageTitle = 'My Reservations';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!verify_csrf()) { header('Location: my-reservations.php'); exit; }
  $action = $_POST['action'] ?? '';
  $reservationId = (int)($_POST['reservation_id'] ?? 0);
  if ($action === 'cancel' && $reservationId > 0) {
    $result = cancel_reservation($pdo, (int)$_SESSION['user_id'], $reservationId);
    set_flash($result['ok'] ? 'success' : 'error', $result['message']);
  }
  header('Location: my-reservations.php');
  exit;
}

$flash = get_flash();
$statement = $pdo->prepare('SELECT r.reservation_id, b.title, r.reservation_date, r.position, r.status FROM reservations r INNER JOIN books b ON b.book_id = r.book_id WHERE r.user_id = ? ORDER BY r.reservation_date DESC');
$statement->execute([(int)$_SESSION['user_id']]);
$reservations = $statement->fetchAll();
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
            <h2><i class="fas fa-bookmark" style="color:var(--warning);margin-right:8px"></i>My Reservations</h2>
            <p>Track and manage your active reservations</p>
          </div>
        </div>
        <?php if ($flash) { ?>
          <div class="alert <?php echo $flash['type'] === 'success' ? 'alert-success' : 'alert-error'; ?>">
            <i class="fas fa-<?php echo $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
            <?php echo htmlspecialchars($flash['message']); ?>
          </div>
        <?php } ?>
        <?php if (!$reservations) { ?>
          <div class="empty-state">
            <i class="fas fa-bookmark"></i>
            <p>No reservations found.</p>
          </div>
        <?php } else { ?>
        <div class="table-wrapper">
          <table class="data-table">
            <thead>
              <tr><th>Book Title</th><th>Reserved On</th><th>Queue Position</th><th>Status</th><th class="text-right">Action</th></tr>
            </thead>
            <tbody>
              <?php foreach ($reservations as $r) { ?>
                <?php
                  $badgeClass = $r['status'] === 'active' ? 'badge-warning' : ($r['status'] === 'fulfilled' ? 'badge-success' : 'badge-neutral');
                ?>
                <tr>
                  <td class="font-semibold"><?php echo htmlspecialchars($r['title']); ?></td>
                  <td><?php echo htmlspecialchars($r['reservation_date']); ?></td>
                  <td>#<?php echo (int)$r['position']; ?></td>
                  <td><span class="badge <?php echo $badgeClass; ?>"><span class="badge-dot"></span> <?php echo ucfirst(htmlspecialchars($r['status'])); ?></span></td>
                  <td class="text-right">
                    <?php if ($r['status'] === 'active') { ?>
                      <form method="post" action="my-reservations.php" style="display:inline" data-confirm="Cancel this reservation?">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="cancel">
                        <input type="hidden" name="reservation_id" value="<?php echo (int)$r['reservation_id']; ?>">
                        <button class="btn btn-danger btn-sm" type="submit"><i class="fas fa-times"></i> Cancel</button>
                      </form>
                    <?php } else { ?>
                      <span class="text-sm text-muted"><?php echo ucfirst(htmlspecialchars($r['status'])); ?></span>
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