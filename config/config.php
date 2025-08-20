<?php
// config/config.php
define('BASE_PATH', dirname(__DIR__)); // Points to /clinic-app

// Determine the base URL path for links regardless of deployment folder
$docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
$baseUri = $docRoot ? str_replace($docRoot, '', BASE_PATH) : '';
define('BASE_URL', rtrim($baseUri, '/'));

$host = 'localhost';
$db   = 'physio_clinic';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die('Database Connection Failed: ' . $e->getMessage());
}
?>
