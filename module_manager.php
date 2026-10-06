<?php
require __DIR__ . '/auth.php';

$modules = [
    'timetable' => [
        'table' => 'timetable_entries',
        'title' => 'Timetable',
        'singular' => 'Timetable Entry',
        'fields' => [
            'class_name' => ['label' => 'Class', 'type' => 'text', 'required' => true],
            'section' => ['label' => 'Section', 'type' => 'text'],
            'weekday' => ['label' => 'Day', 'type' => 'text', 'required' => true, 'placeholder' => 'Monday'],
            'start_time' => ['label' => 'Start Time', 'type' => 'time', 'required' => true],
            'end_time' => ['label' => 'End Time', 'type' => 'time', 'required' => true],
            'subject' => ['label' => 'Subject', 'type' => 'text', 'required' => true],
            'teacher_name' => ['label' => 'Teacher', 'type' => 'text'],
            'room' => ['label' => 'Room', 'type' => 'text'],
        ],
        'columns' => ['class_name', 'section', 'weekday', 'start_time', 'end_time', 'subject', 'teacher_name', 'room'],
    ],

    'homework' => [
        'table' => 'homework_assignments',
        'title' => 'Homework',
        'singular' => 'Homework Assignment',
        'fields' => [
            'class_name' => ['label' => 'Class', 'type' => 'text', 'required' => true],
            'section' => ['label' => 'Section', 'type' => 'text'],
            'subject' => ['label' => 'Subject', 'type' => 'text', 'required' => true],
            'title' => ['label' => 'Homework Title', 'type' => 'text', 'required' => true],
            'description' => ['label' => 'Instructions', 'type' => 'textarea'],
            'assigned_date' => ['label' => 'Assigned Date', 'type' => 'date'],
            'due_date' => ['label' => 'Due Date', 'type' => 'date'],
        ],
        'columns' => ['class_name', 'section', 'subject', 'title', 'description', 'assigned_date', 'due_date'],
    ],

    'library' => [
        'table' => 'library_books',
        'title' => 'Library',
        'singular' => 'Library Book',
        'fields' => [
            'accession_no' => ['label' => 'Accession Number', 'type' => 'text', 'required' => true],
            'title' => ['label' => 'Book Title', 'type' => 'text', 'required' => true],
            'author' => ['label' => 'Author', 'type' => 'text'],
            'category' => ['label' => 'Category', 'type' => 'text'],
            'total_copies' => ['label' => 'Total Copies', 'type' => 'number', 'required' => true],
            'shelf_location' => ['label' => 'Shelf Location', 'type' => 'text'],
        ],
        'columns' => ['accession_no', 'title', 'author', 'category', 'total_copies', 'shelf_location'],
    ],

    'transport' => [
        'table' => 'transport_routes',
        'title' => 'Transport',
        'singular' => 'Transport Route',
        'fields' => [
            'route_name' => ['label' => 'Route Name', 'type' => 'text', 'required' => true],
            'vehicle_no' => ['label' => 'Vehicle Number', 'type' => 'text'],
            'driver_name' => ['label' => 'Driver Name', 'type' => 'text'],
            'driver_phone' => ['label' => 'Driver Phone', 'type' => 'tel'],
            'pickup_area' => ['label' => 'Pickup Areas / Stops', 'type' => 'textarea'],
            'monthly_fee' => ['label' => 'Monthly Fee', 'type' => 'number', 'required' => true],
        ],
        'columns' => ['route_name', 'vehicle_no', 'driver_name', 'driver_phone', 'pickup_area', 'monthly_fee'],
    ],

    'notices' => [
        'table' => 'notices',
        'title' => 'Notices',
        'singular' => 'Notice',
        'fields' => [
            'title' => ['label' => 'Notice Title', 'type' => 'text', 'required' => true],
            'audience' => ['label' => 'Audience', 'type' => 'text', 'required' => true, 'placeholder' => 'Everyone / Students / Parents'],
            'publish_date' => ['label' => 'Publish Date', 'type' => 'date'],
            'expiry_date' => ['label' => 'Expiry Date', 'type' => 'date'],
            'status' => ['label' => 'Status', 'type' => 'text', 'required' => true, 'placeholder' => 'Published / Draft'],
            'message' => ['label' => 'Notice Message', 'type' => 'textarea', 'required' => true],
        ],
        'columns' => ['title', 'audience', 'publish_date', 'expiry_date', 'status', 'message'],
    ],
];

$moduleType = $moduleType ?? '';

if (!isset($modules[$moduleType])) {
    http_response_code(404);
    exit('Module nahi mila.');
}

$config = $modules[$moduleType];
$table = $config['table'];
$fields = $config['fields'];
$columns = $config['columns'];

$baseUrl = basename($_SERVER['PHP_SELF']);
$editId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$isEdit = $editId !== false && $editId !== null;

$data = [];
foreach ($fields as $field => $definition) {
    $data[$field] = '';
}

$error = '';

