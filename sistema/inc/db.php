<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('America/Argentina/Buenos_Aires');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/errors.php';

/* ==========================================
   CONEXION PDO PROFESIONAL
========================================== */

$host    = env('DB_HOST', 'localhost');
$db      = env('DB_NAME', 'sala');
$user    = env('DB_USER', 'root');
$pass    = env('DB_PASS', '');
$charset = env('DB_CHARSET', 'utf8mb4');

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {

    $pdo = new PDO($dsn, $user, $pass, $options);
    $pdo->exec("SET time_zone = '-03:00'");

} catch (PDOException $e) {

    die("Error de conexión: " . $e->getMessage());

}


