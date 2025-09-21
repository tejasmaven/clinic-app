<?php
// config/config.php

if($_SERVER['HTTP_HOST'] == 'localhost:8081')
{
    define('BASE_PATH', '/clinic-app'); // Points to /clinic-app
}
elseif($_SERVER['HTTP_HOST'] == 'hiral.tejaspmehta.com')
{
     define('BASE_PATH', '/'); 
}
else
{
    define('BASE_PATH', '/'); // Points to /clinic-app
}

// Determine the base URL path for links regardless of deployment folder
$docRoot = $_SERVER['HTTP_HOST'] ?? '';
$baseUri = $docRoot ? str_replace($docRoot, '', BASE_PATH) : '';
define('BASE_URL', rtrim($baseUri, '/'));

if($_SERVER['HTTP_HOST'] == 'localhost:8081')
{
    $host = 'localhost';
    $db   = 'physio_clinic';
    $user = 'root';
    $pass = '';
    $charset = 'utf8mb4';
}
elseif($_SERVER['HTTP_HOST'] == 'hiral.tejaspmehta.com')
{
    $host = 'localhost';
    $db   = 'tejasxfc_hiral';
    $user = 'tejasxfc_hiralusr';
    $pass = 'SY#u3U(HMu';
    $charset = 'utf8mb4';
}
else
{
    $host = 'localhost';
    $db   = 'physio_clinic';
    $user = 'root';
    $pass = '';
    $charset = 'utf8mb4';
}


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
