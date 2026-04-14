<?php
require_once "../inc/db.php";

header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);

$id = $data['id'] ?? null;
$practica = $data['practica_id'] ?? null;
$profesional = $data['profesional_id'] ?: null;
$reglas = $data['reglas'] ?? [];
$tipoPaciente = $data['tipo_paciente'] ?? null;

try {

    /* =========================
       VALIDACIONES
    ========================= */
    if (!$practica) {
        throw new Exception("Debe seleccionar una práctica");
    }

    if (!$tipoPaciente) {
        throw new Exception("Debe seleccionar tipo de paciente");
    }

    if (empty($reglas)) {
        throw new Exception("Debe agregar al menos una regla");
    }

    $pdo->beginTransaction();

    /* =========================
       CABECERA
    ========================= */
    if ($id) {

        $stmt = $pdo->prepare("
            UPDATE practicas_reparto 
            SET practica_id = ?, profesional_id = ?, tipo_paciente = ?
            WHERE id = ?
        ");
        $stmt->execute([$practica, $profesional, $tipoPaciente, $id]);

        /* borrar reglas anteriores */
        $stmt = $pdo->prepare("
            DELETE FROM practicas_reparto_detalle 
            WHERE reparto_id = ?
        ");
        $stmt->execute([$id]);
    } else {

        $stmt = $pdo->prepare("
            INSERT INTO practicas_reparto 
            (practica_id, profesional_id, tipo_paciente) 
            VALUES (?,?,?)
        ");
        $stmt->execute([$practica, $profesional, $tipoPaciente]);

        $id = $pdo->lastInsertId();
    }

    /* =========================
       INSERTAR REGLAS
    ========================= */
    $orden = 1;

    foreach ($reglas as $r) {

        $tipoId = isset($r['tipo_id']) ? (int)$r['tipo_id'] : 0;
        $destinoId = isset($r['destino_id']) ? (int)$r['destino_id'] : 0;
        $valor = isset($r['valor']) ? (float)$r['valor'] : 0;

        /* validar */
        if (!$tipoId || !$destinoId) {
            throw new Exception("Regla inválida");
        }

        if (!is_numeric($valor)) {
            throw new Exception("Valor inválido en reglas");
        }

        /* validar tipo exista */
        $stmtCheck = $pdo->prepare("
            SELECT id FROM tipos_reparto WHERE id = ?
        ");
        $stmtCheck->execute([$tipoId]);

        if (!$stmtCheck->fetch()) {
            throw new Exception("Tipo inválido");
        }

        /* validar destino exista */
        $stmtCheck = $pdo->prepare("
            SELECT id FROM destinos_reparto WHERE id = ?
        ");
        $stmtCheck->execute([$destinoId]);

        if (!$stmtCheck->fetch()) {
            throw new Exception("Destino inválido");
        }

        /* insertar */
        $stmt = $pdo->prepare("
            INSERT INTO practicas_reparto_detalle
            (reparto_id, tipo_id, destino_id, valor, orden)
            VALUES (?,?,?,?,?)
        ");

        $stmt->execute([
            $id,
            $tipoId,
            $destinoId,
            $valor,
            $orden++
        ]);
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'id' => $id
    ]);
} catch (Exception $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
