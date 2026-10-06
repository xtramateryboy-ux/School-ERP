<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$modules = [
       ['Students', 'Student records aur profiles', '👨‍🎓', 'students/index.php'],
   ['Teachers', 'Teacher information', '👩‍🏫', 'teachers/index.php'],
    ['Attendance', 'Daily attendance records', '📋', 'attendance/index.php'],
['Fees', 'Fee records aur payments', '💳', 'fees/index.php'],
    ['Exams', 'Exams aur results', '📝', 'exams/index.php'],
   ['Timetable', 'Class schedules', '🗓️', 'timetable/index.php'],
['Homework', 'Homework assignments', '📚', 'homework/index.php'],
['Library', 'Book catalog', '📖', 'library/index.php'],
['Transport', 'Routes and vehicle details', '🚌', 'transport/index.php'],
['Notices', 'School announcements', '📢', 'notices/index.php'],
];
?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — School ERP</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header class="topbar">
        <a class="logo" href="dashboard.php">School ERP</a>

        <div class="user-area">
            <span>
                <?= e($_SESSION['name']) ?>
                <small class="role-badge"><?= e(ucfirst($_SESSION['role'])) ?></small>
            </span>
            <a class="button button-outline" href="logout.php">Logout</a>
        </div>
    </header>

    <main class="container">
        <section class="welcome">
            <p class="eyebrow">SCHOOL MANAGEMENT SYSTEM</p>
            <h1>Welcome, <?= e($_SESSION['name']) ?>!</h1>
            <p class="muted">Dashboard se apne school ke modules access karein.</p>
        </section>

        <section class="module-grid">
    <?php foreach ($modules as $module): ?>
        <article class="module-card">
            <div class="module-icon"><?= $module[2] ?></div>
            <h2><?= e($module[0]) ?></h2>
            <p><?= e($module[1]) ?></p>
            <a class="module-link" href="<?= e($module[3]) ?>">
                <?= $module[3] === '#' ? 'Coming soon' : 'Open module →' ?>
            </a>
        </article>
    <?php endforeach; ?>
</section>

    </main>
</body>
</html>