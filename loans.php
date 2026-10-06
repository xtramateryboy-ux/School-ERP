<?php
require __DIR__ . '/../config/auth.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = $_POST['action'] ?? '';

    if ($action === 'issue') {
        $bookId = filter_var($_POST['book_id'] ?? '', FILTER_VALIDATE_INT);
        $studentId = filter_var($_POST['student_id'] ?? '', FILTER_VALIDATE_INT);
        $issueDate = trim($_POST['issue_date'] ?? '');
        $dueDate = trim($_POST['due_date'] ?? '');

        $issueDateObject = DateTime::createFromFormat('Y-m-d', $issueDate);
        $dueDateObject = DateTime::createFromFormat('Y-m-d', $dueDate);

        $validIssueDate = $issueDateObject
            && $issueDateObject->format('Y-m-d') === $issueDate;

        $validDueDate = $dueDateObject
            && $dueDateObject->format('Y-m-d') === $dueDate;

        if (!$bookId || !$studentId || !$validIssueDate || !$validDueDate) {
            $error = 'Book, student aur valid issue/due dates select karein.';
        } elseif ($dueDate < $issueDate) {
            $error = 'Due date, issue date se pehle nahi ho sakti.';
        } else {
            try {
                $pdo->beginTransaction();

                // Book row lock karne se same copy ek saath do baar issue nahi hogi.
                $bookStmt = $pdo->prepare(
                    'SELECT id, total_copies
                     FROM library_books
                     WHERE id = ?
                     FOR UPDATE'
                );
                $bookStmt->execute([$bookId]);
                $book = $bookStmt->fetch();

                $studentStmt = $pdo->prepare('SELECT id FROM students WHERE id = ?');
                $studentStmt->execute([$studentId]);
                $studentExists = $studentStmt->fetch();

                if (!$book || !$studentExists) {
                    $pdo->rollBack();
                    $error = 'Selected book ya student record nahi mila.';
                } else {
                    $loanCountStmt = $pdo->prepare(
                        'SELECT COUNT(*)
                         FROM library_loans
                         WHERE book_id = ?
                           AND returned_at IS NULL'
                    );
                    $loanCountStmt->execute([$bookId]);
                    $activeLoans = (int) $loanCountStmt->fetchColumn();

                    if ($activeLoans >= (int) $book['total_copies']) {
                        $pdo->rollBack();
                        $error = 'Is book ki saari copies abhi issue hain.';
                    } else {
                        $insertStmt = $pdo->prepare(
                            'INSERT INTO library_loans
                                (book_id, student_id, issue_date, due_date, issued_by)
                             VALUES (?, ?, ?, ?, ?)'
                        );

                        $insertStmt->execute([
                            $bookId,
                            $studentId,
                            $issueDate,
                            $dueDate,
                            $_SESSION['user_id']
                        ]);

                        $pdo->commit();

                        header('Location: loans.php?saved=1');
                        exit;
                    }
                }
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $error = 'Book issue nahi ho saki. Dobara try karein.';
            }
        }
    } elseif ($action === 'return') {
        $loanId = filter_var($_POST['loan_id'] ?? '', FILTER_VALIDATE_INT);

        if ($loanId) {
            $stmt = $pdo->prepare(
                'UPDATE library_loans
                 SET returned_at = NOW()
                 WHERE id = ?
                   AND returned_at IS NULL'
            );
            $stmt->execute([$loanId]);
        }

        header('Location: loan.php');
        exit;
    }
}

$books = $pdo->query(
    'SELECT
        b.id,
        b.title,
        b.author,
        b.total_copies,
        b.total_copies - (
            SELECT COUNT(*)
            FROM library_loans l
            WHERE l.book_id = b.id
              AND l.returned_at IS NULL
        ) AS available_copies
     FROM library_books b
     ORDER BY b.title'
)->fetchAll();

$students = $pdo->query(
    'SELECT id, admission_no, first_name, last_name, class_name, section
     FROM students
     ORDER BY first_name, last_name'
)->fetchAll();

$loans = $pdo->query(
    'SELECT
        l.id,
        l.issue_date,
        l.due_date,
        l.returned_at,
        b.title,
        b.accession_no,
        s.admission_no AS student_admission_no,
        s.first_name,
        s.last_name,
        s.class_name,
        s.section
     FROM library_loans l
     JOIN library_books b ON b.id = l.book_id
     JOIN students s ON s.id = l.student_id
     ORDER BY l.returned_at IS NULL DESC, l.due_date ASC, l.id DESC'
)->fetchAll();

