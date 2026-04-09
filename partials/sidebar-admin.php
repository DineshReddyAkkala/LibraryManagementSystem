<?php
$_unreadNotifCount = 0;
if (!empty($_SESSION['user_id'])) {
  $_nStmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
  $_nStmt->execute([(int)$_SESSION['user_id']]);
  $_unreadNotifCount = (int)$_nStmt->fetchColumn();
}
$_userName = htmlspecialchars($_SESSION['full_name'] ?? 'Admin');
$_userInitials = '';
foreach (explode(' ', $_SESSION['full_name'] ?? 'A') as $_w) {
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
  <div class="sidebar-section">Administration</div>
  <nav class="sidebar-nav">
    <a href="<?php echo $basePath; ?>/pages/admin/dashboard.php" class="nav-item <?php echo ($currentPage ?? '') === 'dashboard' ? 'active' : ''; ?>">
      <i class="fas fa-tachometer-alt"></i><span>Dashboard</span>
    </a>
    <a href="<?php echo $basePath; ?>/pages/admin/manage-books.php" class="nav-item <?php echo ($currentPage ?? '') === 'manage-books' ? 'active' : ''; ?>">
      <i class="fas fa-book-medical"></i><span>Manage Books</span>
    </a>
    <a href="<?php echo $basePath; ?>/pages/admin/manage-users.php" class="nav-item <?php echo ($currentPage ?? '') === 'manage-users' ? 'active' : ''; ?>">
      <i class="fas fa-users-cog"></i><span>Manage Users</span>
    </a>
    <a href="<?php echo $basePath; ?>/pages/admin/circulation.php" class="nav-item <?php echo ($currentPage ?? '') === 'circulation' ? 'active' : ''; ?>">
      <i class="fas fa-exchange-alt"></i><span>Circulation</span>
    </a>
    <a href="<?php echo $basePath; ?>/pages/admin/manage-fines.php" class="nav-item <?php echo ($currentPage ?? '') === 'manage-fines' ? 'active' : ''; ?>">
      <i class="fas fa-receipt"></i><span>Manage Fines</span>
    </a>
    <a href="<?php echo $basePath; ?>/pages/admin/notifications.php" class="nav-item <?php echo ($currentPage ?? '') === 'notifications' ? 'active' : ''; ?>">
      <i class="fas fa-bell"></i><span>Notifications</span>
      <?php if ($_unreadNotifCount > 0) { ?>
        <span class="nav-badge"><?php echo $_unreadNotifCount; ?></span>
      <?php } ?>
    </a>
  </nav>
  <div class="sidebar-section">Quick Links</div>
  <nav class="sidebar-nav">
    <a href="<?php echo $basePath; ?>/pages/books/search-books.php" class="nav-item <?php echo ($currentPage ?? '') === 'search' ? 'active' : ''; ?>">
      <i class="fas fa-search"></i><span>Search Catalogue</span>
    </a>
  </nav>
  <div class="sidebar-footer">
    <div class="sidebar-user">
      <div class="user-avatar"><?php echo $_userInitials; ?></div>
      <div class="user-info">
        <div class="user-name"><?php echo $_userName; ?></div>
        <div class="user-role">Administrator</div>
      </div>
    </div>
  </div>
</aside>
