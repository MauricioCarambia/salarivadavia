<?php
require_once __DIR__ . '/../inc/db.php';

header('Content-Type: application/json');

$id = $_POST['id'] ?? 0;

if(!$id){
    echo json_encode(['success'=>false,'message'=>'ID inválido']);
    exit;
}

// Usamos NOW() directamente en el SQL para que la base de datos asigne el timestamp actual
$stmt = $conexion->prepare("
    UPDATE lista_espera SET
        nombre = :nombre,
        apellido = :apellido,
        celular = :celular,
        edad = :edad,
        derivacion = :derivacion,
        horario = :horario,
        profesional = :profesional,
        nota = :nota,
        asignado = :asignado,
        created_date = NOW()
    WHERE Id = :id
");

$ok = $stmt->execute([
    ':nombre'=>$_POST['nombre'],
    ':apellido'=>$_POST['apellido'],
    ':celular'=>$_POST['celular'],
    ':edad'=>$_POST['edad'],
    ':derivacion'=>$_POST['derivacion'],
    ':horario'=>$_POST['horario'],
    ':profesional'=>$_POST['profesional'],
    ':nota'=>$_POST['nota'],
    ':asignado'=>$_POST['asignado'],
    ':id'=>$id
]);

echo json_encode(['success'=>$ok]);