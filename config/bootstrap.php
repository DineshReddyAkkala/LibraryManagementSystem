<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/smtp.php';

/* ---- CSRF protection helpers ---- */
function csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field()
{
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function verify_csrf()
{
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals(csrf_token(), $token)) {
        set_flash('error', 'Invalid form submission. Please try again.');
        return false;
    }
    return true;
}

/* ---- Auto-update overdue borrowings ---- */
if (isset($pdo)) {
    $pdo->exec("UPDATE borrowings SET status = 'overdue' WHERE return_date IS NULL AND due_date < CURDATE() AND status = 'borrowed'");
}

function notify_user($pdo, $userId, $message, $type, $emailSubject = '')
{
    $pdo->prepare('INSERT INTO notifications (user_id, message, notification_type) VALUES (?, ?, ?)')
        ->execute([$userId, $message, $type]);

    if ($emailSubject !== '') {
        $userStmt = $pdo->prepare('SELECT full_name, email FROM users WHERE user_id = ? LIMIT 1');
        $userStmt->execute([$userId]);
        $user = $userStmt->fetch();
        if ($user) {
            @smtp_send($user['email'], $user['full_name'], $emailSubject, $message);
        }
    }
}

function require_login()
{
    if (empty($_SESSION['user_id'])) {
        header('Location: ../auth/login.php');
        exit;
    }
}

function current_user_role()
{
    return $_SESSION['role_name'] ?? 'guest';
}

function require_admin()
{
    require_login();
    if (current_user_role() !== 'admin') {
        header('Location: ../dashboard/student-dashboard.php');
        exit;
    }
}

function is_admin()
{
    return current_user_role() === 'admin';
}

function dashboard_path_for_current_user()
{
    if (current_user_role() === 'admin') {
        return '../admin/dashboard.php';
    }
    return '../dashboard/student-dashboard.php';
}

function current_user_name()
{
    return $_SESSION['full_name'] ?? 'Guest';
}

function set_flash($type, $message)
{
    $validTypes = ['success', 'error', 'warning', 'info'];
    if (!in_array($type, $validTypes, true)) {
        $type = 'error';
    }
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash()
{
    if (empty($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

function borrow_book($pdo, $userId, $bookId)
{
    try {
        $pdo->beginTransaction();

        $bookStatement = $pdo->prepare('SELECT title, available_copies FROM books WHERE book_id = ? FOR UPDATE');
        $bookStatement->execute([$bookId]);
        $book = $bookStatement->fetch();

        if (!$book) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'Book not found.'];
        }

        if ((int)$book['available_copies'] <= 0) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'This book is currently unavailable for borrowing.'];
        }

        $activeBorrowing = $pdo->prepare('SELECT borrowing_id FROM borrowings WHERE user_id = ? AND book_id = ? AND return_date IS NULL LIMIT 1');
        $activeBorrowing->execute([$userId, $bookId]);
        if ($activeBorrowing->fetch()) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'You have already borrowed this book.'];
        }

        $insertBorrowing = $pdo->prepare('INSERT INTO borrowings (user_id, book_id, borrow_date, due_date, status) VALUES (?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 14 DAY), ?)');
        $insertBorrowing->execute([$userId, $bookId, 'borrowed']);

        $insertHistory = $pdo->prepare('INSERT INTO borrow_history (user_id, book_id, borrowed_at) VALUES (?, ?, CURDATE())');
        $insertHistory->execute([$userId, $bookId]);

        $updateBook = $pdo->prepare('UPDATE books SET available_copies = available_copies - 1 WHERE book_id = ?');
        $updateBook->execute([$bookId]);

        $pdo->commit();
        notify_user($pdo, $userId, 'Borrowed "' . $book['title'] . '". Due in 14 days.', 'due_date', 'Book Borrowed - ' . $book['title']);
        return ['ok' => true, 'message' => 'Book borrowed successfully.'];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['ok' => false, 'message' => 'Unable to process borrow request right now.'];
    }
}

function reserve_book($pdo, $userId, $bookId)
{
    try {
        $pdo->beginTransaction();

        $bookStatement = $pdo->prepare('SELECT title, available_copies FROM books WHERE book_id = ? FOR UPDATE');
        $bookStatement->execute([$bookId]);
        $book = $bookStatement->fetch();

        if (!$book) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'Book not found.'];
        }

        if ((int)$book['available_copies'] > 0) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'This book is currently available. Please borrow it instead of reserving.'];
        }

        $activeBorrow = $pdo->prepare('SELECT borrowing_id FROM borrowings WHERE user_id = ? AND book_id = ? AND return_date IS NULL LIMIT 1');
        $activeBorrow->execute([$userId, $bookId]);
        if ($activeBorrow->fetch()) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'You already have this book borrowed.'];
        }

        $existingReservation = $pdo->prepare('SELECT reservation_id FROM reservations WHERE user_id = ? AND book_id = ? AND status = ? LIMIT 1');
        $existingReservation->execute([$userId, $bookId, 'active']);
        if ($existingReservation->fetch()) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'You already have an active reservation for this book.'];
        }

        $positionStatement = $pdo->prepare('SELECT COUNT(*) FROM reservations WHERE book_id = ? AND status = ?');
        $positionStatement->execute([$bookId, 'active']);
        $position = (int)$positionStatement->fetchColumn() + 1;

        $insertReservation = $pdo->prepare('INSERT INTO reservations (user_id, book_id, reservation_date, position, status) VALUES (?, ?, CURDATE(), ?, ?)');
        $insertReservation->execute([$userId, $bookId, $position, 'active']);

        $pdo->commit();
        notify_user($pdo, $userId, 'Reserved "' . $book['title'] . '". Current queue position: ' . $position . '.', 'reservation', 'Reservation Placed - ' . $book['title']);
        return ['ok' => true, 'message' => 'Reservation placed successfully.'];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['ok' => false, 'message' => 'Unable to process reservation right now.'];
    }
}

