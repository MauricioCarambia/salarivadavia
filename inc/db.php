<?php
date_default_timezone_set('America/Argentina/Buenos_Aires');
/* ==========================================
   CONEXION PDO PROFESIONAL
========================================== */

$host = "localhost";
$db   = "sala";
$user = "root";
$pass = "";
$charset = "utf8mb4";

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {

    $pdo = new PDO($dsn, $user, $pass, $options);
   $conexion = $pdo;
   $conexion->exec("SET time_zone = '-03:00'");

} catch (PDOException $e) {

    die("Error de conexión: " . $e->getMessage());

}