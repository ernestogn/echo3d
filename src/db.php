<?php
// Simple PDO database connection

// Load configuration from environment variables or fallback values
$dsn = getenv('DB_DSN') ?: 'mysql:host=localhost;dbname=your_db;charset=utf8mb4';
$username = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASSWORD') ?: '';

try {
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    // If connection fails, halt script
    die('Database connection error: ' . $e->getMessage());
}
?>