function return_borrowed_book($pdo, $userId, $borrowingId)
{
    try {
        $pdo->beginTransaction();

        $borrowingStatement = $pdo->prepare('SELECT br.book_id, b.title FROM borrowings br INNER JOIN books b ON b.book_id = br.book_id WHERE br.borrowing_id = ? AND br.user_id = ? AND br.return_date IS NULL AND br.status IN (?, ?, ?) FOR UPDATE');
        $borrowingStatement->execute([$borrowingId, $userId, 'borrowed', 'overdue', 'return_requested']);
        $borrowing = $borrowingStatement->fetch();

        if (!$borrowing) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'Borrowing record not found or already returned.'];
        }

        $updateBorrowing = $pdo->prepare('UPDATE borrowings SET return_date = CURDATE(), status = ? WHERE borrowing_id = ?');
        $updateBorrowing->execute(['returned', $borrowingId]);

        $updateBook = $pdo->prepare('UPDATE books SET available_copies = available_copies + 1 WHERE book_id = ?');
        $updateBook->execute([$borrowing['book_id']]);

        // Auto-allocate to the next reserved user in the queue
        $nextReservation = $pdo->prepare('SELECT r.reservation_id, r.user_id FROM reservations r WHERE r.book_id = ? AND r.status = ? ORDER BY r.position ASC LIMIT 1');
        $nextReservation->execute([$borrowing['book_id'], 'active']);
        $reserved = $nextReservation->fetch();

        if ($reserved) {
            // Create a borrowing for the reserved user
            $insertBorrowing = $pdo->prepare('INSERT INTO borrowings (user_id, book_id, borrow_date, due_date, status) VALUES (?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 14 DAY), ?)');
            $insertBorrowing->execute([(int)$reserved['user_id'], $borrowing['book_id'], 'borrowed']);

            $insertHistory = $pdo->prepare('INSERT INTO borrow_history (user_id, book_id, borrowed_at) VALUES (?, ?, CURDATE())');
            $insertHistory->execute([(int)$reserved['user_id'], $borrowing['book_id']]);

            // Decrement available copies back (was just incremented)
            $decrementBook = $pdo->prepare('UPDATE books SET available_copies = available_copies - 1 WHERE book_id = ?');
            $decrementBook->execute([$borrowing['book_id']]);

            // Mark reservation as fulfilled
            $fulfillReservation = $pdo->prepare('UPDATE reservations SET status = ? WHERE reservation_id = ?');
            $fulfillReservation->execute(['fulfilled', (int)$reserved['reservation_id']]);
        }

        $pdo->commit();
        notify_user($pdo, $userId, 'Returned "' . $borrowing['title'] . '" successfully.', 'general', 'Book Returned - ' . $borrowing['title']);

        if ($reserved) {
            notify_user($pdo, (int)$reserved['user_id'], '"' . $borrowing['title'] . '" is now available and has been allocated to you. Due in 14 days.', 'reservation', 'Book Allocated - ' . $borrowing['title']);
        }

        return ['ok' => true, 'message' => 'Book returned successfully.'];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['ok' => false, 'message' => 'Unable to process return right now.'];
    }
}

function request_return($pdo, $userId, $borrowingId)
{
    $stmt = $pdo->prepare('SELECT br.borrowing_id, b.title FROM borrowings br INNER JOIN books b ON b.book_id = br.book_id WHERE br.borrowing_id = ? AND br.user_id = ? AND br.return_date IS NULL AND br.status IN (?, ?)');
    $stmt->execute([$borrowingId, $userId, 'borrowed', 'overdue']);
    $borrowing = $stmt->fetch();

    if (!$borrowing) {
        return ['ok' => false, 'message' => 'Borrowing record not found or return already requested.'];
    }

    $update = $pdo->prepare('UPDATE borrowings SET status = ? WHERE borrowing_id = ?');
    $update->execute(['return_requested', $borrowingId]);

    $admins = $pdo->prepare('SELECT u.user_id FROM users u INNER JOIN roles r ON r.role_id = u.role_id WHERE r.role_name = ?');
    $admins->execute(['admin']);
    $adminRows = $admins->fetchAll();

    foreach ($adminRows as $admin) {
        notify_user($pdo, (int)$admin['user_id'], 'Return requested for "' . $borrowing['title'] . '".', 'general');
    }

    return ['ok' => true, 'message' => 'Return request submitted. Please wait for admin approval.'];
}

function cancel_reservation($pdo, $userId, $reservationId)
{
    $statement = $pdo->prepare('UPDATE reservations SET status = ? WHERE reservation_id = ? AND user_id = ? AND status = ?');
    $statement->execute(['cancelled', $reservationId, $userId, 'active']);

    if ($statement->rowCount() < 1) {
        return ['ok' => false, 'message' => 'Reservation not found or already closed.'];
    }

    return ['ok' => true, 'message' => 'Reservation cancelled successfully.'];
}
