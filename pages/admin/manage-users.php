<?php
require_once dirname(__DIR__, 2) . '/config/bootstrap.php';
require_admin();
$basePath = '../..';
$currentPage = 'manage-users';
$pageTitle = 'Manage Users';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) { header('Location: manage-users.php'); exit; }
    $action = $_POST['action'] ?? '';
    $userId = (int)($_POST['user_id'] ?? 0);

    if ($userId > 0 && in_array($action, ['activate', 'deactivate'], true)) {
        $newStatus = $action === 'activate' ? 'active' : 'inactive';
        $stmt = $pdo->prepare('UPDATE users SET status = ? WHERE user_id = ?');
        $stmt->execute([$newStatus, $userId]);
        set_flash('success', 'User status updated.');
    }

    if ($action === 'edit-user' && $userId > 0) {
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        if ($fullName === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            set_flash('error', 'Valid name and email are required.');
        } else {
            $dup = $pdo->prepare('SELECT user_id FROM users WHERE email = ? AND user_id != ?');
            $dup->execute([$email, $userId]);
            if ($dup->fetch()) {
                set_flash('error', 'Another account already uses that email.');
            } else {
                $pdo->prepare('UPDATE users SET full_name = ?, email = ? WHERE user_id = ?')->execute([$fullName, $email, $userId]);
                set_flash('success', 'User details updated.');
            }
        }
    }

    if ($action === 'delete-user' && $userId > 0) {
        $chk = $pdo->prepare("SELECT r.role_name FROM users u INNER JOIN roles r ON r.role_id = u.role_id WHERE u.user_id = ?");
        $chk->execute([$userId]);
        $chkRow = $chk->fetch();
        if ($chkRow && $chkRow['role_name'] === 'admin') {
            set_flash('error', 'Admin accounts cannot be deleted.');
        } else {
            $activeBorrow = $pdo->prepare('SELECT COUNT(*) FROM borrowings WHERE user_id = ? AND return_date IS NULL');
            $activeBorrow->execute([$userId]);
            if ((int)$activeBorrow->fetchColumn() > 0) {
                set_flash('error', 'Cannot delete user with active borrowings.');
            } else {
                $pdo->beginTransaction();
                $pdo->prepare("DELETE FROM reservations WHERE user_id = ? AND status = 'active'")->execute([$userId]);
                $pdo->prepare('DELETE FROM notifications WHERE user_id = ?')->execute([$userId]);
                $pdo->prepare('DELETE FROM search_history WHERE user_id = ?')->execute([$userId]);
                $pdo->prepare('DELETE FROM borrow_history WHERE user_id = ?')->execute([$userId]);
                $pdo->prepare('DELETE FROM users WHERE user_id = ?')->execute([$userId]);
                $pdo->commit();
                set_flash('success', 'User account deleted.');
            }
        }
    }

    header('Location: manage-users.php');
    exit;
}

$flash = get_flash();
$usersStmt = $pdo->query('SELECT u.user_id, u.full_name, u.email, u.status, u.is_email_confirmed, u.created_at, r.role_name FROM users u INNER JOIN roles r ON r.role_id = u.role_id ORDER BY u.created_at DESC');
$users = $usersStmt->fetchAll();

