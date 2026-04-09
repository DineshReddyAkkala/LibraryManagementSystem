<?php
require_once dirname(__DIR__, 2) . '/config/bootstrap.php';

if (!empty($_SESSION['user_id'])) {
  if (($_SESSION['role_name'] ?? '') === 'admin') {
    header('Location: ../admin/dashboard.php');
  } else {
    header('Location: ../dashboard/student-dashboard.php');
  }
    exit;
}

$message = '';
$success = false;
$nameValue = '';
$emailValue = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) { $message = 'Invalid form submission. Please try again.'; } else {
    $nameValue = trim($_POST['full_name'] ?? '');
    $emailValue = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($nameValue === '' || $emailValue === '' || $password === '') {
        $message = 'All fields are required.';
    } elseif (!filter_var($emailValue, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address.';
    } elseif (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password) || !preg_match('/[^A-Za-z0-9]/', $password)) {
        $message = 'Password must be at least 8 characters and include uppercase, lowercase, digit, and special character.';
    } else {
        $existing = $pdo->prepare('SELECT user_id FROM users WHERE email = ?');
        $existing->execute([$emailValue]);
        if ($existing->fetch()) {
            $message = 'An account with this email already exists.';
        } else {
            $pdo->beginTransaction();
            $roleStatement = $pdo->prepare('SELECT role_id FROM roles WHERE role_name = ?');
            $roleStatement->execute(['student']);
            $role = $roleStatement->fetch();
            if (!$role) {
                $insertRole = $pdo->prepare('INSERT INTO roles (role_name) VALUES (?)');
                $insertRole->execute(['student']);
                $roleId = (int)$pdo->lastInsertId();
            } else {
                $roleId = (int)$role['role_id'];
            }

            $token = bin2hex(random_bytes(32));
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $insertUser = $pdo->prepare('INSERT INTO users (role_id, full_name, email, password_hash, is_email_confirmed, confirmation_token, confirmation_sent_at, status) VALUES (?, ?, ?, ?, 0, ?, NOW(), ?)');
            $insertUser->execute([$roleId, $nameValue, $emailValue, $passwordHash, $token, 'inactive']);
            $pdo->commit();

            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $path = rtrim(dirname($_SERVER['REQUEST_URI']), '/\\');
            $confirmUrl = $scheme . '://' . $host . $path . '/confirm-email.php?token=' . $token;
            $subject = 'Confirm your library account';
            $body = "Hello $nameValue,\n\nPlease confirm your account by clicking the link below:\n$confirmUrl\n\nIf you did not request this, please ignore this email.";
            smtp_send($emailValue, $nameValue, $subject, $body);

            $success = true;
            $message = 'Registration successful! Please check your email to confirm your account.';
        }
    }
    } /* csrf */
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Create Account - UniLibrary</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="auth-layout">
  <nav class="auth-topnav">
    <a href="../../index.php" class="auth-topnav-brand"><i class="fas fa-book-open"></i> UniLibrary</a>
    <div class="auth-topnav-links">
      <a href="../../index.php"><i class="fas fa-home"></i> Home</a>
      <a href="login.php"><i class="fas fa-sign-in-alt"></i> Sign In</a>
    </div>
  </nav>
  <div class="auth-card">
    <div class="auth-brand">
      <div class="auth-logo"><i class="fas fa-user-plus"></i></div>
      <h1>Create Account</h1>
      <p>Register with your university email to get started</p>
    </div>
    <?php if ($message !== '') { ?>
      <div class="alert <?php echo $success ? 'alert-success' : 'alert-error'; ?>">
        <i class="fas fa-<?php echo $success ? 'check-circle' : 'exclamation-circle'; ?>"></i>
        <?php echo htmlspecialchars($message); ?>
      </div>
    <?php } ?>
    <?php if (!$success) { ?>
    <form method="post" action="register.php">
      <?php echo csrf_field(); ?>
      <div class="form-group">
        <label for="full_name">Full Name</label>
        <input id="full_name" name="full_name" type="text" placeholder="Enter your full name" required value="<?php echo htmlspecialchars($nameValue); ?>">
      </div>
      <div class="form-group">
        <label for="email">University Email</label>
        <input id="email" name="email" type="email" placeholder="you@university.edu" required value="<?php echo htmlspecialchars($emailValue); ?>">
      </div>
      <div class="form-group">
        <label for="password">Password</label>
        <input id="password" name="password" type="password" placeholder="Create a strong password" required>
        <ul class="password-hints" id="passwordHints">
          <li id="hint-length"><i class="fas fa-circle"></i> At least 8 characters</li>
          <li id="hint-upper"><i class="fas fa-circle"></i> One uppercase letter (A-Z)</li>
          <li id="hint-lower"><i class="fas fa-circle"></i> One lowercase letter (a-z)</li>
          <li id="hint-digit"><i class="fas fa-circle"></i> One digit (0-9)</li>
          <li id="hint-special"><i class="fas fa-circle"></i> One special character (!@#$%&hellip;)</li>
        </ul>
      </div>
      <button class="btn btn-primary" type="submit" style="width:100%"><i class="fas fa-user-plus"></i> Create Account</button>
    </form>
    <div class="auth-footer">
      Already have an account? <a href="login.php">Sign in</a>
    </div>
    <?php } else { ?>
    <div class="mt-16 text-center">
      <a href="login.php" class="btn btn-primary"><i class="fas fa-sign-in-alt"></i> Go to Sign In</a>
    </div>
    <?php } ?>
  </div>
  <div class="auth-bottom-footer">&copy; <?php echo date('Y'); ?> University Library Management System. All rights reserved.</div>
  <script src="../../assets/js/app.js"></script>
  <script>
  (function(){
    var pw = document.getElementById('password');
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