<?php
$_unreadNotifCount = 0;
if (!empty($_SESSION['user_id'])) {
  $_nStmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
  $_nStmt->execute([(int)$_SESSION['user_id']]);
  $_unreadNotifCount = (int)$_nStmt->fetchColumn();
}
$_userName = htmlspecialchars($_SESSION['full_name'] ?? 'Student');
$_userInitials = '';
foreach (explode(' ', $_SESSION['full_name'] ?? 'S') as $_w) {
  $_userInitials .= mb_strtoupper(mb_substr($_w, 0, 1));
}
$_userInitials = mb_substr($_userInitials, 0, 2);
?>
<div class="sidebar-overlay" id="sidebar-overlay"></div>
<aside class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <div class="sidebar-logo"><i class="fas fa-book-open"></i></div>
    <span class="sidebar-title">UniLibrary</span>
  </div>
  <div class="sidebar-section">Main</div>
  <nav class="sidebar-nav">
    <a href="<?php echo $basePath; ?>/pages/dashboard/student-dashboard.php" class="nav-item <?php echo ($currentPage ?? '') === 'dashboard' ? 'active' : ''; ?>">
      <i class="fas fa-th-large"></i><span>Dashboard</span>
    </a>
    <a href="<?php echo $basePath; ?>/pages/books/search-books.php" class="nav-item <?php echo ($currentPage ?? '') === 'search' ? 'active' : ''; ?>">
      <i class="fas fa-search"></i><span>Search Books</span>
    </a>
  </nav>
  <div class="sidebar-section">My Account</div>
  <nav class="sidebar-nav">
    <a href="<?php echo $basePath; ?>/pages/account/my-borrowings.php" class="nav-item <?php echo ($currentPage ?? '') === 'borrowings' ? 'active' : ''; ?>">
      <i class="fas fa-book"></i><span>My Borrowings</span>
    </a>
    <a href="<?php echo $basePath; ?>/pages/account/my-reservations.php" class="nav-item <?php echo ($currentPage ?? '') === 'reservations' ? 'active' : ''; ?>">
      <i class="fas fa-bookmark"></i><span>My Reservations</span>
    </a>
    <a href="<?php echo $basePath; ?>/pages/account/notifications.php" class="nav-item <?php echo ($currentPage ?? '') === 'notifications' ? 'active' : ''; ?>">
      <i class="fas fa-bell"></i><span>Notifications</span>
      <?php if ($_unreadNotifCount > 0) { ?>
        <span class="nav-badge"><?php echo $_unreadNotifCount; ?></span>
      <?php } ?>
    </a>
    <a href="<?php echo $basePath; ?>/pages/account/my-fines.php" class="nav-item <?php echo ($currentPage ?? '') === 'fines' ? 'active' : ''; ?>">
      <i class="fas fa-receipt"></i><span>My Fines</span>
    </a>
    <a href="<?php echo $basePath; ?>/pages/account/my-profile.php" class="nav-item <?php echo ($currentPage ?? '') === 'profile' ? 'active' : ''; ?>">
      <i class="fas fa-user-circle"></i><span>My Profile</span>
    </a>
  </nav>
  <div class="sidebar-footer">
    <div class="sidebar-user">
      <div class="user-avatar"><?php echo $_userInitials; ?></div>
      <div class="user-info">
        <div class="user-name"><?php echo $_userName; ?></div>
        <div class="user-role">Student</div>
      </div>
    </div>
  </div>
</aside>
