<?php
require_once dirname(__DIR__, 2) . '/config/bootstrap.php';
require_admin();
$basePath = '../..';
$currentPage = 'manage-books';
$pageTitle = 'Manage Books';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) { header('Location: manage-books.php'); exit; }
    $action = $_POST['action'] ?? '';

    /* -- Helper: create new publisher/authors/categories if provided -- */
    $newPublisher = trim($_POST['new_publisher'] ?? '');
    $newAuthors   = trim($_POST['new_authors'] ?? '');
    $newCategory  = trim($_POST['new_category'] ?? '');

    if ($action === 'add') {
        $title = trim($_POST['title'] ?? '');
        $isbn = trim($_POST['isbn'] ?? '');
        $publishYear = (int)($_POST['publish_year'] ?? 0);
        $totalCopies = (int)($_POST['total_copies'] ?? 0);
        $publisherId = (int)($_POST['publisher_id'] ?? 0);
        $authorIds = $_POST['author_ids'] ?? [];
        $categoryIds = $_POST['category_ids'] ?? [];

        // Create new publisher if provided
        if ($newPublisher !== '') {
            $pdo->prepare('INSERT IGNORE INTO publishers (publisher_name) VALUES (?)')->execute([$newPublisher]);
            $pubStmt = $pdo->prepare('SELECT publisher_id FROM publishers WHERE publisher_name = ? LIMIT 1');
            $pubStmt->execute([$newPublisher]);
            $publisherId = (int)$pubStmt->fetchColumn();
        }

        // Create new authors if provided (comma-separated)
        if ($newAuthors !== '') {
            foreach (explode(',', $newAuthors) as $aName) {
                $aName = trim($aName);
                if ($aName === '') continue;
                $pdo->prepare('INSERT IGNORE INTO authors (author_name) VALUES (?)')->execute([$aName]);
                $aStmt = $pdo->prepare('SELECT author_id FROM authors WHERE author_name = ? LIMIT 1');
                $aStmt->execute([$aName]);
                $authorIds[] = (string)$aStmt->fetchColumn();
            }
        }

        // Create new category if provided
        if ($newCategory !== '') {
            $pdo->prepare('INSERT IGNORE INTO categories (category_name) VALUES (?)')->execute([$newCategory]);
            $catStmt = $pdo->prepare('SELECT category_id FROM categories WHERE category_name = ? LIMIT 1');
            $catStmt->execute([$newCategory]);
            $categoryIds[] = (string)$catStmt->fetchColumn();
        }

        if ($title === '' || $totalCopies < 1 || $publisherId < 1) {
            set_flash('error', 'Title, publisher, and copies are required.');
        } else {
            $pdo->beginTransaction();
            $insert = $pdo->prepare('INSERT INTO books (title, isbn, publisher_id, publish_year, total_copies, available_copies) VALUES (?, ?, ?, ?, ?, ?)');
            $insert->execute([$title, $isbn !== '' ? $isbn : null, $publisherId, $publishYear > 0 ? $publishYear : null, $totalCopies, $totalCopies]);
            $newBookId = (int)$pdo->lastInsertId();

            foreach ($authorIds as $aid) {
                $aid = (int)$aid;
                if ($aid > 0) {
                    $pdo->prepare('INSERT INTO book_authors (book_id, author_id) VALUES (?, ?)')->execute([$newBookId, $aid]);
                }
            }
            foreach ($categoryIds as $cid) {
                $cid = (int)$cid;
                if ($cid > 0) {
                    $pdo->prepare('INSERT INTO book_categories (book_id, category_id) VALUES (?, ?)')->execute([$newBookId, $cid]);
                }
            }
            $pdo->commit();
            set_flash('success', 'Book added to catalogue.');
        }
    }

    if ($action === 'edit') {
        $bookId = (int)($_POST['book_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $isbn = trim($_POST['isbn'] ?? '');
        $publishYear = (int)($_POST['publish_year'] ?? 0);
        $totalCopies = (int)($_POST['total_copies'] ?? 0);
        $availableCopies = (int)($_POST['available_copies'] ?? 0);
        $publisherId = (int)($_POST['publisher_id'] ?? 0);
        $authorIds = $_POST['author_ids'] ?? [];
        $categoryIds = $_POST['category_ids'] ?? [];

        // Create new publisher if provided
        if ($newPublisher !== '') {
            $pdo->prepare('INSERT IGNORE INTO publishers (publisher_name) VALUES (?)')->execute([$newPublisher]);
            $pubStmt = $pdo->prepare('SELECT publisher_id FROM publishers WHERE publisher_name = ? LIMIT 1');
            $pubStmt->execute([$newPublisher]);
            $publisherId = (int)$pubStmt->fetchColumn();
        }

        // Create new authors if provided (comma-separated)
        if ($newAuthors !== '') {
            foreach (explode(',', $newAuthors) as $aName) {
                $aName = trim($aName);
                if ($aName === '') continue;
                $pdo->prepare('INSERT IGNORE INTO authors (author_name) VALUES (?)')->execute([$aName]);
                $aStmt = $pdo->prepare('SELECT author_id FROM authors WHERE author_name = ? LIMIT 1');
                $aStmt->execute([$aName]);
                $authorIds[] = (string)$aStmt->fetchColumn();
            }
        }

        // Create new category if provided
        if ($newCategory !== '') {
            $pdo->prepare('INSERT IGNORE INTO categories (category_name) VALUES (?)')->execute([$newCategory]);
            $catStmt = $pdo->prepare('SELECT category_id FROM categories WHERE category_name = ? LIMIT 1');
            $catStmt->execute([$newCategory]);
            $categoryIds[] = (string)$catStmt->fetchColumn();
        }

        if ($bookId < 1 || $title === '' || $publisherId < 1) {
            set_flash('error', 'Title and publisher are required.');
        } elseif ($availableCopies > $totalCopies) {
            set_flash('error', 'Available copies cannot exceed total copies.');
        } else {
            $pdo->beginTransaction();
            $update = $pdo->prepare('UPDATE books SET title = ?, isbn = ?, publisher_id = ?, publish_year = ?, total_copies = ?, available_copies = ? WHERE book_id = ?');
            $update->execute([$title, $isbn !== '' ? $isbn : null, $publisherId, $publishYear > 0 ? $publishYear : null, $totalCopies, $availableCopies, $bookId]);

            $pdo->prepare('DELETE FROM book_authors WHERE book_id = ?')->execute([$bookId]);
            foreach ($authorIds as $aid) {
                $aid = (int)$aid;
                if ($aid > 0) {
                    $pdo->prepare('INSERT INTO book_authors (book_id, author_id) VALUES (?, ?)')->execute([$bookId, $aid]);
                }
            }

            $pdo->prepare('DELETE FROM book_categories WHERE book_id = ?')->execute([$bookId]);
            foreach ($categoryIds as $cid) {
                $cid = (int)$cid;
                if ($cid > 0) {
                    $pdo->prepare('INSERT INTO book_categories (book_id, category_id) VALUES (?, ?)')->execute([$bookId, $cid]);
                }
            }
            $pdo->commit();
            set_flash('success', 'Book updated successfully.');
        }
    }

    if ($action === 'delete') {
        $bookId = (int)($_POST['book_id'] ?? 0);
        if ($bookId > 0) {
            $activeBorrowings = $pdo->prepare('SELECT COUNT(*) FROM borrowings WHERE book_id = ? AND return_date IS NULL');
            $activeBorrowings->execute([$bookId]);
            if ((int)$activeBorrowings->fetchColumn() > 0) {
                set_flash('error', 'Cannot delete a book with active borrowings.');
            } else {
                $pdo->beginTransaction();
                $pdo->prepare('DELETE FROM book_authors WHERE book_id = ?')->execute([$bookId]);
                $pdo->prepare('DELETE FROM book_categories WHERE book_id = ?')->execute([$bookId]);
                $pdo->prepare("DELETE FROM reservations WHERE book_id = ? AND status = 'active'")->execute([$bookId]);
                $pdo->prepare('DELETE FROM books WHERE book_id = ?')->execute([$bookId]);
                $pdo->commit();
                set_flash('success', 'Book deleted from catalogue.');
            }
        }
    }

    header('Location: manage-books.php');
    exit;
}

$flash = get_flash();
$publishers = $pdo->query('SELECT publisher_id, publisher_name FROM publishers ORDER BY publisher_name')->fetchAll();
$authors = $pdo->query('SELECT author_id, author_name FROM authors ORDER BY author_name')->fetchAll();
$categories = $pdo->query('SELECT category_id, category_name FROM categories ORDER BY category_name')->fetchAll();
$books = $pdo->query('SELECT b.book_id, b.title, b.isbn, b.publish_year, b.total_copies, b.available_copies, b.publisher_id, p.publisher_name, GROUP_CONCAT(DISTINCT a.author_name SEPARATOR ", ") AS author_names, GROUP_CONCAT(DISTINCT ba.author_id) AS author_id_list, GROUP_CONCAT(DISTINCT c.category_name SEPARATOR ", ") AS category_names, GROUP_CONCAT(DISTINCT bc.category_id) AS category_id_list FROM books b LEFT JOIN publishers p ON p.publisher_id = b.publisher_id LEFT JOIN book_authors ba ON ba.book_id = b.book_id LEFT JOIN authors a ON a.author_id = ba.author_id LEFT JOIN book_categories bc ON bc.book_id = b.book_id LEFT JOIN categories c ON c.category_id = bc.category_id GROUP BY b.book_id ORDER BY b.title')->fetchAll();

$editBook = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    foreach ($books as $b) {
        if ((int)$b['book_id'] === $editId) {
            $editBook = $b;
            break;
        }
    }
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
  <?php include dirname(__DIR__, 2) . '/partials/sidebar-admin.php'; ?>
  <div class="main-content">
    <?php include dirname(__DIR__, 2) . '/partials/topbar.php'; ?>
    <div class="content-area">

      <?php if ($flash) { ?>
        <div class="alert <?php echo $flash['type'] === 'success' ? 'alert-success' : 'alert-error'; ?> mb-24">
          <i class="fas fa-<?php echo $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
          <?php echo htmlspecialchars($flash['message']); ?>
        </div>
      <?php } ?>

      <?php if ($editBook) { ?>
      <div class="card mb-24">
        <div class="card-header">
          <div>
            <h2><i class="fas fa-edit" style="color:var(--primary);margin-right:8px"></i>Edit Book</h2>
            <p>Update the details for "<?php echo htmlspecialchars($editBook['title']); ?>"</p>
          </div>
          <a href="manage-books.php" class="btn btn-ghost"><i class="fas fa-times"></i> Cancel</a>
        </div>
        <?php $eAuthorIds = $editBook['author_id_list'] ? explode(',', $editBook['author_id_list']) : []; ?>
        <?php $eCategoryIds = $editBook['category_id_list'] ? explode(',', $editBook['category_id_list']) : []; ?>
        <form method="post" action="manage-books.php">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="action" value="edit">
          <input type="hidden" name="book_id" value="<?php echo (int)$editBook['book_id']; ?>">
          <div class="form-grid">
            <div class="form-group">
              <label for="title">Book Title</label>
              <input id="title" type="text" name="title" value="<?php echo htmlspecialchars($editBook['title']); ?>" required>
            </div>
            <div class="form-group">
              <label for="isbn">ISBN</label>
              <input id="isbn" type="text" name="isbn" value="<?php echo htmlspecialchars((string)($editBook['isbn'] ?? '')); ?>">
            </div>
            <div class="form-group">
              <label for="publish_year">Publish Year</label>
              <input id="publish_year" type="number" name="publish_year" min="1900" max="2100" value="<?php echo (int)$editBook['publish_year']; ?>">
            </div>
            <div class="form-group">
              <label for="total_copies">Total Copies</label>
              <input id="total_copies" type="number" name="total_copies" min="0" value="<?php echo (int)$editBook['total_copies']; ?>" required>
            </div>
            <div class="form-group">
              <label for="available_copies">Available Copies</label>
              <input id="available_copies" type="number" name="available_copies" min="0" value="<?php echo (int)$editBook['available_copies']; ?>" required>
            </div>
            <div class="form-group">
              <label for="publisher_id">Publisher</label>
              <select id="publisher_id" name="publisher_id">
                <option value="">Select publisher</option>
                <?php foreach ($publishers as $pub) { ?>
                  <option value="<?php echo (int)$pub['publisher_id']; ?>" <?php echo (int)$pub['publisher_id'] === (int)$editBook['publisher_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($pub['publisher_name']); ?></option>
                <?php } ?>
              </select>
              <div class="inline-add-toggle"><a href="#" onclick="toggleInlineAdd(this,'edit-new-publisher');return false"><i class="fas fa-plus-circle"></i> Add new publisher</a></div>
              <input type="text" id="edit-new-publisher" name="new_publisher" class="inline-add-input" placeholder="Enter new publisher name" style="display:none">
            </div>
            <div class="form-group">
              <label for="author_ids">Authors</label>
              <select id="author_ids" name="author_ids[]" multiple style="min-height:80px">
                <?php foreach ($authors as $a) { ?>
                  <option value="<?php echo (int)$a['author_id']; ?>" <?php echo in_array((string)$a['author_id'], $eAuthorIds) ? 'selected' : ''; ?>><?php echo htmlspecialchars($a['author_name']); ?></option>
                <?php } ?>
              </select>
              <div class="inline-add-toggle"><a href="#" onclick="toggleInlineAdd(this,'edit-new-authors');return false"><i class="fas fa-plus-circle"></i> Add new authors</a></div>
              <input type="text" id="edit-new-authors" name="new_authors" class="inline-add-input" placeholder="Comma-separated, e.g. Author A, Author B" style="display:none">
            </div>
            <div class="form-group">
              <label for="category_ids">Categories</label>
              <select id="category_ids" name="category_ids[]" multiple style="min-height:80px">
                <?php foreach ($categories as $c) { ?>
                  <option value="<?php echo (int)$c['category_id']; ?>" <?php echo in_array((string)$c['category_id'], $eCategoryIds) ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['category_name']); ?></option>
                <?php } ?>
              </select>
              <div class="inline-add-toggle"><a href="#" onclick="toggleInlineAdd(this,'edit-new-category');return false"><i class="fas fa-plus-circle"></i> Add new category</a></div>
              <input type="text" id="edit-new-category" name="new_category" class="inline-add-input" placeholder="Enter new category name" style="display:none">
            </div>
          </div>
          <button class="btn btn-primary mt-16" type="submit"><i class="fas fa-save"></i> Save Changes</button>
        </form>
      </div>
      <?php } else { ?>
      <div class="card mb-24">
        <div class="card-header">
          <div>
            <h2><i class="fas fa-plus-circle" style="color:var(--success);margin-right:8px"></i>Add New Book</h2>
            <p>Fill in the details to add a book to the catalogue</p>
          </div>
        </div>
        <form method="post" action="manage-books.php">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="action" value="add">
          <div class="form-grid">
            <div class="form-group">
              <label for="title">Book Title</label>
              <input id="title" type="text" name="title" placeholder="Enter book title" required>
            </div>
            <div class="form-group">
              <label for="isbn">ISBN</label>
              <input id="isbn" type="text" name="isbn" placeholder="Optional">
            </div>
            <div class="form-group">
              <label for="publish_year">Publish Year</label>
              <input id="publish_year" type="number" name="publish_year" min="1900" max="2100" placeholder="e.g. 2023">
            </div>
            <div class="form-group">
              <label for="total_copies">Copies</label>
              <input id="total_copies" type="number" name="total_copies" min="1" placeholder="Qty" required>
            </div>
            <div class="form-group">
              <label for="publisher_id">Publisher</label>
              <select id="publisher_id" name="publisher_id">
                <option value="">Select publisher</option>
                <?php foreach ($publishers as $pub) { ?>
                  <option value="<?php echo (int)$pub['publisher_id']; ?>"><?php echo htmlspecialchars($pub['publisher_name']); ?></option>
                <?php } ?>
              </select>
              <div class="inline-add-toggle"><a href="#" onclick="toggleInlineAdd(this,'add-new-publisher');return false"><i class="fas fa-plus-circle"></i> Add new publisher</a></div>
              <input type="text" id="add-new-publisher" name="new_publisher" class="inline-add-input" placeholder="Enter new publisher name" style="display:none">
            </div>
            <div class="form-group">
              <label for="author_ids">Authors</label>
              <select id="author_ids" name="author_ids[]" multiple style="min-height:80px">
                <?php foreach ($authors as $a) { ?>
                  <option value="<?php echo (int)$a['author_id']; ?>"><?php echo htmlspecialchars($a['author_name']); ?></option>
                <?php } ?>
              </select>
              <div class="inline-add-toggle"><a href="#" onclick="toggleInlineAdd(this,'add-new-authors');return false"><i class="fas fa-plus-circle"></i> Add new authors</a></div>
              <input type="text" id="add-new-authors" name="new_authors" class="inline-add-input" placeholder="Comma-separated, e.g. Author A, Author B" style="display:none">
            </div>
            <div class="form-group">
              <label for="category_ids">Categories</label>
              <select id="category_ids" name="category_ids[]" multiple style="min-height:80px">
                <?php foreach ($categories as $c) { ?>
                  <option value="<?php echo (int)$c['category_id']; ?>"><?php echo htmlspecialchars($c['category_name']); ?></option>
                <?php } ?>
              </select>
              <div class="inline-add-toggle"><a href="#" onclick="toggleInlineAdd(this,'add-new-category');return false"><i class="fas fa-plus-circle"></i> Add new category</a></div>
              <input type="text" id="add-new-category" name="new_category" class="inline-add-input" placeholder="Enter new category name" style="display:none">
            </div>
          </div>
          <button class="btn btn-primary mt-16" type="submit"><i class="fas fa-plus"></i> Add Book</button>
        </form>
      </div>
      <?php } ?>

      <div class="card">
        <div class="card-header">
          <div>
            <h2><i class="fas fa-book" style="color:var(--primary);margin-right:8px"></i>Catalogue (<?php echo count($books); ?> books)</h2>
            <p>Edit or delete books from the catalogue</p>
          </div>
        </div>
        <div class="table-wrapper">
          <table class="data-table">
            <thead>
              <tr><th>Title</th><th>Authors</th><th>Publisher</th><th>Year</th><th>ISBN</th><th>Copies</th><th class="text-right">Actions</th></tr>
            </thead>
            <tbody>
              <?php foreach ($books as $book) { ?>
                <tr>
                  <td class="font-semibold"><?php echo htmlspecialchars($book['title']); ?></td>
                  <td class="text-sm"><?php echo htmlspecialchars((string)($book['author_names'] ?? '—')); ?></td>
                  <td><?php echo htmlspecialchars((string)$book['publisher_name']); ?></td>
                  <td><?php echo htmlspecialchars((string)$book['publish_year']); ?></td>
                  <td><span class="text-sm text-muted"><?php echo htmlspecialchars((string)$book['isbn']); ?></span></td>
                  <td>
                    <span class="badge <?php echo (int)$book['available_copies'] > 0 ? 'badge-success' : 'badge-danger'; ?>">
                      <?php echo (int)$book['available_copies']; ?>/<?php echo (int)$book['total_copies']; ?>
                    </span>
                  </td>
                  <td class="text-right">
                    <div style="display:inline-flex;gap:6px">
                      <a href="manage-books.php?edit=<?php echo (int)$book['book_id']; ?>" class="btn btn-sm btn-secondary"><i class="fas fa-edit"></i> Edit</a>
                      <form method="post" action="manage-books.php" style="display:inline" data-confirm="Delete this book permanently?">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="book_id" value="<?php echo (int)$book['book_id']; ?>">
                        <button class="btn btn-sm btn-danger" type="submit"><i class="fas fa-trash"></i></button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php } ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
    <?php include dirname(__DIR__, 2) . '/partials/footer.php'; ?>
  </div>
  <script src="<?php echo $basePath; ?>/assets/js/app.js"></script>
  <script>
  function toggleInlineAdd(link, inputId) {
    var inp = document.getElementById(inputId);
    if (inp.style.display === 'none') {
      inp.style.display = 'block';
      inp.focus();
      link.innerHTML = '<i class="fas fa-times-circle"></i> Cancel';
    } else {
      inp.style.display = 'none';
      inp.value = '';
      link.innerHTML = '<i class="fas fa-plus-circle"></i> Add new';
    }
  }
  </script>
</body>
</html>