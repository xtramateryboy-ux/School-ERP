<?php

$host = 'localhost';
$dbname = 'school_erp';
$username = 'root';
$password = ''; // XAMPP mein default password aam taur par blank hota hai

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    exit('Database connection failed. XAMPP mein MySQL start karke settings check karein.');
}