$saved = isset($_GET['saved']) && $_GET['saved'] === '1';
?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library Issue/Return — School ERP</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <header class="topbar">
        <a class="logo" href="../dashboard.php">School ERP</a>
        <div class="user-area">
            <span><?= e($_SESSION['name']) ?></span>
            <a class="button button-outline" href="../logout.php">Logout</a>
        </div>
    </header>

    <main class="container">
        <div class="page-heading">
            <div>
                <p class="eyebrow">LIBRARY</p>
                <h1>Book Issue / Return</h1>
                <p class="muted">Students ko books issue karein aur returned books record karein.</p>
            </div>
            <a class="button button-outline" href="index.php">Book Catalog</a>
        </div>

        <?php if ($saved): ?>
            <div class="success-message">Book successfully issue ho gayi.</div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <section class="form-card">
            <h2>Issue a Book</h2>

            <?php if (!$books || !$students): ?>
                <div class="alert">
                    Book issue karne ke liye Library catalog mein book aur Students module mein student hona chahiye.
                </div>
            <?php else: ?>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="issue">

                    <div class="form-grid">
                        <div>
                            <label for="book_id">Book *</label>
                            <select id="book_id" name="book_id" required>
                                <option value="">Book select karein</option>
                                <?php foreach ($books as $book): ?>
                                    <?php if ((int) $book['available_copies'] > 0): ?>
                                        <option value="<?= (int) $book['id'] ?>">
                                            <?= e($book['title']) ?>
                                            — <?= e($book['accession_no']) ?>
                                            (<?= (int) $book['available_copies'] ?> available)
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label for="student_id">Student *</label>
                            <select id="student_id" name="student_id" required>
                                <option value="">Student select karein</option>
                                <?php foreach ($students as $student): ?>
                                    <option value="<?= (int) $student['id'] ?>">
                                        <?= e($student['admission_no']) ?> —
                                        <?= e($student['first_name'] . ' ' . $student['last_name']) ?>
                                        (<?= e($student['class_name']) ?><?= $student['section'] ? '-' . e($student['section']) : '' ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label for="issue_date">Issue Date *</label>
                            <input
                                id="issue_date"
                                type="date"
                                name="issue_date"
                                value="<?= e(date('Y-m-d')) ?>"
                                required
                            >
                        </div>

                        <div>
                            <label for="due_date">Due Date *</label>
                            <input
                                id="due_date"
                                type="date"
                                name="due_date"
                                value="<?= e(date('Y-m-d', strtotime('+14 days'))) ?>"
                                required
                            >
                        </div>
                    </div>

                    <div class="form-actions">
                        <button class="button button-primary" type="submit">Issue Book</button>
                    </div>
                </form>
            <?php endif; ?>
        </section>

        <section class="table-card module-list-card">
            <h2>Loan Records</h2>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Book</th>
                            <th>Student</th>
                            <th>Issue Date</th>
                            <th>Due Date</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$loans): ?>
                            <tr>
                                <td colspan="6" class="empty-state">Abhi koi loan record nahi hai.</td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($loans as $loan): ?>
                            <?php
                            $isReturned = $loan['returned_at'] !== null;
                            $isOverdue = !$isReturned && $loan['due_date'] < date('Y-m-d');
                            ?>
                            <tr>
                                <td>
                                    <?= e($loan['title']) ?>
                                    <small class="table-subtext"><?= e($loan['accession_no']) ?></small>
                                </td>
                                <td>
                                    <?= e($loan['first_name'] . ' ' . $loan['last_name']) ?>
                                    <small class="table-subtext">
                                        <?= e($loan['student_admission_no']) ?>
                                    </small>
                                </td>
                                <td><?= e($loan['issue_date']) ?></td>
                                <td><?= e($loan['due_date']) ?></td>
                                <td>
                                    <?php if ($isReturned): ?>
                                        Returned
                                    <?php elseif ($isOverdue): ?>
                                        <span class="fee-status status-unpaid">Overdue</span>
                                    <?php else: ?>
                                        Issued
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!$isReturned): ?>
                                        <form method="POST">
                                            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                                            <input type="hidden" name="action" value="return">
                                            <input type="hidden" name="loan_id" value="<?= (int) $loan['id'] ?>">
                                            <button
                                                class="button button-primary button-small"
                                                type="submit"
                                                onclick="return confirm('Book return mark karein?');"
                                            >Mark Returned</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="muted"><?= e($loan['returned_at']) ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <p class="back-link"><a href="../dashboard.php">← Dashboard par wapas jaayein</a></p>
    </main>
</body>
</html>