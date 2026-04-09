<?php
/**
 * Seed script: populates the database with demo student accounts and realistic transaction data.
 *
 * Run:  php scripts/seed.php
 */

$host = getenv('LMS_DB_HOST') ?: 'localhost';
$user = getenv('LMS_DB_USER') ?: 'root';
$pass = getenv('LMS_DB_PASS') ?: '';
$dbName = 'library_management_system';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbName;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    fwrite(STDERR, "DB connection failed: " . $e->getMessage() . "\n");
    fwrite(STDERR, "Run  php scripts/migrate.php  first.\n");
    exit(1);
}

/* ---- Fetch student role_id ---- */
$roleStmt = $pdo->prepare('SELECT role_id FROM roles WHERE role_name = ? LIMIT 1');
$roleStmt->execute(['student']);
$studentRoleId = (int)$roleStmt->fetchColumn();
if (!$studentRoleId) {
    fwrite(STDERR, "Student role not found. Run migrate.php first.\n");
    exit(1);
}

/* ====================================================================
   1. DEMO STUDENT USERS  (password meets strength rules)
   ==================================================================== */
$students = [
    ['full_name' => 'Student 1', 'email' => 'student1@university.edu', 'password' => 'Student@1'],
    ['full_name' => 'Student 2', 'email' => 'student2@university.edu', 'password' => 'Student@2'],
    ['full_name' => 'Student 3', 'email' => 'student3@university.edu', 'password' => 'Student@3'],
    ['full_name' => 'Student 4', 'email' => 'student4@university.edu', 'password' => 'Student@4'],
    ['full_name' => 'Student 5', 'email' => 'student5@university.edu', 'password' => 'Student@5'],
];

$studentIds = [];
$insertUser = $pdo->prepare('INSERT IGNORE INTO users (role_id, full_name, email, password_hash, is_email_confirmed, status) VALUES (?, ?, ?, ?, 1, \'active\')');
$selectUser = $pdo->prepare('SELECT user_id FROM users WHERE email = ? LIMIT 1');

foreach ($students as $s) {
    $hash = password_hash($s['password'], PASSWORD_DEFAULT);
    $insertUser->execute([$studentRoleId, $s['full_name'], $s['email'], $hash]);
    $selectUser->execute([$s['email']]);
    $studentIds[$s['email']] = (int)$selectUser->fetchColumn();
}

echo "Created " . count($studentIds) . " student accounts.\n";
$s1 = $studentIds['student1@university.edu'];
$s2 = $studentIds['student2@university.edu'];
$s3 = $studentIds['student3@university.edu'];
$s4 = $studentIds['student4@university.edu'];
$s5 = $studentIds['student5@university.edu'];

/* ====================================================================
   2. BORROWINGS  (various statuses: active, returned, overdue, return_requested)
   ==================================================================== */
// Clear existing seeded borrowings to allow re-running
$pdo->exec("DELETE FROM fines WHERE borrowing_id IN (SELECT borrowing_id FROM borrowings WHERE user_id IN ($s1,$s2,$s3,$s4,$s5))");
$pdo->exec("DELETE FROM borrowings WHERE user_id IN ($s1,$s2,$s3,$s4,$s5)");
$pdo->exec("DELETE FROM reservations WHERE user_id IN ($s1,$s2,$s3,$s4,$s5)");
$pdo->exec("DELETE FROM notifications WHERE user_id IN ($s1,$s2,$s3,$s4,$s5)");
$pdo->exec("DELETE FROM borrow_history WHERE user_id IN ($s1,$s2,$s3,$s4,$s5)");
$pdo->exec("DELETE FROM search_history WHERE user_id IN ($s1,$s2,$s3,$s4,$s5)");

// Reset all book available_copies to total_copies, then we'll adjust below
$pdo->exec('UPDATE books SET available_copies = total_copies');

$insertBorrowing = $pdo->prepare('INSERT INTO borrowings (user_id, book_id, borrow_date, due_date, return_date, status) VALUES (?, ?, ?, ?, ?, ?)');
$insertHistory   = $pdo->prepare('INSERT INTO borrow_history (user_id, book_id, borrowed_at) VALUES (?, ?, ?)');
$decrementCopies = $pdo->prepare('UPDATE books SET available_copies = available_copies - 1 WHERE book_id = ? AND available_copies > 0');

