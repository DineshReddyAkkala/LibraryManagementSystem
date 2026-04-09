<?php
require_once dirname(__DIR__, 2) . '/config/bootstrap.php';
require_admin();
$basePath = '../..';
$currentPage = 'notifications';
$pageTitle = 'Notifications';

$statement = $pdo->prepare('SELECT notification_id, message, notification_type, created_at, is_read FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50');
$statement->execute([(int)$_SESSION['user_id']]);
$notifications = $statement->fetchAll();

/* Mark all as read */
$pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0')->execute([(int)$_SESSION['user_id']]);
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
      <div class="card">
        <div class="card-header">
          <div>
            <h2><i class="fas fa-bell" style="color:var(--warning);margin-right:8px"></i>Notifications</h2>
            <p>System alerts and student activity updates</p>
          </div>
        </div>
        <?php if (!$notifications) { ?>
          <div class="empty-state">
            <i class="fas fa-bell-slash"></i>
            <p>No notifications yet.</p>
          </div>
        <?php } else { ?>
          <?php foreach ($notifications as $n) { ?>
            <?php
              $icon = 'fas fa-info-circle';
              if ($n['notification_type'] === 'due_date') $icon = 'fas fa-clock';
              elseif ($n['notification_type'] === 'reservation') $icon = 'fas fa-bookmark';
              elseif ($n['notification_type'] === 'fine') $icon = 'fas fa-pound-sign';
            ?>
            <div class="notification-item <?php echo (int)$n['is_read'] === 0 ? 'unread' : ''; ?>">
              <div>
                <div class="notif-type"><i class="<?php echo $icon; ?>" style="margin-right:6px;color:var(--primary)"></i><?php echo ucfirst(htmlspecialchars(str_replace('_', ' ', $n['notification_type']))); ?></div>
                <div class="notif-message"><?php echo htmlspecialchars($n['message']); ?></div>
              </div>
              <div class="notif-time"><?php echo htmlspecialchars($n['created_at']); ?></div>
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