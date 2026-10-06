<?php
require __DIR__ . '/../config/auth.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$isEdit = $id !== false && $id !== null;

$teacher = [
    'employee_no' => '',
    'first_name' => '',
    'last_name' => '',
    'email' => '',
    'phone' => '',
    'subject' => '',
    'qualification' => '',
    'hire_date' => '',
    'address' => '',
];

if ($isEdit) {
    $stmt = $pdo->prepare('SELECT * FROM teachers WHERE id = ?');
    $stmt->execute([$id]);
    $existingTeacher = $stmt->fetch();

    if (!$existingTeacher) {
        http_response_code(404);
        exit('Teacher record nahi mila.');
    }

    $teacher = $existingTeacher;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    foreach (array_keys($teacher) as $field) {
        $teacher[$field] = trim($_POST[$field] ?? '');
    }

    if (
        $teacher['employee_no'] === '' ||
        $teacher['first_name'] === '' ||
        $teacher['last_name'] === '' ||
        $teacher['email'] === ''
    ) {
        $error = 'Employee number, first name, last name aur email zaroori hain.';
    } elseif (!filter_var($teacher['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Email address valid nahi hai.';
    } else {
        try {
            if ($isEdit) {
                $stmt = $pdo->prepare(
                    'UPDATE teachers SET
                        employee_no = ?,
                        first_name = ?,
                        last_name = ?,
                        email = ?,
                        phone = ?,
                        subject = ?,
                        qualification = ?,
                        hire_date = ?,
                        address = ?
                     WHERE id = ?'
                );

                $stmt->execute([
                    $teacher['employee_no'],
                    $teacher['first_name'],
                    $teacher['last_name'],
                    $teacher['email'],
                    $teacher['phone'] ?: null,
                    $teacher['subject'] ?: null,
                    $teacher['qualification'] ?: null,
                    $teacher['hire_date'] ?: null,
                    $teacher['address'] ?: null,
                    $id
                ]);
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO teachers
                        (employee_no, first_name, last_name, email, phone,
                         subject, qualification, hire_date, address)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );

                $stmt->execute([
                    $teacher['employee_no'],
                    $teacher['first_name'],
                    $teacher['last_name'],
                    $teacher['email'],
                    $teacher['phone'] ?: null,
                    $teacher['subject'] ?: null,
                    $teacher['qualification'] ?: null,
                    $teacher['hire_date'] ?: null,
                    $teacher['address'] ?: null
                ]);
            }

            header('Location: index.php');
            exit;
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $error = 'Employee number ya email pehle se registered hai.';
            } else {
                $error = 'Teacher save nahi ho saka. Dobara try karein.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $isEdit ? 'Edit' : 'Add' ?> Teacher — School ERP</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <header class="topbar">
        <a class="logo" href="../dashboard.php">School ERP</a>
        <a class="button button-outline" href="index.php">Teachers list</a>
    </header>

    <main class="container form-container">
        <section class="form-card">
            <h1><?= $isEdit ? 'Edit Teacher' : 'Add Teacher' ?></h1>
            <p class="muted">Teacher ki details fill karein.</p>

            <?php if ($error): ?>
                <div class="alert"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">

                <div class="form-grid">
                    <div>
                        <label for="employee_no">Employee Number *</label>
                        <input
                            id="employee_no"
                            name="employee_no"
                            required
                            value="<?= e($teacher['employee_no']) ?>"
                        >
                    </div>

                    <div>
                        <label for="email">Email *</label>
                        <input
                            id="email"
                            type="email"
                            name="email"
                            required
                            value="<?= e($teacher['email']) ?>"
                        >
                    </div>

                    <div>
                        <label for="first_name">First Name *</label>
                        <input
                            id="first_name"
                            name="first_name"
                            required
                            value="<?= e($teacher['first_name']) ?>"
                        >
                    </div>

                    <div>
                        <label for="last_name">Last Name *</label>
                        <input
                            id="last_name"
                            name="last_name"
                            required
                            value="<?= e($teacher['last_name']) ?>"
                        >
                    </div>

                    <div>
                        <label for="subject">Subject</label>
                        <input
                            id="subject"
                            name="subject"
                            placeholder="Jaise: Mathematics"
                            value="<?= e($teacher['subject']) ?>"
                        >
                    </div>

                    <div>
                        <label for="phone">Phone</label>
                        <input
                            id="phone"
                            type="tel"
                            name="phone"
                            value="<?= e($teacher['phone']) ?>"
                        >
                    </div>

                    <div>
                        <label for="qualification">Qualification</label>
                        <input
                            id="qualification"
                            name="qualification"
                            placeholder="Jaise: B.Ed, M.Sc"
                            value="<?= e($teacher['qualification']) ?>"
                        >
                    </div>

                    <div>
                        <label for="hire_date">Joining Date</label>
                        <input
                            id="hire_date"
                            type="date"
                            name="hire_date"
                            value="<?= e($teacher['hire_date']) ?>"
                        >
                    </div>

                    <div class="form-full">
                        <label for="address">Address</label>
                        <textarea id="address" name="address" rows="3"><?= e($teacher['address']) ?></textarea>
                    </div>
                </div>

                <div class="form-actions">
                    <button class="button button-primary" type="submit">
                        <?= $isEdit ? 'Update Teacher' : 'Save Teacher' ?>
                    </button>
                    <a class="button button-outline" href="index.php">Cancel</a>
                </div>
            </form>
        </section>
    </main>
</body>
</html>