<?php
require_once dirname(__DIR__, 2) . '/config/bootstrap.php';

$query = trim($_GET['q'] ?? '');
$category = $_GET['category'] ?? 'all';
$isLoggedIn = !empty($_SESSION['user_id']);
$basePath = '../..';
$currentPage = 'search';
$pageTitle = 'Search Catalogue';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isLoggedIn) {
  if (!verify_csrf()) { header('Location: search-books.php'); exit; }
  $action = $_POST['action'] ?? '';
  $bookId = (int)($_POST['book_id'] ?? 0);

  if ($bookId > 0) {
    if ($action === 'borrow') {
      $result = borrow_book($pdo, (int)$_SESSION['user_id'], $bookId);
      set_flash($result['ok'] ? 'success' : 'error', $result['message']);
    } elseif ($action === 'reserve') {
      $result = reserve_book($pdo, (int)$_SESSION['user_id'], $bookId);
      set_flash($result['ok'] ? 'success' : 'error', $result['message']);
    }
  }

  $redirectUrl = 'search-books.php?q=' . urlencode($query) . '&category=' . urlencode((string)$category);
  header('Location: ' . $redirectUrl);
  exit;
}

$flash = get_flash();

$categories = $pdo->query('SELECT category_id, category_name FROM categories ORDER BY category_name')->fetchAll();

/* Log search to search_history for logged-in students */
if ($isLoggedIn && !is_admin() && ($query !== '' || $category !== 'all')) {
  $logSearch = $pdo->prepare('INSERT INTO search_history (user_id, search_keyword, category_id) VALUES (?, ?, ?)');
  $logSearch->execute([
    (int)$_SESSION['user_id'],
    $query !== '' ? $query : null,
    $category !== 'all' ? (int)$category : null,
  ]);
}

$sql = 'SELECT b.book_id, b.title, b.available_copies, b.total_copies, GROUP_CONCAT(DISTINCT a.author_name SEPARATOR ", ") AS authors, GROUP_CONCAT(DISTINCT c.category_name SEPARATOR ", ") AS categories FROM books b LEFT JOIN book_authors ba ON b.book_id = ba.book_id LEFT JOIN authors a ON ba.author_id = a.author_id LEFT JOIN book_categories bc ON b.book_id = bc.book_id LEFT JOIN categories c ON bc.category_id = c.category_id WHERE 1=1';
$params = [];

if ($query !== '') {
    $sql .= ' AND (b.title LIKE ? OR a.author_name LIKE ? OR c.category_name LIKE ?)';
    $likeQuery = '%' . $query . '%';
    $params[] = $likeQuery;
    $params[] = $likeQuery;
    $params[] = $likeQuery;
}

if ($category !== 'all') {
    $sql .= ' AND c.category_id = ?';
    $params[] = $category;
}

$sql .= ' GROUP BY b.book_id ORDER BY (b.available_copies > 0) DESC, b.title';

