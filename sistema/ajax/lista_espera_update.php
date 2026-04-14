<?php
require_once '../inc/db.php';

header('Content-Type: application/json');

$id = $_POST['id'] ?? 0;

if(!$id){
    echo json_encode(['success'=>false,'message'=>'ID inválido']);
    exit;
}

$stmt = $conexion->prepare("
    UPDATE lista_espera SET
        nombre = :nombre,
        apellido = :apellido,
        celular = :celular,
        edad = :edad,
        horario = :horario,
        profesional = :profesional,
        asignado = :asignado
    WHERE Id = :id
");

$ok = $stmt->execute([
    ':nombre'=>$_POST['nombre'],
    ':apellido'=>$_POST['apellido'],
    ':celular'=>$_POST['celular'],
    ':edad'=>$_POST['edad'],
    ':horario'=>$_POST['horario'],
    ':profesional'=>$_POST['profesional'],
    ':asignado'=>$_POST['asignado'],
    ':id'=>$id
]);

echo json_encode(['success'=>$ok]);