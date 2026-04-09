<?php
require_once dirname(__DIR__, 2) . '/config/bootstrap.php';
require_login();
if (is_admin()) { header('Location: ../admin/dashboard.php'); exit; }
$userId = (int)$_SESSION['user_id'];
$name = current_user_name();
$basePath = '../..';
$currentPage = 'profile';
$pageTitle = 'My Profile';
$flash = get_flash();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) { header('Location: my-profile.php'); exit; }
    $action = $_POST['action'] ?? '';

    if ($action === 'profile') {
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        if ($fullName === '' || $email === '') {
            set_flash('error', 'Name and email are required.');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            set_flash('error', 'Please enter a valid email address.');
        } else {
            $check = $pdo->prepare('SELECT user_id FROM users WHERE email = ? AND user_id <> ? LIMIT 1');
            $check->execute([$email, $userId]);
            if ($check->fetch()) {
                set_flash('error', 'Email is already used by another account.');
            } else {
                $update = $pdo->prepare('UPDATE users SET full_name = ?, email = ? WHERE user_id = ?');
                $update->execute([$fullName, $email, $userId]);
                $_SESSION['full_name'] = $fullName;
                set_flash('success', 'Profile updated successfully.');
            }
        }
    }

    if ($action === 'password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $userStmt = $pdo->prepare('SELECT password_hash FROM users WHERE user_id = ?');
        $userStmt->execute([$userId]);
        $user = $userStmt->fetch();
        if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
            set_flash('error', 'Current password is incorrect.');
        } elseif ($newPassword !== $confirmPassword) {
            set_flash('error', 'New passwords do not match.');
        } elseif (strlen($newPassword) < 8 || !preg_match('/[A-Z]/', $newPassword) || !preg_match('/[a-z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword) || !preg_match('/[^A-Za-z0-9]/', $newPassword)) {
            set_flash('error', 'Password must be at least 8 characters and include uppercase, lowercase, digit, and special character.');
        } else {
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $updatePass = $pdo->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?');
            $updatePass->execute([$newHash, $userId]);
            set_flash('success', 'Password changed successfully.');
        }
    }

    header('Location: my-profile.php');
    exit;
}

$profileStmt = $pdo->prepare('SELECT full_name, email, status, created_at FROM users WHERE user_id = ? LIMIT 1');
$profileStmt->execute([$userId]);
$profile = $profileStmt->fetch();
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
      <?php if ($flash) { ?>
        <div class="alert <?php echo $flash['type'] === 'success' ? 'alert-success' : 'alert-error'; ?> mb-24">
          <i class="fas fa-<?php echo $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
          <?php echo htmlspecialchars($flash['message']); ?>
        </div>
      <?php } ?>

      <div class="kpi-grid mb-24">
        <div class="card kpi-card">
          <div class="kpi-icon green"><i class="fas fa-user-check"></i></div>
          <div class="kpi-data">
            <div class="kpi-label">Account Status</div>
            <div class="kpi-value"><?php echo ucfirst(htmlspecialchars((string)$profile['status'])); ?></div>
          </div>
        </div>
        <div class="card kpi-card">
          <div class="kpi-icon blue"><i class="fas fa-calendar-alt"></i></div>
          <div class="kpi-data">
            <div class="kpi-label">Member Since</div>
            <div class="kpi-value"><?php echo htmlspecialchars(substr((string)$profile['created_at'], 0, 10)); ?></div>
          </div>
        </div>
      </div>

      <div class="form-grid">
        <div class="card">
          <div class="card-header"><div><h2><i class="fas fa-user-edit" style="color:var(--primary);margin-right:8px"></i>Update Profile</h2></div></div>
          <form method="post" action="my-profile.php">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="profile">
            <div class="form-group">
              <label for="full_name">Full Name</label>
              <input id="full_name" name="full_name" type="text" required value="<?php echo htmlspecialchars((string)$profile['full_name']); ?>">
            </div>
            <div class="form-group">
              <label for="email">Email Address</label>
              <input id="email" name="email" type="email" required value="<?php echo htmlspecialchars((string)$profile['email']); ?>">
            </div>
            <button class="btn btn-primary" type="submit"><i class="fas fa-save"></i> Save Changes</button>
          </form>
        </div>
        <div class="card">
          <div class="card-header"><div><h2><i class="fas fa-lock" style="color:var(--warning);margin-right:8px"></i>Change Password</h2></div></div>
          <form method="post" action="my-profile.php">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="password">
            <div class="form-group">
              <label for="current_password">Current Password</label>
              <input id="current_password" name="current_password" type="password" required placeholder="Enter current password">
            </div>
            <div class="form-group">
              <label for="new_password">New Password</label>
              <input id="new_password" name="new_password" type="password" required placeholder="Create a strong password">
              <ul class="password-hints" id="passwordHints">
                <li id="hint-length"><i class="fas fa-circle"></i> At least 8 characters</li>
                <li id="hint-upper"><i class="fas fa-circle"></i> One uppercase letter (A-Z)</li>
                <li id="hint-lower"><i class="fas fa-circle"></i> One lowercase letter (a-z)</li>
                <li id="hint-digit"><i class="fas fa-circle"></i> One digit (0-9)</li>
                <li id="hint-special"><i class="fas fa-circle"></i> One special character (!@#$%&hellip;)</li>
              </ul>
            </div>
            <div class="form-group">
              <label for="confirm_password">Confirm New Password</label>
              <input id="confirm_password" name="confirm_password" type="password" required placeholder="Re-enter new password">
            </div>
            <button class="btn btn-primary" type="submit"><i class="fas fa-key"></i> Update Password</button>
          </form>
        </div>
      </div>
    </div>
    <?php include dirname(__DIR__, 2) . '/partials/footer.php'; ?>
  </div>
  <script src="<?php echo $basePath; ?>/assets/js/app.js"></script>
  <script>
  (function(){
    var pw = document.getElementById('new_password');
    if (!pw) return;
    var rules = [
      {id:'hint-length', test:function(v){return v.length>=8;}},
      {id:'hint-upper',  test:function(v){return /[A-Z]/.test(v);}},
      {id:'hint-lower',  test:function(v){return /[a-z]/.test(v);}},
      {id:'hint-digit',  test:function(v){return /[0-9]/.test(v);}},
      {id:'hint-special', test:function(v){return /[^A-Za-z0-9]/.test(v);}}
    ];
    pw.addEventListener('input', function(){
      rules.forEach(function(r){
        var el = document.getElementById(r.id);
        if(r.test(pw.value)){el.classList.add('met');el.classList.remove('unmet');}
        else{el.classList.add('unmet');el.classList.remove('met');}
      });
    });
  })();
  </script>
</body>
</html>