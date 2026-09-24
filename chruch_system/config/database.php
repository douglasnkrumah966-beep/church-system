<?php
/* ============================================================
   DATABASE CONFIGURATION
   ============================================================ */

// Force HTTP on localhost (fixes Chrome HTTPS auto-upgrade)
$_SERVER['HTTPS'] = 'off';
$_SERVER['REQUEST_SCHEME'] = 'http';
$_SERVER['HTTP_X_FORWARDED_PROTO'] = 'http';

// Start session with security
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.use_strict_mode', 1);
    session_start();
}

$DB_HOST = 'localhost';
$DB_NAME = 'church_db';
$DB_USER = 'root';
$DB_PASS = '';

try {
    $pdo = new PDO(
        "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Load settings globally
$settings = $pdo->query("SELECT * FROM settings WHERE id=1")->fetch();

if (!$settings || !is_array($settings)) {
    $settings = [
        'church_name' => 'Roman Catholic Church',
        'currency'    => 'GHS',
        'timezone'    => 'Africa/Accra',
    ];
}

// Apply timezone from settings
if (!empty($settings['timezone'])) {
    date_default_timezone_set($settings['timezone']);
}

// Load shared functions
require_once __DIR__ . '/../includes/functions.php';