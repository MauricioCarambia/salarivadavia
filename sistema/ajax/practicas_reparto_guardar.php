<?php
require_once __DIR__ . '/../inc/db.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);

$id = $data['id'] ?? null;
$practica = $data['practica_id'] ?? null;
$profesional_ids = $data['profesional_ids'] ?? [];
$reglas = $data['reglas'] ?? [];
$tipoPaciente = $data['tipo_paciente'] ?? null;

try {

    $pdo->beginTransaction();

    $stmtInsertReparto = $pdo->prepare("
        INSERT INTO practicas_reparto (practica_id, profesional_id, tipo_paciente)
        VALUES (?, ?, ?)
    ");

    $stmtInsertDetalle = $pdo->prepare("
        INSERT INTO practicas_reparto_detalle
        (reparto_id, tipo_id, destino_id, valor)
        VALUES (?, ?, ?, ?)
    ");

    foreach ($profesional_ids as $profesional_id) {

        $profesional_id = (int)$profesional_id;

        if ($profesional_id <= 0) {
            continue;
        }

        // =========================
        // INSERT CABECERA
        // =========================
        $stmtInsertReparto->execute([
            $data['practica_id'],
            $profesional_id,
            $data['tipo_paciente']
        ]);

        $reparto_id = $pdo->lastInsertId();

        // =========================
        // VALIDAR REGLAS
        // =========================
        if (!empty($data['reglas'])) {

            foreach ($data['reglas'] as $regla) {

                $tipoId = (int)($regla['tipo_id'] ?? 0);
                $destinoId = (int)($regla['destino_id'] ?? 0);
                $valor = (float)($regla['valor'] ?? 0);

                if (!$tipoId || !$destinoId) {
                    throw new Exception("Regla inválida");
                }

                if (!is_numeric($valor)) {
                    throw new Exception("Valor inválido en regla");
                }

                $stmtInsertDetalle->execute([
                    $reparto_id,
                    $tipoId,
                    $destinoId,
                    $valor
                ]);
            }
        }
    }

    $pdo->commit();

    echo json_encode([
        'success' => true
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