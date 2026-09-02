<?php
/**
 * Database connection (PDO)
 * BAMBOSA Irrigation Management Platform
 */

$DB_HOST = 'localhost';
$DB_NAME = 'bambosa_db';
$DB_USER = 'root';       // change for your XAMPP / server setup
$DB_PASS = '';           // change for your XAMPP / server setup
$DB_CHARSET = 'utf8mb4';

$dsn = "mysql:host={$DB_HOST};dbname={$DB_NAME};charset={$DB_CHARSET}";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $options);
} catch (PDOException $e) {
    // Never expose raw DB errors to end users in production
    error_log('DB Connection Error: ' . $e->getMessage());
    die('Database connection failed. Please try again later.');
}
