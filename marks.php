<?php
require __DIR__ . '/../config/auth.php';

$examId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$examId) {
    header('Location: index.php');
    exit;
}

$examStmt = $pdo->prepare('SELECT * FROM exams WHERE id = ?');
$examStmt->execute([$examId]);
$exam = $examStmt->fetch();

if (!$exam) {
    http_response_code(404);
    exit('Exam record nahi mila.');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $marksInput = $_POST['marks'] ?? [];

    $studentStmt = $pdo->prepare(
        "SELECT id FROM students
         WHERE class_name = ?
           AND COALESCE(section, '') = ?"
    );
    $studentStmt->execute([
        $exam['class_name'],
        $exam['section'] ?? ''
    ]);

    $validStudentIds = array_map('intval', $studentStmt->fetchAll(PDO::FETCH_COLUMN));
    $validStudentLookup = array_flip($validStudentIds);

    $toSave = [];
    $invalidMarks = false;

    foreach ($marksInput as $studentId => $rawMarks) {
        $studentId = filter_var($studentId, FILTER_VALIDATE_INT);

        // Form mein blank chhoda gaya ho to us student ka result unchanged rahega.
        if ($rawMarks === '') {
            continue;
        }

        if (!$studentId || !isset($validStudentLookup[$studentId])) {
            $invalidMarks = true;
            break;
        }

        $marks = filter_var($rawMarks, FILTER_VALIDATE_FLOAT);

        if (
            $marks === false ||
            $marks < 0 ||
            $marks > (float) $exam['max_marks']
        ) {
            $invalidMarks = true;
            break;
        }

        $toSave[] = [$studentId, $marks];
    }

    if ($invalidMarks) {
        $error = 'Marks valid hone chahiye aur maximum marks se zyada nahi ho sakte.';
    } else {
        try {
            $pdo->beginTransaction();

            $saveStmt = $pdo->prepare(
                'INSERT INTO exam_results (exam_id, student_id, marks_obtained)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE marks_obtained = ?'
            );

            foreach ($toSave as [$studentId, $marks]) {
                $saveStmt->execute([
                    $examId,
                    $studentId,
                    $marks,
                    $marks
                ]);
            }

            $pdo->commit();

            header('Location: marks.php?id=' . $examId . '&saved=1');
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $error = 'Marks save nahi ho sake. Dobara try karein.';
        }
    }
}

$studentsStmt = $pdo->prepare(
    "SELECT
        s.id,
        s.admission_no,
        s.first_name,
        s.last_name,
        r.marks_obtained
     FROM students s
     LEFT JOIN exam_results r
        ON r.student_id = s.id
       AND r.exam_id = ?
     WHERE s.class_name = ?
       AND COALESCE(s.section, '') = ?
     ORDER BY s.first_name, s.last_name"
);

$studentsStmt->execute([
    $examId,
    $exam['class_name'],
    $exam['section'] ?? ''
]);

$students = $studentsStmt->fetchAll();
$saved = isset($_GET['saved']) && $_GET['saved'] === '1';
?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enter Marks — School ERP</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <header class="topbar">
        <a class="logo" href="../dashboard.php">School ERP</a>
        <a class="button button-outline" href="index.php">Exams list</a>
    </header>

    <main class="container">
        <div class="page-heading">
            <div>
                <p class="eyebrow">EXAM RESULTS</p>
                <h1><?= e($exam['exam_name']) ?></h1>
                <p class="muted">
                    <?= e($exam['class_name']) ?>
                    <?= $exam['section'] ? ' - Section ' . e($exam['section']) : '' ?>
                    · <?= e($exam['subject']) ?>
                    · Maximum marks: <?= e($exam['max_marks']) ?>
                </p>
            </div>
        </div>

        <?php if ($saved): ?>
            <div class="success-message">Marks successfully save ho gaye.</div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <section class="table-card">
            <?php if (!$students): ?>
                <div class="empty-state">Is class/section mein koi student nahi mila.</div>
            <?php else: ?>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">

                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Admission No.</th>
                                    <th>Student Name</th>
                                    <th>Marks / <?= e($exam['max_marks']) ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $student): ?>
                                    <tr>
                                        <td><?= e($student['admission_no']) ?></td>
                                        <td><?= e($student['first_name'] . ' ' . $student['last_name']) ?></td>
                                        <td>
                                            <input
                                                class="marks-input"
                                                type="number"
                                                name="marks[<?= (int) $student['id'] ?>]"
                                                min="0"
                                                max="<?= e($exam['max_marks']) ?>"
                                                step="0.01"
                                                value="<?= $student['marks_obtained'] !== null
                                                    ? e($student['marks_obtained'])
                                                    : '' ?>"
                                                placeholder="Marks enter karein"
                                            >
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <p class="muted form-hint">
                        Note: Marks khaali chhodne par us student ka pehle se saved result change nahi hoga.
                    </p>

                    <div class="form-actions">
                        <button class="button button-primary" type="submit">Save Marks</button>
                    </div>
                </form>
            <?php endif; ?>
        </section>

        <p class="back-link"><a href="index.php">← Exams list par wapas jaayein</a></p>
    </main>
</body>
</html>