$editUser = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    foreach ($users as $u) {
        if ((int)$u['user_id'] === $editId && $u['role_name'] !== 'admin') {
            $editUser = $u;
            break;
        }
    }
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

      <?php if ($editUser) { ?>
      <div class="card mb-24">
        <div class="card-header">
          <div>
            <h2><i class="fas fa-user-edit" style="color:var(--primary);margin-right:8px"></i>Edit User</h2>
            <p>Update details for "<?php echo htmlspecialchars($editUser['full_name']); ?>"</p>
          </div>
          <a href="manage-users.php" class="btn btn-ghost"><i class="fas fa-times"></i> Cancel</a>
        </div>
        <form method="post" action="manage-users.php">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="action" value="edit-user">
          <input type="hidden" name="user_id" value="<?php echo (int)$editUser['user_id']; ?>">
          <div class="form-grid">
            <div class="form-group">
              <label for="full_name">Full Name</label>
              <input id="full_name" type="text" name="full_name" value="<?php echo htmlspecialchars($editUser['full_name']); ?>" required>
            </div>
            <div class="form-group">
              <label for="email">Email</label>
              <input id="email" type="email" name="email" value="<?php echo htmlspecialchars($editUser['email']); ?>" required>
            </div>
          </div>
          <button class="btn btn-primary mt-16" type="submit"><i class="fas fa-save"></i> Save Changes</button>
        </form>
      </div>
      <?php } ?>

      <div class="card">
        <div class="card-header">
          <div>
            <h2><i class="fas fa-users-cog" style="color:var(--primary);margin-right:8px"></i>User Accounts (<?php echo count($users); ?>)</h2>
            <p>Manage student and administrator accounts</p>
          </div>
        </div>
        <div class="table-wrapper">
          <table class="data-table">
            <thead>
              <tr><th>Name</th><th>Email</th><th>Role</th><th>Verified</th><th>Status</th><th>Joined</th><th class="text-right">Actions</th></tr>
            </thead>
            <tbody>
              <?php foreach ($users as $user) { ?>
                <tr>
                  <td class="font-semibold"><?php echo htmlspecialchars($user['full_name']); ?></td>
                  <td><?php echo htmlspecialchars($user['email']); ?></td>
                  <td><span class="badge <?php echo $user['role_name'] === 'admin' ? 'badge-info' : 'badge-neutral'; ?>"><?php echo ucfirst(htmlspecialchars($user['role_name'])); ?></span></td>
                  <td>
                    <?php if ((int)$user['is_email_confirmed'] === 1) { ?>
                      <span class="badge badge-success"><span class="badge-dot"></span> Verified</span>
                    <?php } else { ?>
                      <span class="badge badge-warning"><span class="badge-dot"></span> Pending</span>
                    <?php } ?>
                  </td>
                  <td>
                    <span class="badge <?php echo $user['status'] === 'active' ? 'badge-success' : 'badge-danger'; ?>">
                      <span class="badge-dot"></span> <?php echo ucfirst(htmlspecialchars($user['status'])); ?>
                    </span>
                  </td>
                  <td class="text-sm text-muted"><?php echo htmlspecialchars(substr((string)$user['created_at'], 0, 10)); ?></td>
                  <td class="text-right">
                    <?php if ($user['role_name'] !== 'admin') { ?>
                      <div style="display:inline-flex;gap:6px">
                        <a href="manage-users.php?edit=<?php echo (int)$user['user_id']; ?>" class="btn btn-sm btn-secondary"><i class="fas fa-edit"></i></a>
                        <form method="post" action="manage-users.php" style="display:inline" data-confirm="<?php echo $user['status'] === 'active' ? 'Deactivate this user?' : 'Activate this user?'; ?>">
                          <?php echo csrf_field(); ?>
                          <input type="hidden" name="user_id" value="<?php echo (int)$user['user_id']; ?>">
                          <?php if ($user['status'] === 'active') { ?>
                            <input type="hidden" name="action" value="deactivate">
                            <button class="btn btn-sm btn-danger" type="submit" title="Deactivate"><i class="fas fa-user-slash"></i></button>
                          <?php } else { ?>
                            <input type="hidden" name="action" value="activate">
                            <button class="btn btn-sm btn-primary" type="submit" title="Activate"><i class="fas fa-user-check"></i></button>
                          <?php } ?>
                        </form>
                        <form method="post" action="manage-users.php" style="display:inline" data-confirm="Permanently delete this user?">
                          <?php echo csrf_field(); ?>
                          <input type="hidden" name="action" value="delete-user">
                          <input type="hidden" name="user_id" value="<?php echo (int)$user['user_id']; ?>">
                          <button class="btn btn-sm btn-danger" type="submit" title="Delete"><i class="fas fa-trash"></i></button>
                        </form>
                      </div>
                    <?php } else { ?>
                      <span class="text-sm text-muted"><i class="fas fa-shield-alt"></i> Protected</span>
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