$statement = $pdo->prepare($sql);
$statement->execute($params);
$books = $statement->fetchAll();
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
<?php if ($isLoggedIn) { ?>
<body class="app-layout">
  <?php
    if (is_admin()) {
      include dirname(__DIR__, 2) . '/partials/sidebar-admin.php';
    } else {
      include dirname(__DIR__, 2) . '/partials/sidebar-student.php';
    }
  ?>
  <div class="main-content">
    <?php include dirname(__DIR__, 2) . '/partials/topbar.php'; ?>
<?php } else { ?>
<body>
  <nav class="public-topbar">
    <a href="<?php echo $basePath; ?>/index.php" class="pub-brand">
      <div class="pub-logo"><i class="fas fa-book-open"></i></div>
      <span>UniLibrary</span>
    </a>
    <div class="pub-nav">
      <a href="search-books.php" class="active">Catalogue</a>
      <a href="../auth/login.php">Sign In</a>
      <a href="../auth/register.php">Register</a>
    </div>
  </nav>
  <div class="main-content" style="margin-left:0">
<?php } ?>
    <div class="content-area">
      <div class="card">
        <div class="card-header">
          <div>
            <h2><i class="fas fa-search" style="color:var(--primary);margin-right:8px"></i>Search Catalogue</h2>
            <p>Find books by title, author, or category</p>
          </div>
        </div>

        <form class="search-form mb-16" method="get" action="search-books.php">
          <input name="q" type="text" placeholder="Search by title, author, or keyword..." value="<?php echo htmlspecialchars($query); ?>">
          <select name="category">
            <option value="all" <?php echo $category === 'all' ? 'selected' : ''; ?>>All Categories</option>
            <?php foreach ($categories as $cat) { ?>
              <option value="<?php echo (int)$cat['category_id']; ?>" <?php echo (string)$cat['category_id'] === (string)$category ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat['category_name']); ?></option>
            <?php } ?>
          </select>
          <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i> Search</button>
        </form>

        <?php if ($flash) { ?>
          <div class="alert <?php echo $flash['type'] === 'success' ? 'alert-success' : 'alert-error'; ?>">
            <i class="fas fa-<?php echo $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
            <?php echo htmlspecialchars($flash['message']); ?>
          </div>
        <?php } ?>

        <?php if ($query !== '' || $category !== 'all') { ?>
          <div class="text-sm text-muted mb-16">Found <?php echo count($books); ?> result<?php echo count($books) !== 1 ? 's' : ''; ?></div>
        <?php } ?>

        <div class="book-grid">
          <?php if (!$books) { ?>
            <div class="empty-state">
              <i class="fas fa-book-open"></i>
              <p>No books match your search criteria.</p>
            </div>
          <?php } ?>
          <?php foreach ($books as $book) { ?>
            <?php $available = (int)$book['available_copies'] > 0; ?>
            <div class="card book-card<?php echo !$available ? ' book-card-unavailable' : ''; ?>">
              <div class="book-title"><?php echo htmlspecialchars($book['title']); ?></div>
              <div class="book-meta"><i class="fas fa-user"></i> <?php echo htmlspecialchars($book['authors'] ?? 'Unknown'); ?></div>
              <div class="book-meta"><i class="fas fa-tag"></i> <?php echo htmlspecialchars($book['categories'] ?? 'Uncategorised'); ?></div>
              <div class="flex items-center gap-8">
                <span class="badge <?php echo $available ? 'badge-success' : 'badge-danger'; ?>">
                  <span class="badge-dot"></span>
                  <?php echo $available ? 'Available' : 'Checked Out'; ?>
                </span>
                <span class="text-xs text-muted"><?php echo (int)$book['available_copies']; ?>/<?php echo (int)$book['total_copies']; ?> copies</span>
              </div>
              <div class="book-actions">
                <?php if ($isLoggedIn && !is_admin()) { ?>
                  <?php if ($available) { ?>
                    <form method="post" action="search-books.php?q=<?php echo urlencode($query); ?>&category=<?php echo urlencode((string)$category); ?>">
                      <?php echo csrf_field(); ?>
                      <input type="hidden" name="action" value="borrow">
                      <input type="hidden" name="book_id" value="<?php echo (int)$book['book_id']; ?>">
                      <button class="btn btn-primary btn-sm" type="submit"><i class="fas fa-hand-holding"></i> Borrow</button>
                    </form>
                  <?php } else { ?>
                    <form method="post" action="search-books.php?q=<?php echo urlencode($query); ?>&category=<?php echo urlencode((string)$category); ?>">
                      <?php echo csrf_field(); ?>
                      <input type="hidden" name="action" value="reserve">
                      <input type="hidden" name="book_id" value="<?php echo (int)$book['book_id']; ?>">
                      <button class="btn-ghost btn-sm" type="submit"><i class="fas fa-bookmark"></i> Reserve</button>
                    </form>
                  <?php } ?>
                <?php } elseif (!$isLoggedIn) { ?>
                  <a href="../auth/login.php" class="btn btn-primary btn-sm"><i class="fas fa-sign-in-alt"></i> Login to Borrow</a>
                <?php } ?>
                <button class="btn-ghost btn-sm" onclick="goTo('book-details.php?book_id=<?php echo (int)$book['book_id']; ?>')"><i class="fas fa-info-circle"></i> Details</button>
              </div>
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