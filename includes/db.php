<?php
/**
 * Database bootstrap. Every page includes this first.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/security_headers.php';

send_security_headers();

if (FORCE_HTTPS && php_sapi_name() !== 'cli'
    && empty($_SERVER['HTTPS']) && empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
    header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], true, 301);
    exit;
}

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    die('Database connection failed. Start MySQL in the XAMPP control panel and '
        . 'import database/schema.sql to create the "' . DB_NAME . '" database.');
}

require_once __DIR__ . '/functions.php';
