<?php
require_once dirname(__DIR__, 2) . '/config/bootstrap.php';

$isLoggedIn = !empty($_SESSION['user_id']);
$basePath = '../..';
$currentPage = 'search';
$pageTitle = 'Book Details';

$bookId = (int)($_GET['book_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isLoggedIn) {
  if (!verify_csrf()) { header('Location: book-details.php?book_id=' . $bookId); exit; }
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

  header('Location: book-details.php?book_id=' . $bookId);
  exit;
}

$flash = get_flash();
$book = null;
$alreadyBorrowed = false;
$alreadyReserved = false;

if ($bookId > 0) {
    $statement = $pdo->prepare('SELECT b.book_id, b.title, b.isbn, b.publish_year, b.available_copies, b.total_copies, p.publisher_name, GROUP_CONCAT(DISTINCT a.author_name SEPARATOR ", ") AS authors, GROUP_CONCAT(DISTINCT c.category_name SEPARATOR ", ") AS categories FROM books b LEFT JOIN publishers p ON b.publisher_id = p.publisher_id LEFT JOIN book_authors ba ON b.book_id = ba.book_id LEFT JOIN authors a ON ba.author_id = a.author_id LEFT JOIN book_categories bc ON b.book_id = bc.book_id LEFT JOIN categories c ON bc.category_id = c.category_id WHERE b.book_id = ? GROUP BY b.book_id');
    $statement->execute([$bookId]);
    $book = $statement->fetch();

    if ($book && $isLoggedIn && !is_admin()) {
        $bStmt = $pdo->prepare('SELECT borrowing_id FROM borrowings WHERE user_id = ? AND book_id = ? AND return_date IS NULL LIMIT 1');
        $bStmt->execute([(int)$_SESSION['user_id'], $bookId]);
        $alreadyBorrowed = (bool)$bStmt->fetch();

        $rStmt = $pdo->prepare('SELECT reservation_id FROM reservations WHERE user_id = ? AND book_id = ? AND status = ? LIMIT 1');
        $rStmt->execute([(int)$_SESSION['user_id'], $bookId, 'active']);
        $alreadyReserved = (bool)$rStmt->fetch();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?php echo $book ? htmlspecialchars($book['title']) : 'Book Details'; ?> - UniLibrary</title>
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
      <a href="search-books.php">Catalogue</a>
      <a href="../auth/login.php">Sign In</a>
      <a href="../auth/register.php">Register</a>
    </div>
  </nav>
  <div class="main-content" style="margin-left:0">
<?php } ?>
    <div class="content-area">
      <?php if (!$book) { ?>
        <div class="card">
          <div class="empty-state">
            <i class="fas fa-search"></i>
            <p>Book not found. Please return to the catalogue.</p>
            <a href="search-books.php" class="btn btn-primary mt-16"><i class="fas fa-arrow-left"></i> Back to Search</a>
          </div>
        </div>
      <?php } else { ?>
        <?php if ($flash) { ?>
          <div class="alert <?php echo $flash['type'] === 'success' ? 'alert-success' : 'alert-error'; ?> mb-24">
            <i class="fas fa-<?php echo $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
            <?php echo htmlspecialchars($flash['message']); ?>
          </div>
        <?php } ?>

        <div class="book-detail-page">
          <!-- Cover Image -->
          <div class="book-cover-area">
            <div class="book-cover-placeholder">
              <i class="fas fa-book"></i>
              <span class="book-cover-title"><?php echo htmlspecialchars($book['title']); ?></span>
              <span class="book-cover-author"><?php echo htmlspecialchars($book['authors'] ?? 'Unknown Author'); ?></span>
            </div>
            <div class="book-availability-badge <?php echo (int)$book['available_copies'] > 0 ? 'avail-yes' : 'avail-no'; ?>">
              <i class="fas fa-<?php echo (int)$book['available_copies'] > 0 ? 'check-circle' : 'times-circle'; ?>"></i>
              <?php echo (int)$book['available_copies'] > 0 ? 'Available' : 'Checked Out'; ?>
            </div>
          </div>

          <!-- Book Info -->
          <div class="book-info-area">
            <div class="card">
              <h2 class="book-detail-title"><?php echo htmlspecialchars($book['title']); ?></h2>
              <div class="book-detail-meta-grid">
                <div class="meta-item">
                  <div class="meta-icon blue"><i class="fas fa-users"></i></div>
                  <div>
                    <div class="meta-label">Authors</div>
                    <div class="meta-value"><?php echo htmlspecialchars($book['authors'] ?? 'Unknown'); ?></div>
                  </div>
                </div>
                <div class="meta-item">
                  <div class="meta-icon green"><i class="fas fa-building"></i></div>
                  <div>
                    <div class="meta-label">Publisher</div>
                    <div class="meta-value"><?php echo htmlspecialchars($book['publisher_name'] ?? 'N/A'); ?></div>
                  </div>
                </div>
                <div class="meta-item">
                  <div class="meta-icon amber"><i class="fas fa-calendar"></i></div>
                  <div>
                    <div class="meta-label">Publication Year</div>
                    <div class="meta-value"><?php echo htmlspecialchars((string)($book['publish_year'] ?? 'N/A')); ?></div>
                  </div>
                </div>
                <div class="meta-item">
                  <div class="meta-icon cyan"><i class="fas fa-barcode"></i></div>
                  <div>
                    <div class="meta-label">ISBN</div>
                    <div class="meta-value"><?php echo htmlspecialchars((string)($book['isbn'] ?? 'N/A')); ?></div>
                  </div>
                </div>
                <div class="meta-item">
                  <div class="meta-icon red"><i class="fas fa-tags"></i></div>
                  <div>
                    <div class="meta-label">Categories</div>
                    <div class="meta-value"><?php echo htmlspecialchars($book['categories'] ?? 'Uncategorised'); ?></div>
                  </div>
                </div>
                <div class="meta-item">
                  <div class="meta-icon <?php echo (int)$book['available_copies'] > 0 ? 'green' : 'red'; ?>"><i class="fas fa-copy"></i></div>
                  <div>
                    <div class="meta-label">Copies</div>
                    <div class="meta-value"><?php echo (int)$book['available_copies']; ?> of <?php echo (int)$book['total_copies']; ?> available</div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Actions Card -->
            <div class="card book-action-card">
              <h3><i class="fas fa-hand-pointer" style="color:var(--primary);margin-right:8px"></i>Actions</h3>
              <div class="book-action-btns">
                <?php if ($isLoggedIn && !is_admin()) { ?>
                  <?php if ($alreadyBorrowed) { ?>
                    <button class="btn btn-secondary" disabled style="width:100%"><i class="fas fa-check"></i> Already Borrowed</button>
                  <?php } elseif ((int)$book['available_copies'] > 0) { ?>
                    <form method="post" action="book-details.php?book_id=<?php echo (int)$book['book_id']; ?>">
                      <?php echo csrf_field(); ?>
                      <input type="hidden" name="action" value="borrow">
                      <input type="hidden" name="book_id" value="<?php echo (int)$book['book_id']; ?>">
                      <button class="btn btn-primary" type="submit" style="width:100%">
                        <i class="fas fa-hand-holding"></i> Borrow This Book
                      </button>
                    </form>
                  <?php } else { ?>
                    <?php if ($alreadyReserved) { ?>
                      <button class="btn btn-secondary" disabled style="width:100%"><i class="fas fa-clock"></i> Already Reserved</button>
                    <?php } else { ?>
                      <form method="post" action="book-details.php?book_id=<?php echo (int)$book['book_id']; ?>">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="reserve">
                        <input type="hidden" name="book_id" value="<?php echo (int)$book['book_id']; ?>">
                        <button class="btn btn-secondary" type="submit" style="width:100%"><i class="fas fa-bookmark"></i> Reserve This Book</button>
                      </form>
                    <?php } ?>
                  <?php } ?>
                <?php } elseif (!$isLoggedIn) { ?>
                  <a href="../auth/login.php" class="btn btn-primary" style="width:100%;text-align:center"><i class="fas fa-sign-in-alt"></i> Login to Borrow</a>
                <?php } ?>
                <a href="search-books.php" class="btn btn-ghost" style="width:100%;text-align:center"><i class="fas fa-arrow-left"></i> Back to Catalogue</a>
              </div>
            </div>
          </div>
        </div>
      <?php } ?>
    </div>
    <?php include dirname(__DIR__, 2) . '/partials/footer.php'; ?>
  </div>
  <script src="<?php echo $basePath; ?>/assets/js/app.js"></script>
</body>
</html>