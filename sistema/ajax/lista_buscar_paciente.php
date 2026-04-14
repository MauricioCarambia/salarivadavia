<?php
require_once '../inc/db.php';

$dni = $_POST['dni'] ?? '';

$stmt = $conexion->prepare("
    SELECT nombre, apellido, celular
    FROM pacientes
    WHERE documento = :dni
    LIMIT 1
");

$stmt->execute([':dni'=>$dni]);

$p = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode([
    'encontrado' => $p ? true : false,
    'nombre' => $p['nombre'] ?? '',
    'apellido' => $p['apellido'] ?? '',
    'celular' => $p['celular'] ?? ''
]);