<?php
require_once __DIR__ . '/../inc/db.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);

$montoInicial = (float)($data['monto_inicial'] ?? 0);
$hoy = date('Y-m-d');

try {

    $pdo->beginTransaction();

    // ======================
    // 🔒 LOCK DEL DÍA
    // ======================
    $stmt = $pdo->prepare("
        SELECT id 
        FROM resumen_financiero_diario 
        WHERE fecha = ?
        FOR UPDATE
    ");
    $stmt->execute([$hoy]);
    $existe = $stmt->fetchColumn();

    // ======================
    // 🆕 SI NO EXISTE → CREAR SOLO BASE
    // ======================
    if (!$existe) {

        $stmt = $pdo->prepare("
            INSERT INTO resumen_financiero_diario
            (fecha, monto_inicial, total_cajas, total_fondos, egresos_caja, egresos_fondos)
            VALUES (?, ?, 0, 0, 0, 0)
        ");

        $stmt->execute([
            $hoy,
            $montoInicial
        ]);

    } else {

        // ======================
        // 🔁 SOLO ACTUALIZAR ARRANQUE
        // ======================
        $stmt = $pdo->prepare("
            UPDATE resumen_financiero_diario
            SET monto_inicial = ?
            WHERE fecha = ?
        ");

        $stmt->execute([
            $montoInicial,
            $hoy
        ]);
    }

    $pdo->commit();

    echo json_encode(['success' => true]);

} catch (Throwable $e) {

    $pdo->rollBack();

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}