$today = date('Y-m-d');
$borrowings = [
    // Student 1: 2 active borrowings, 1 returned
    ['user' => $s1, 'book' => 1,  'borrow' => date('Y-m-d', strtotime('-10 days')), 'due' => date('Y-m-d', strtotime('+4 days')),  'return' => null,                                'status' => 'borrowed'],
    ['user' => $s1, 'book' => 4,  'borrow' => date('Y-m-d', strtotime('-7 days')),  'due' => date('Y-m-d', strtotime('+7 days')),  'return' => null,                                'status' => 'borrowed'],
    ['user' => $s1, 'book' => 11, 'borrow' => date('Y-m-d', strtotime('-30 days')), 'due' => date('Y-m-d', strtotime('-16 days')), 'return' => date('Y-m-d', strtotime('-14 days')), 'status' => 'returned'],

    // Student 2: 1 overdue, 1 return_requested, 1 returned
    ['user' => $s2, 'book' => 2,  'borrow' => date('Y-m-d', strtotime('-20 days')), 'due' => date('Y-m-d', strtotime('-6 days')), 'return' => null,                                'status' => 'overdue'],
    ['user' => $s2, 'book' => 8,  'borrow' => date('Y-m-d', strtotime('-12 days')), 'due' => date('Y-m-d', strtotime('+2 days')), 'return' => null,                                'status' => 'return_requested'],
    ['user' => $s2, 'book' => 6,  'borrow' => date('Y-m-d', strtotime('-25 days')), 'due' => date('Y-m-d', strtotime('-11 days')), 'return' => date('Y-m-d', strtotime('-10 days')), 'status' => 'returned'],

    // Student 3: 1 active, 1 overdue
    ['user' => $s3, 'book' => 7,  'borrow' => date('Y-m-d', strtotime('-5 days')),  'due' => date('Y-m-d', strtotime('+9 days')), 'return' => null, 'status' => 'borrowed'],
    ['user' => $s3, 'book' => 10, 'borrow' => date('Y-m-d', strtotime('-18 days')), 'due' => date('Y-m-d', strtotime('-4 days')), 'return' => null, 'status' => 'overdue'],

    // Student 4: 2 active, 1 return_requested
    ['user' => $s4, 'book' => 3,  'borrow' => date('Y-m-d', strtotime('-8 days')),  'due' => date('Y-m-d', strtotime('+6 days')),  'return' => null, 'status' => 'borrowed'],
    ['user' => $s4, 'book' => 12, 'borrow' => date('Y-m-d', strtotime('-3 days')),  'due' => date('Y-m-d', strtotime('+11 days')), 'return' => null, 'status' => 'borrowed'],
    ['user' => $s4, 'book' => 5,  'borrow' => date('Y-m-d', strtotime('-13 days')), 'due' => date('Y-m-d', strtotime('+1 day')),   'return' => null, 'status' => 'return_requested'],

    // Student 5: 1 active, 2 returned
    ['user' => $s5, 'book' => 9,  'borrow' => date('Y-m-d', strtotime('-6 days')),  'due' => date('Y-m-d', strtotime('+8 days')),  'return' => null,                                'status' => 'borrowed'],
    ['user' => $s5, 'book' => 14, 'borrow' => date('Y-m-d', strtotime('-20 days')), 'due' => date('Y-m-d', strtotime('-6 days')),  'return' => date('Y-m-d', strtotime('-5 days')), 'status' => 'returned'],
    ['user' => $s5, 'book' => 15, 'borrow' => date('Y-m-d', strtotime('-28 days')), 'due' => date('Y-m-d', strtotime('-14 days')), 'return' => date('Y-m-d', strtotime('-12 days')), 'status' => 'returned'],
];

$borrowingIds = [];
foreach ($borrowings as $b) {
    $insertBorrowing->execute([$b['user'], $b['book'], $b['borrow'], $b['due'], $b['return'], $b['status']]);
    $bid = (int)$pdo->lastInsertId();
    $borrowingIds[] = ['id' => $bid, 'user' => $b['user'], 'book' => $b['book'], 'status' => $b['status']];
    $insertHistory->execute([$b['user'], $b['book'], $b['borrow']]);

    // Decrement copies only for non-returned
    if ($b['return'] === null) {
        $decrementCopies->execute([$b['book']]);
    }
}
echo "Created " . count($borrowings) . " borrowing records.\n";

/* ====================================================================
   3. RESERVATIONS  (active + fulfilled)
   ==================================================================== */
