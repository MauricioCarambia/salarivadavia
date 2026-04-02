<?php
require_once "../inc/db.php";

header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);

$id = $data['id'] ?? null;
$practica = $data['practica_id'] ?? null;
$profesional = $data['profesional_id'] ?: null;
$reglas = $data['reglas'] ?? [];
$tipoPaciente = $data['tipo_paciente'];
try {

    if(!$practica){
        throw new Exception("Debe seleccionar una práctica");
    }

    if(empty($reglas)){
        throw new Exception("Debe agregar al menos una regla");
    }

    $pdo->beginTransaction();

    if($id){

        /* ✏️ ACTUALIZAR CABECERA */
        $stmt = $pdo->prepare("
            UPDATE practicas_reparto 
            SET practica_id = ?, profesional_id = ?
            WHERE id = ?
        ");
        $stmt->execute([$practica, $profesional, $id]);

        /* 🔄 BORRAR DETALLE */
        $stmt = $pdo->prepare("
            DELETE FROM practicas_reparto_detalle 
            WHERE reparto_id = ?
        ");
        $stmt->execute([$id]);

    } else {

        /* ➕ CREAR NUEVO */
      $stmt = $pdo->prepare("
    INSERT INTO practicas_reparto 
    (practica_id, profesional_id, tipo_paciente) 
    VALUES (?,?,?)
");
$stmt->execute([$practica, $profesional, $tipoPaciente]);

        $id = $pdo->lastInsertId();
    }

    /* ➕ INSERTAR REGLAS */
    $orden = 1;

    foreach($reglas as $r){

        $tipo = $r['tipo'] ?? null;
        $destino = $r['destino'] ?? null;
        $valor = $r['valor'] ?? 0;

        if(!$tipo || !$destino){
            throw new Exception("Regla inválida");
        }

        $stmt = $pdo->prepare("
            INSERT INTO practicas_reparto_detalle
            (reparto_id, tipo, destino, valor, orden)
            VALUES (?,?,?,?,?)
        ");

        $stmt->execute([
            $id,
            $tipo,
            $destino,
            $valor,
            $orden++
        ]);
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'id' => $id
    ]);

} catch(Exception $e){

    if($pdo->inTransaction()){
        $pdo->rollBack();
    }

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}