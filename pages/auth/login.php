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
$emailValue = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) { $message = 'Invalid form submission. Please try again.'; } else {
    $emailValue = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $attempts = $_SESSION['login_attempts'] ?? ['count' => 0, 'last' => 0];
    if ($attempts['count'] >= 5 && (time() - $attempts['last']) < 900) {
        $message = 'Too many failed attempts. Please try again in 15 minutes.';
    } elseif ($emailValue === '' || $password === '') {
        $message = 'Email and password are required.';
    } else {
      $statement = $pdo->prepare('SELECT u.user_id, u.full_name, u.password_hash, u.is_email_confirmed, u.status, r.role_name FROM users u INNER JOIN roles r ON r.role_id = u.role_id WHERE u.email = ? LIMIT 1');
        $statement->execute([$emailValue]);
        $user = $statement->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $attempts = $_SESSION['login_attempts'] ?? ['count' => 0, 'last' => 0];
            $_SESSION['login_attempts'] = ['count' => $attempts['count'] + 1, 'last' => time()];
            $message = 'Invalid email or password.';
        } elseif ($user['status'] !== 'active') {
            $message = 'Your account is inactive. Please contact the library administrator.';
        } elseif ((int)$user['is_email_confirmed'] !== 1) {
            $message = 'Please confirm your email before logging in.';
        } else {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role_name'] = $user['role_name'] ?? 'student';
            unset($_SESSION['login_attempts']);
            if (($_SESSION['role_name'] ?? '') === 'admin') {
              header('Location: ../admin/dashboard.php');
            } else {
              header('Location: ../dashboard/student-dashboard.php');
            }
            exit;
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
  <title>Sign In - UniLibrary</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="auth-layout">
  <nav class="auth-topnav">
    <a href="../../index.php" class="auth-topnav-brand"><i class="fas fa-book-open"></i> UniLibrary</a>
    <div class="auth-topnav-links">
      <a href="../../index.php"><i class="fas fa-home"></i> Home</a>
      <a href="register.php"><i class="fas fa-user-plus"></i> Register</a>
    </div>
  </nav>
  <div class="auth-card">
    <div class="auth-brand">
      <div class="auth-logo"><i class="fas fa-book-open"></i></div>
      <h1>Welcome Back</h1>
      <p>Sign in to your library account</p>
    </div>
    <?php if ($message !== '') { ?>
      <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($message); ?></div>
    <?php } ?>
    <form method="post" action="login.php">
      <?php echo csrf_field(); ?>
      <div class="form-group">
        <label for="email">Email Address</label>
        <input id="email" name="email" type="email" placeholder="you@university.edu" required value="<?php echo htmlspecialchars($emailValue); ?>">
      </div>
      <div class="form-group">
        <label for="password">Password</label>
        <input id="password" name="password" type="password" placeholder="Enter your password" required>
      </div>
      <button class="btn btn-primary" type="submit" style="width:100%"><i class="fas fa-sign-in-alt"></i> Sign In</button>
    </form>
    <div class="auth-footer">
      Don't have an account? <a href="register.php">Create one now</a>
    </div>
  </div>

  <div class="demo-credentials-card">
    <div class="demo-credentials-header">
      <i class="fas fa-key"></i> Demo Login Credentials
    </div>
    <div class="demo-credentials-body">
      <div class="demo-cred-section">
        <span class="demo-cred-badge admin">Admin</span>
        <div class="demo-cred-detail"><strong>Email:</strong> admin@admin.com</div>
        <div class="demo-cred-detail"><strong>Password:</strong> admin1234</div>
      </div>
      <hr class="demo-cred-divider">
      <div class="demo-cred-section">
        <span class="demo-cred-badge student">Students</span>
        <table class="demo-cred-table">
          <thead><tr><th>Name</th><th>Email</th><th>Password</th></tr></thead>
          <tbody>
            <tr><td>Student 1</td><td>student1@university.edu</td><td>Student@1</td></tr>
            <tr><td>Student 2</td><td>student2@university.edu</td><td>Student@2</td></tr>
            <tr><td>Student 3</td><td>student3@university.edu</td><td>Student@3</td></tr>
            <tr><td>Student 4</td><td>student4@university.edu</td><td>Student@4</td></tr>
            <tr><td>Student 5</td><td>student5@university.edu</td><td>Student@5</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="auth-bottom-footer">&copy; <?php echo date('Y'); ?> University Library Management System. All rights reserved.</div>
  <script src="../../assets/js/app.js"></script>
</body>
</html>