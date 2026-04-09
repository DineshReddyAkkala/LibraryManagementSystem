<?php
require_once dirname(__DIR__, 2) . '/config/bootstrap.php';
require_login();
if (is_admin()) {
  header('Location: ../admin/dashboard.php');
  exit;
}
$name = current_user_name();
$userId = (int)$_SESSION['user_id'];
$basePath = '../..';
$currentPage = 'dashboard';
$pageTitle = 'Student Dashboard';

$borrowedCountStatement = $pdo->prepare('SELECT COUNT(*) FROM borrowings WHERE user_id = ? AND return_date IS NULL');
$borrowedCountStatement->execute([$userId]);
$borrowedCount = (int)$borrowedCountStatement->fetchColumn();

$dueSoonStatement = $pdo->prepare('SELECT COUNT(*) FROM borrowings WHERE user_id = ? AND return_date IS NULL AND due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)');
$dueSoonStatement->execute([$userId]);
$dueSoonCount = (int)$dueSoonStatement->fetchColumn();

$activeReservationStatement = $pdo->prepare('SELECT COUNT(*) FROM reservations WHERE user_id = ? AND status = ?');
$activeReservationStatement->execute([$userId, 'active']);
$activeReservations = (int)$activeReservationStatement->fetchColumn();

$overdueStatement = $pdo->prepare('SELECT COUNT(*) FROM borrowings WHERE user_id = ? AND return_date IS NULL AND due_date < CURDATE()');
$overdueStatement->execute([$userId]);
$overdueCount = (int)$overdueStatement->fetchColumn();

$unpaidFinesStatement = $pdo->prepare(
  'SELECT COALESCE(SUM(f.amount), 0) FROM fines f
   INNER JOIN borrowings b ON b.borrowing_id = f.borrowing_id
   WHERE b.user_id = ? AND f.paid_status = ?'
);
$unpaidFinesStatement->execute([$userId, 'unpaid']);
$unpaidFinesTotal = number_format((float)$unpaidFinesStatement->fetchColumn(), 2);

/*
 * Recommendation engine (Search-history-first + borrowing history fallback)
 */
$searchCatStatement = $pdo->prepare(
  'SELECT DISTINCT sh.category_id
   FROM search_history sh
   WHERE sh.user_id = ? AND sh.category_id IS NOT NULL
   ORDER BY sh.searched_at DESC
   LIMIT 10'
);
$searchCatStatement->execute([$userId]);
$searchCatIds = $searchCatStatement->fetchAll(PDO::FETCH_COLUMN, 0);

$searchKwStatement = $pdo->prepare(
  'SELECT DISTINCT sh.search_keyword
   FROM search_history sh
   WHERE sh.user_id = ? AND sh.search_keyword IS NOT NULL AND sh.search_keyword != \'\'
   ORDER BY sh.searched_at DESC
   LIMIT 10'
);
$searchKwStatement->execute([$userId]);
$keywords = $searchKwStatement->fetchAll(PDO::FETCH_COLUMN, 0);

$keywordCatIds = [];
if ($keywords) {
  $likeConditions = [];
  $likeParams = [];
  foreach ($keywords as $kw) {
    $likeConditions[] = 'b.title LIKE ?';
    $likeConditions[] = 'a.author_name LIKE ?';
    $likeParams[] = '%' . $kw . '%';
    $likeParams[] = '%' . $kw . '%';
  }
  $kwSql = 'SELECT DISTINCT bc.category_id
            FROM books b
            LEFT JOIN book_authors ba ON b.book_id = ba.book_id
            LEFT JOIN authors a ON ba.author_id = a.author_id
            INNER JOIN book_categories bc ON b.book_id = bc.book_id
            WHERE ' . implode(' OR ', $likeConditions);
  $kwStmt = $pdo->prepare($kwSql);
  $kwStmt->execute($likeParams);
  $keywordCatIds = $kwStmt->fetchAll(PDO::FETCH_COLUMN, 0);
}

$borrowCatStatement = $pdo->prepare(
  'SELECT DISTINCT bc.category_id
   FROM borrow_history bh
   INNER JOIN book_categories bc ON bh.book_id = bc.book_id
   WHERE bh.user_id = ?
   ORDER BY bh.borrowed_at DESC
   LIMIT 5'
);
$borrowCatStatement->execute([$userId]);
$borrowCatIds = $borrowCatStatement->fetchAll(PDO::FETCH_COLUMN, 0);

$mergedCatIds = array_values(array_unique(array_merge($searchCatIds, $keywordCatIds, $borrowCatIds)));

$recommendations = [];
if ($mergedCatIds) {
  $catPlaceholders = implode(',', array_fill(0, count($mergedCatIds), '?'));
  $recSql = "SELECT DISTINCT b.book_id, b.title, b.available_copies, b.publish_year
             FROM books b
             INNER JOIN book_categories bc ON b.book_id = bc.book_id
             WHERE bc.category_id IN ($catPlaceholders)
               AND b.available_copies > 0
               AND b.book_id NOT IN (
                 SELECT br.book_id FROM borrowings br
                 WHERE br.user_id = ? AND br.return_date IS NULL
               )
             ORDER BY b.publish_year DESC, b.title ASC
             LIMIT 3";
  $recStmt = $pdo->prepare($recSql);
  $recStmt->execute(array_merge($mergedCatIds, [$userId]));
  $recommendations = $recStmt->fetchAll();
}

