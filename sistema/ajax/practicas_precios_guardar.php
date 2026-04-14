<?php
session_start();
require_once "../inc/db.php";

$id = $_POST['id'] ?? null;
$practica = $_POST['practica_id'];
$tipo = $_POST['tipo_paciente'];
$precio = $_POST['precio'];
$activo = $_POST['activo'] ?? 1;

try {

    if ($id) {
        $stmt = $pdo->prepare("
            UPDATE practicas_precios
            SET practica_id=?, tipo_paciente=?, precio=?, activo=?
            WHERE id=?
        ");
        $stmt->execute([$practica,$tipo,$precio,$activo,$id]);

    } else {
        $stmt = $pdo->prepare("
            INSERT INTO practicas_precios (practica_id,tipo_paciente,precio)
            VALUES (?,?,?)
        ");
        $stmt->execute([$practica,$tipo,$precio]);
    }

    echo json_encode(['success'=>true]);

} catch(Exception $e){
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}