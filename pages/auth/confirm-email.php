<?php
session_start();
require_once dirname(__DIR__, 2) . '/config/db.php';

$message = '';
$success = false;
$token = trim($_GET['token'] ?? '');

if ($token !== '') {
    $statement = $pdo->prepare('SELECT user_id FROM users WHERE confirmation_token = ? AND is_email_confirmed = 0 LIMIT 1');
    $statement->execute([$token]);
    $user = $statement->fetch();

    if ($user) {
        $update = $pdo->prepare('UPDATE users SET is_email_confirmed = 1, confirmation_token = NULL, status = ? WHERE user_id = ?');
        $update->execute(['active', (int)$user['user_id']]);
        $success = true;
        $message = 'Your email has been confirmed! You can now sign in.';
    } else {
        $message = 'Invalid or expired confirmation link.';
    }
} else {
    $message = 'No confirmation token provided.';
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Confirm Email - UniLibrary</title>
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
      <div class="auth-logo"><i class="fas fa-envelope-open-text"></i></div>
      <h1>Email Confirmation</h1>
    </div>
    <div class="alert <?php echo $success ? 'alert-success' : 'alert-error'; ?>">
      <i class="fas fa-<?php echo $success ? 'check-circle' : 'times-circle'; ?>"></i>
      <?php echo htmlspecialchars($message); ?>
    </div>
    <div class="text-center mt-16">
      <a href="login.php" class="btn btn-primary"><i class="fas fa-sign-in-alt"></i> Go to Sign In</a>
    </div>
  </div>
  <div class="auth-bottom-footer">&copy; <?php echo date('Y'); ?> University Library Management System. All rights reserved.</div>
  <script src="../../assets/js/app.js"></script>
</body>
</html>