$insertReservation = $pdo->prepare('INSERT INTO reservations (user_id, book_id, reservation_date, position, status) VALUES (?, ?, ?, ?, ?)');
$reservations = [
    // Student 1 reserved book 2 (currently borrowed by Student 2 overdue) – position 1
    ['user' => $s1, 'book' => 2,  'date' => date('Y-m-d', strtotime('-3 days')), 'pos' => 1, 'status' => 'active'],
    // Student 3 reserved book 8 (return_requested by Student 2) – position 1
    ['user' => $s3, 'book' => 8,  'date' => date('Y-m-d', strtotime('-2 days')), 'pos' => 1, 'status' => 'active'],
    // Student 5 reserved book 2 – position 2
    ['user' => $s5, 'book' => 2,  'date' => date('Y-m-d', strtotime('-1 day')),  'pos' => 2, 'status' => 'active'],
    // Student 4 had reserved book 6 – fulfilled when Student 2 returned it
    ['user' => $s4, 'book' => 6,  'date' => date('Y-m-d', strtotime('-12 days')), 'pos' => 1, 'status' => 'fulfilled'],
    // Student 2 had reserved book 14 – fulfilled
    ['user' => $s2, 'book' => 14, 'date' => date('Y-m-d', strtotime('-22 days')), 'pos' => 1, 'status' => 'fulfilled'],
];

foreach ($reservations as $r) {
    $insertReservation->execute([$r['user'], $r['book'], $r['date'], $r['pos'], $r['status']]);
}
echo "Created " . count($reservations) . " reservation records.\n";

/* ====================================================================
   4. FINES  (for overdue borrowings)
   ==================================================================== */
$insertFine = $pdo->prepare('INSERT INTO fines (borrowing_id, amount, paid_status) VALUES (?, ?, ?)');
$finesCreated = 0;
foreach ($borrowingIds as $bi) {
    if ($bi['status'] === 'overdue') {
        // £1 per day overdue
        $insertFine->execute([$bi['id'], 6.00, 'unpaid']);
        $finesCreated++;
    }
}
// Add a paid fine for Student 5 (book 14 was returned late)
foreach ($borrowingIds as $bi) {
    if ($bi['user'] === $s5 && $bi['book'] === 14) {
        $insertFine->execute([$bi['id'], 1.00, 'paid']);
        $finesCreated++;
    }
}
echo "Created $finesCreated fine records.\n";

/* ====================================================================
   5. NOTIFICATIONS
   ==================================================================== */
$insertNotif = $pdo->prepare('INSERT INTO notifications (user_id, message, notification_type, created_at, is_read) VALUES (?, ?, ?, ?, ?)');
$notifications = [
    [$s1, 'Borrowed "Design Patterns: Elements of Reusable Object-Oriented Software". Due in 14 days.', 'due_date', date('Y-m-d H:i:s', strtotime('-10 days')), 0],
    [$s1, 'Borrowed "Clean Code". Due in 14 days.', 'due_date', date('Y-m-d H:i:s', strtotime('-7 days')), 0],
    [$s1, 'Reserved "Introduction to Algorithms". Current queue position: 1.', 'reservation', date('Y-m-d H:i:s', strtotime('-3 days')), 0],
    [$s1, 'Returned "Deep Learning with Python" successfully.', 'general', date('Y-m-d H:i:s', strtotime('-14 days')), 1],

    [$s2, 'Borrowed "Introduction to Algorithms". Due in 14 days.', 'due_date', date('Y-m-d H:i:s', strtotime('-20 days')), 1],
    [$s2, 'Your book "Introduction to Algorithms" is now overdue. Please return it immediately.', 'due_date', date('Y-m-d H:i:s', strtotime('-6 days')), 0],
    [$s2, 'A fine of £6.00 has been issued for "Introduction to Algorithms".', 'fine', date('Y-m-d H:i:s', strtotime('-5 days')), 0],
    [$s2, 'Return request submitted for "Artificial Intelligence: A Modern Approach".', 'general', date('Y-m-d H:i:s', strtotime('-1 day')), 0],

    [$s3, 'Borrowed "Computer Networking: A Top-Down Approach". Due in 14 days.', 'due_date', date('Y-m-d H:i:s', strtotime('-5 days')), 1],
    [$s3, 'Your book "Database System Concepts" is now overdue.', 'due_date', date('Y-m-d H:i:s', strtotime('-4 days')), 0],
    [$s3, 'A fine of £6.00 has been issued for "Database System Concepts".', 'fine', date('Y-m-d H:i:s', strtotime('-3 days')), 0],
    [$s3, 'Reserved "Artificial Intelligence: A Modern Approach". Queue position: 1.', 'reservation', date('Y-m-d H:i:s', strtotime('-2 days')), 0],

    [$s4, 'Borrowed "Concrete Mathematics". Due in 14 days.', 'due_date', date('Y-m-d H:i:s', strtotime('-8 days')), 1],
    [$s4, 'Borrowed "Hands-On Machine Learning with Scikit-Learn, Keras, and TensorFlow". Due in 14 days.', 'due_date', date('Y-m-d H:i:s', strtotime('-3 days')), 0],
    [$s4, 'Return request submitted for "Code Complete".', 'general', date('Y-m-d H:i:s', strtotime('-1 day')), 0],

    [$s5, 'Borrowed "Operating System Concepts". Due in 14 days.', 'due_date', date('Y-m-d H:i:s', strtotime('-6 days')), 1],
    [$s5, 'Fine of £1.00 for late return of "Structure and Interpretation of Computer Programs" has been paid.', 'fine', date('Y-m-d H:i:s', strtotime('-4 days')), 1],
    [$s5, 'Reserved "Introduction to Algorithms". Queue position: 2.', 'reservation', date('Y-m-d H:i:s', strtotime('-1 day')), 0],
];

