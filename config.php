<?php
$host = 'sql110.infinityfree.com';
$db = 'if0_39848429_db_student';
$user = 'if0_39848429';
$pass = 'n13mnshrma';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    exit("Database connection failed: " . $e->getMessage());
}
