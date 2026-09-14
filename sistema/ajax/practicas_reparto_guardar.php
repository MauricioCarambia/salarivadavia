<?php
require_once __DIR__ . '/../inc/session.php';

if (empty($_SESSION['login']) || $_SESSION['login'] !== 'si') {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

require_once __DIR__ . '/../inc/csrf.php';
requerirCsrf();
require_once __DIR__ . '/../inc/db.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

$id = isset($data['id']) && $data['id'] !== ''
    ? (int)$data['id']
    : 0;

$practica_id = isset($data['practica_id'])
    ? (int)$data['practica_id']
    : 0;

$profesional_ids = $data['profesional_ids'] ?? [];

$tipo_paciente = $data['tipo_paciente'] ?? '';

$reglas = $data['reglas'] ?? [];

try {

    $pdo->beginTransaction();

    // =====================================================
    // EDITAR
    // =====================================================
    if ($id > 0) {

        $profesional_id = isset($profesional_ids[0])
            ? (int)$profesional_ids[0]
            : 0;

        if ($profesional_id <= 0) {
            throw new Exception('Profesional inválido');
        }

        $stmt = $pdo->prepare("
            UPDATE practicas_reparto
            SET
                practica_id = ?,
                profesional_id = ?,
                tipo_paciente = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $practica_id,
            $profesional_id,
            $tipo_paciente,
            $id
        ]);

        // borrar reglas actuales
        $stmt = $pdo->prepare("
            DELETE FROM practicas_reparto_detalle
            WHERE reparto_id = ?
        ");

        $stmt->execute([$id]);

        $reparto_id = $id;
    }

    // =====================================================
    // NUEVO
    // =====================================================
    else {

        $stmtInsertReparto = $pdo->prepare("
            INSERT INTO practicas_reparto
            (
                practica_id,
                profesional_id,
                tipo_paciente
            )
            VALUES (?, ?, ?)
        ");

        foreach ($profesional_ids as $profesional_id) {

            $profesional_id = (int)$profesional_id;

            if ($profesional_id <= 0) {
                continue;
            }

            $stmtInsertReparto->execute([
                $practica_id,
                $profesional_id,
                $tipo_paciente
            ]);

            $reparto_id = $pdo->lastInsertId();

            if (!empty($reglas)) {

                $stmtDetalle = $pdo->prepare("
                    INSERT INTO practicas_reparto_detalle
                    (
                        reparto_id,
                        tipo_id,
                        destino_id,
                        valor
                    )
                    VALUES (?, ?, ?, ?)
                ");

                foreach ($reglas as $regla) {

                    $tipo_id = (int)($regla['tipo_id'] ?? 0);
                    $destino_id = (int)($regla['destino_id'] ?? 0);
                    $valor = (float)($regla['valor'] ?? 0);

                    if ($tipo_id <= 0 || $destino_id <= 0) {
                        continue;
                    }

                    $stmtDetalle->execute([
                        $reparto_id,
                        $tipo_id,
                        $destino_id,
                        $valor
                    ]);
                }
            }
        }

        $pdo->commit();

        echo json_encode([
            'success' => true
        ]);
        exit;
    }

    // =====================================================
    // REINSERTAR REGLAS EN EDICIÓN
    // =====================================================
    if (!empty($reglas)) {

        $stmtDetalle = $pdo->prepare("
            INSERT INTO practicas_reparto_detalle
            (
                reparto_id,
                tipo_id,
                destino_id,
                valor
            )
            VALUES (?, ?, ?, ?)
        ");

        foreach ($reglas as $regla) {

            $tipo_id = (int)($regla['tipo_id'] ?? 0);
            $destino_id = (int)($regla['destino_id'] ?? 0);
            $valor = (float)($regla['valor'] ?? 0);

            if ($tipo_id <= 0 || $destino_id <= 0) {
                continue;
            }

            $stmtDetalle->execute([
                $reparto_id,
                $tipo_id,
                $destino_id,
                $valor
            ]);
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