if (!$recommendations) {
  $fallbackStatement = $pdo->query('SELECT book_id, title, available_copies, publish_year FROM books WHERE available_copies > 0 ORDER BY publish_year DESC, title ASC LIMIT 3');
  $recommendations = $fallbackStatement->fetchAll();
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
  <?php include dirname(__DIR__, 2) . '/partials/sidebar-student.php'; ?>
  <div class="main-content">
    <?php include dirname(__DIR__, 2) . '/partials/topbar.php'; ?>
    <div class="content-area">

      <div class="kpi-grid mb-24">
        <div class="card kpi-card">
          <div class="kpi-icon blue"><i class="fas fa-book"></i></div>
          <div class="kpi-data">
            <div class="kpi-label">Borrowed Books</div>
            <div class="kpi-value"><?php echo $borrowedCount; ?></div>
            <div class="kpi-sub">Due in 7 days: <?php echo $dueSoonCount; ?></div>
          </div>
        </div>
        <div class="card kpi-card">
          <div class="kpi-icon amber"><i class="fas fa-bookmark"></i></div>
          <div class="kpi-data">
            <div class="kpi-label">Active Reservations</div>
            <div class="kpi-value"><?php echo $activeReservations; ?></div>
            <div class="kpi-sub">Track in My Reservations</div>
          </div>
        </div>
        <div class="card kpi-card">
          <div class="kpi-icon red"><i class="fas fa-exclamation-triangle"></i></div>
          <div class="kpi-data">
            <div class="kpi-label">Overdue Books</div>
            <div class="kpi-value"><?php echo $overdueCount; ?></div>
            <div class="kpi-sub">Return soon to avoid fines</div>
          </div>
        </div>
        <div class="card kpi-card">
          <div class="kpi-icon <?php echo $unpaidFinesTotal > 0 ? 'red' : 'green'; ?>"><i class="fas fa-sterling-sign"></i></div>
          <div class="kpi-data">
            <div class="kpi-label">Outstanding Fines</div>
            <div class="kpi-value">&pound;<?php echo $unpaidFinesTotal; ?></div>
            <div class="kpi-sub"><?php echo $unpaidFinesTotal > 0 ? 'Pay to clear your record' : 'All clear!'; ?></div>
          </div>
        </div>
      </div>

      <div class="card mb-24">
        <div class="card-header">
          <div>
            <h2>Quick Access</h2>
            <p>Navigate to key features</p>
          </div>
        </div>
        <div class="tile-grid">
          <div class="card tile" onclick="goTo('../books/search-books.php')">
            <div class="tile-icon kpi-icon blue"><i class="fas fa-search"></i></div>
            <div class="tile-body">
              <div class="tile-title">Search Books</div>
              <div class="tile-sub">Explore catalogue and borrow</div>
            </div>
          </div>
          <div class="card tile" onclick="goTo('../account/my-borrowings.php')">
            <div class="tile-icon kpi-icon green"><i class="fas fa-book-reader"></i></div>
            <div class="tile-body">
              <div class="tile-title">My Borrowings</div>
              <div class="tile-sub">View items and due dates</div>
            </div>
          </div>
          <div class="card tile" onclick="goTo('../account/notifications.php')">
            <div class="tile-icon kpi-icon amber"><i class="fas fa-bell"></i></div>
            <div class="tile-body">
              <div class="tile-title">Notifications</div>
              <div class="tile-sub">Alerts and updates</div>
            </div>
          </div>
          <div class="card tile" onclick="goTo('../account/my-reservations.php')">
            <div class="tile-icon kpi-icon cyan"><i class="fas fa-bookmark"></i></div>
            <div class="tile-body">
              <div class="tile-title">My Reservations</div>
              <div class="tile-sub">Manage queue positions</div>
            </div>
          </div>
          <div class="card tile" onclick="goTo('../account/my-fines.php')">
            <div class="tile-icon kpi-icon red"><i class="fas fa-receipt"></i></div>
            <div class="tile-body">
              <div class="tile-title">My Fines</div>
              <div class="tile-sub">Track penalties</div>
            </div>
          </div>
          <div class="card tile" onclick="goTo('../account/my-profile.php')">
            <div class="tile-icon kpi-icon blue"><i class="fas fa-user-cog"></i></div>
            <div class="tile-body">
              <div class="tile-title">My Profile</div>
              <div class="tile-sub">Account settings</div>
            </div>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="section-header">
          <h3><i class="fas fa-lightbulb" style="color:var(--warning);margin-right:8px"></i>Recommended for You</h3>
        </div>
        <div class="recommendation-grid">
          <?php if (!$recommendations) { ?>
            <div class="empty-state">
              <i class="fas fa-book-open"></i>
              <p>No recommendations yet. Search or borrow books to personalise this section.</p>
            </div>
          <?php } ?>
          <?php foreach ($recommendations as $book) { ?>
            <div class="card rec-card" onclick="goTo('../books/book-details.php?book_id=<?php echo (int)$book['book_id']; ?>')">
              <div class="rec-title"><?php echo htmlspecialchars($book['title']); ?></div>
              <div class="rec-meta"><i class="fas fa-calendar-alt"></i> Published: <?php echo htmlspecialchars((string)$book['publish_year']); ?></div>
              <div class="rec-meta"><i class="fas fa-copy"></i> Available: <?php echo htmlspecialchars((string)$book['available_copies']); ?> copies</div>
            </div>
          <?php } ?>
        </div>
      </div>

    </div>
    <?php include dirname(__DIR__, 2) . '/partials/footer.php'; ?>
  </div>
  <script src="<?php echo $basePath; ?>/assets/js/app.js"></script>
</body>
</html>