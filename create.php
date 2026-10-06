<?php
require __DIR__ . '/../config/auth.php';

$students = $pdo->query(
    'SELECT id, admission_no, first_name, last_name, class_name, section
     FROM students
     ORDER BY first_name, last_name'
)->fetchAll();

$error = '';
$data = [
    'student_id' => '',
    'fee_title' => '',
    'amount' => '',
    'due_date' => '',
    'academic_year' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    foreach (array_keys($data) as $field) {
        $data[$field] = trim($_POST[$field] ?? '');
    }

    $studentId = filter_var($data['student_id'], FILTER_VALIDATE_INT);
    $amount = filter_var($data['amount'], FILTER_VALIDATE_FLOAT);

    $validDueDate = true;
    if ($data['due_date'] !== '') {
        $dateObject = DateTime::createFromFormat('Y-m-d', $data['due_date']);
        $validDueDate = $dateObject && $dateObject->format('Y-m-d') === $data['due_date'];
    }

    if (!$studentId || $data['fee_title'] === '') {
        $error = 'Student aur fee title select/enter karna zaroori hai.';
    } elseif ($amount === false || $amount <= 0) {
        $error = 'Fee amount zero se zyada hona chahiye.';
    } elseif (!$validDueDate) {
        $error = 'Due date valid nahi hai.';
    } else {
        $studentStmt = $pdo->prepare('SELECT id FROM students WHERE id = ?');
        $studentStmt->execute([$studentId]);

        if (!$studentStmt->fetch()) {
            $error = 'Selected student record nahi mila.';
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO fee_invoices
                    (student_id, fee_title, amount, due_date, academic_year)
                 VALUES (?, ?, ?, ?, ?)'
            );

            $stmt->execute([
                $studentId,
                $data['fee_title'],
                $amount,
                $data['due_date'] ?: null,
                $data['academic_year'] ?: null
            ]);

            header('Location: index.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Fee Invoice — School ERP</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <header class="topbar">
        <a class="logo" href="../dashboard.php">School ERP</a>
        <a class="button button-outline" href="index.php">Fees list</a>
    </header>

    <main class="container form-container">
        <section class="form-card">
            <h1>Create Fee Invoice</h1>
            <p class="muted">Student ke liye fee record banayein.</p>

            <?php if ($error): ?>
                <div class="alert"><?= e($error) ?></div>
            <?php endif; ?>

            <?php if (!$students): ?>
                <div class="alert">
                    Pehle Students module mein student add karein.
                </div>
            <?php else: ?>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">

                    <label for="student_id">Student *</label>
                    <select id="student_id" name="student_id" required>
                        <option value="">Student select karein</option>
                        <?php foreach ($students as $student): ?>
                            <?php
                            $studentLabel = $student['admission_no'] . ' — '
                                . $student['first_name'] . ' ' . $student['last_name']
                                . ' (' . $student['class_name']
                                . ($student['section'] ? '-' . $student['section'] : '')
                                . ')';
                            ?>
                            <option value="<?= (int) $student['id'] ?>"
                                <?= (string) $data['student_id'] === (string) $student['id'] ? 'selected' : '' ?>>
                                <?= e($studentLabel) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <div class="form-grid">
                        <div>
                            <label for="fee_title">Fee Title *</label>
                            <input
                                id="fee_title"
                                name="fee_title"
                                placeholder="Jaise: Term 1 Fee"
                                required
                                value="<?= e($data['fee_title']) ?>"
                            >
                        </div>

                        <div>
                            <label for="amount">Amount (₹) *</label>
                            <input
                                id="amount"
                                type="number"
                                name="amount"
                                min="0.01"
                                step="0.01"
                                required
                                value="<?= e($data['amount']) ?>"
                            >
                        </div>

                        <div>
                            <label for="due_date">Due Date</label>
                            <input
                                id="due_date"
                                type="date"
                                name="due_date"
                                value="<?= e($data['due_date']) ?>"
                            >
                        </div>

                        <div>
                            <label for="academic_year">Academic Year</label>
                            <input
                                id="academic_year"
                                name="academic_year"
                                placeholder="Jaise: 2025-2026"
                                value="<?= e($data['academic_year']) ?>"
                            >
                        </div>
                    </div>

                    <div class="form-actions">
                        <button class="button button-primary" type="submit">Create Invoice</button>
                        <a class="button button-outline" href="index.php">Cancel</a>
                    </div>
                </form>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>