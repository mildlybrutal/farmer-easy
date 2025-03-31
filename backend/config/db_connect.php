<?php
$host = 'localhost';
$dbname = 'farmers_portal';
$username = 'root';  // your MySQL username
$password = '';      // your MySQL password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $pdo;
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
} 