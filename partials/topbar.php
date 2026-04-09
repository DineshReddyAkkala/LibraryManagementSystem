<header class="topbar">
  <div class="topbar-left">
    <button class="hamburger" onclick="toggleSidebar()" aria-label="Toggle navigation"><i class="fas fa-bars"></i></button>
    <div class="page-title"><?php echo htmlspecialchars($pageTitle ?? 'Dashboard'); ?></div>
  </div>
  <div class="topbar-right">
    <a href="<?php echo $basePath; ?>/pages/auth/logout.php" class="topbar-btn"><i class="fas fa-sign-out-alt"></i> Sign Out</a>
  </div>
</header>
