<?php
require __DIR__ . '/config/database.php';

$email = 'admin@school.com';

$stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
$stmt->execute([$email]);

if ($stmt->fetch()) {
    exit('Admin account pehle se maujood hai. Ab setup.php delete kar dein.');
}

$passwordHash = password_hash('Admin@123', PASSWORD_DEFAULT);

$stmt = $pdo->prepare(
    'INSERT INTO users (name, email, password_hash, role, status)
     VALUES (?, ?, ?, ?, ?)'
);

$stmt->execute([
    'School Administrator',
    $email,
    $passwordHash,
    'admin',
    'active'
]);

echo 'Admin account ban gaya.<br>';
echo 'Email: admin@school.com<br>';
echo 'Password: Admin@123<br><br>';
echo 'Security ke liye ab setup.php file delete kar dein.';