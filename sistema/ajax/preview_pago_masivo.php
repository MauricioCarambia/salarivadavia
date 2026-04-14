<?php
require_once '../inc/db.php';

$id = (int) $_GET['paciente_id'];

$stmt = $conexion->prepare("
    SELECT 
        Id,
        apellido,
        nombre,
        nro_afiliado
    FROM pacientes
    WHERE SUBSTRING_INDEX(SUBSTRING_INDEX(nro_afiliado, '/', 1), ' ', 1) = (
        SELECT SUBSTRING_INDEX(SUBSTRING_INDEX(nro_afiliado, '/', 1), ' ', 1)
        FROM pacientes
        WHERE Id = :id
    )
");

$stmt->execute([':id' => $id]);
$pacientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($pacientes);