if ($isEdit) {
    $stmt = $pdo->prepare("SELECT * FROM `$table` WHERE id = ?");
    $stmt->execute([$editId]);
    $record = $stmt->fetch();

    if (!$record) {
        http_response_code(404);
        exit('Record nahi mila.');
    }

    foreach ($fields as $field => $definition) {
        $data[$field] = $record[$field] ?? '';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $deleteId = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);

        if ($deleteId) {
            $stmt = $pdo->prepare("DELETE FROM `$table` WHERE id = ?");
            $stmt->execute([$deleteId]);
        }

        header("Location: {$baseUrl}");
        exit;
    }

    foreach ($fields as $field => $definition) {
        $data[$field] = trim((string) ($_POST[$field] ?? ''));
    }

    foreach ($fields as $field => $definition) {
        if (!empty($definition['required']) && $data[$field] === '') {
            $error = $definition['label'] . ' zaroori hai.';
            break;
        }

        if ($data[$field] !== '' && $definition['type'] === 'number') {
            if (!is_numeric($data[$field]) || (float) $data[$field] < 0) {
                $error = $definition['label'] . ' valid, non-negative number hona chahiye.';
                break;
            }
        }

        if ($data[$field] !== '' && $definition['type'] === 'date') {
            $dateObject = DateTime::createFromFormat('Y-m-d', $data[$field]);

            if (!$dateObject || $dateObject->format('Y-m-d') !== $data[$field]) {
                $error = $definition['label'] . ' valid date nahi hai.';
                break;
            }
        }
    }

    if ($error === '') {
        $values = [];

        foreach ($fields as $field => $definition) {
            $values[] = $data[$field] === '' ? null : $data[$field];
        }

        try {
            if ($isEdit) {
                $setParts = [];

                foreach ($columns as $column) {
                    $setParts[] = "`$column` = ?";
                }

                $sql = "UPDATE `$table` SET " . implode(', ', $setParts) . ' WHERE id = ?';
                $stmt = $pdo->prepare($sql);
                $stmt->execute([...$values, $editId]);
            } else {
                $columnSql = implode(', ', array_map(
                    fn($column) => "`$column`",
                    $columns
                ));
                $placeholders = implode(', ', array_fill(0, count($columns), '?'));

                $sql = "INSERT INTO `$table` ($columnSql) VALUES ($placeholders)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($values);
            }

            header("Location: {$baseUrl}");
            exit;
        } catch (PDOException $e) {
            $error = $e->getCode() === '23000'
                ? 'Yeh record kisi existing unique value se duplicate ho raha hai.'
                : 'Record save nahi ho saka. Database setup check karein.';
        }
    }
}

$listStmt = $pdo->query("SELECT * FROM `$table` ORDER BY id DESC");
$records = $listStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($config['title']) ?> — School ERP</title>
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
                <p class="eyebrow">SCHOOL MANAGEMENT</p>
                <h1><?= e($config['title']) ?></h1>
                <p class="muted">Records add, edit aur delete karein.</p>
            </div>
           <div class="page-actions">
    <?php if ($moduleType === 'library'): ?>
        <a class="button button-outline" href="loans.php">Issue / Return Books</a>
    <?php endif; ?>

    <a class="button button-primary" href="<?= e($baseUrl) ?>?new=1">
        + Add <?= e($config['singular']) ?>
    </a>
</div>
        </div>

        <?php if ($error): ?>
            <div class="alert"><?= e($error) ?></div>
        <?php endif; ?>

        <?php if (isset($_GET['new']) || $isEdit || $error): ?>
            <section class="form-card module-form-card">
                <h2><?= $isEdit ? 'Edit ' : 'Add ' ?><?= e($config['singular']) ?></h2>

                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">

                    <div class="form-grid">
                        <?php foreach ($fields as $field => $definition): ?>
                            <div class="<?= $definition['type'] === 'textarea' ? 'form-full' : '' ?>">
                                <label for="<?= e($field) ?>">
                                    <?= e($definition['label']) ?>
                                    <?= !empty($definition['required']) ? '*' : '' ?>
                                </label>

                                <?php if ($definition['type'] === 'textarea'): ?>
                                    <textarea
                                        id="<?= e($field) ?>"
                                        name="<?= e($field) ?>"
                                        rows="4"
                                        <?= !empty($definition['required']) ? 'required' : '' ?>
                                    ><?= e($data[$field]) ?></textarea>
                                <?php else: ?>
                                    <input
                                        id="<?= e($field) ?>"
                                        type="<?= e($definition['type']) ?>"
                                        name="<?= e($field) ?>"
                                        value="<?= e($data[$field]) ?>"
                                        <?= !empty($definition['required']) ? 'required' : '' ?>
                                        <?= isset($definition['placeholder'])
                                            ? 'placeholder="' . e($definition['placeholder']) . '"'
                                            : '' ?>
                                        <?= $definition['type'] === 'number' ? 'min="0" step="0.01"' : '' ?>
                                    >
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="form-actions">
                        <button class="button button-primary" type="submit">
                            <?= $isEdit ? 'Update' : 'Save' ?>
                        </button>
                        <a class="button button-outline" href="<?= e($baseUrl) ?>">Cancel</a>
                    </div>
                </form>
            </section>
        <?php endif; ?>

        <section class="table-card module-list-card">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <?php foreach ($columns as $column): ?>
                                <th><?= e($fields[$column]['label']) ?></th>
                            <?php endforeach; ?>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$records): ?>
                            <tr>
                                <td colspan="<?= count($columns) + 1 ?>" class="empty-state">
                                    Abhi koi record nahi hai.
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($records as $record): ?>
                            <tr>
                                <?php foreach ($columns as $column): ?>
                                    <td class="module-cell">
                                        <?= nl2br(e($record[$column] ?? '—')) ?>
                                    </td>
                                <?php endforeach; ?>
                                <td class="actions">
                                    <a
                                        class="button button-outline button-small"
                                        href="<?= e($baseUrl) ?>?id=<?= (int) $record['id'] ?>"
                                    >Edit</a>

                                    <form
                                        method="POST"
                                        onsubmit="return confirm('Kya aap is record ko delete karna chahte hain?');"
                                    >
                                        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= (int) $record['id'] ?>">
                                        <button class="button button-danger button-small" type="submit">
                                            Delete
                                        </button>
                                    </form>
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