foreach ($notifications as $n) {
    $insertNotif->execute($n);
}
echo "Created " . count($notifications) . " notification records.\n";

/* ====================================================================
   6. SEARCH HISTORY  (for recommendation engine)
   ==================================================================== */
$insertSearch = $pdo->prepare('INSERT INTO search_history (user_id, search_keyword, category_id, searched_at) VALUES (?, ?, ?, ?)');
$searches = [
    // Student 1: interested in Data Science & Software Engineering
    [$s1, 'machine learning',  5, date('Y-m-d H:i:s', strtotime('-5 days'))],
    [$s1, 'deep learning',     5, date('Y-m-d H:i:s', strtotime('-4 days'))],
    [$s1, 'clean code',        6, date('Y-m-d H:i:s', strtotime('-3 days'))],
    [$s1, null,                1, date('Y-m-d H:i:s', strtotime('-2 days'))],

    // Student 2: interested in Computer Science & Algorithms
    [$s2, 'algorithms',          1, date('Y-m-d H:i:s', strtotime('-8 days'))],
    [$s2, 'artificial intelligence', 1, date('Y-m-d H:i:s', strtotime('-6 days'))],
    [$s2, 'data structures',     1, date('Y-m-d H:i:s', strtotime('-3 days'))],

    // Student 3: interested in Engineering & Networking
    [$s3, 'networking',      2, date('Y-m-d H:i:s', strtotime('-7 days'))],
    [$s3, 'database',        1, date('Y-m-d H:i:s', strtotime('-4 days'))],
    [$s3, null,              2, date('Y-m-d H:i:s', strtotime('-2 days'))],

    // Student 4: interested in Mathematics & Data Science
    [$s4, 'mathematics',       3, date('Y-m-d H:i:s', strtotime('-6 days'))],
    [$s4, 'tensorflow',        5, date('Y-m-d H:i:s', strtotime('-3 days'))],
    [$s4, 'scikit-learn',      5, date('Y-m-d H:i:s', strtotime('-1 day'))],

    // Student 5: interested in Operating Systems & CS fundamentals
    [$s5, 'operating systems', 1, date('Y-m-d H:i:s', strtotime('-9 days'))],
    [$s5, 'programming',       6, date('Y-m-d H:i:s', strtotime('-5 days'))],
    [$s5, null,                3, date('Y-m-d H:i:s', strtotime('-2 days'))],
];

foreach ($searches as $s) {
    $insertSearch->execute($s);
}
echo "Created " . count($searches) . " search history records.\n";

echo "\n=== Seeding Complete ===\n";
echo "Demo student accounts:\n";
echo str_pad('Name', 20) . str_pad('Email', 30) . "Password\n";
echo str_repeat('-', 70) . "\n";
foreach ($students as $s) {
    echo str_pad($s['full_name'], 20) . str_pad($s['email'], 30) . $s['password'] . "\n";
}
echo "\nAdmin account:  admin@admin.com / admin1234\n";
