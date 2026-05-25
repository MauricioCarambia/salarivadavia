<?php
require_once __DIR__ . '/../inc/db.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);

$montoInicial = isset($data['monto_inicial']) ? (float)$data['monto_inicial'] : null;
$hoy = date('Y-m-d');

if ($montoInicial === null) {
    echo json_encode([
        'success' => false,
        'message' => 'Monto inicial requerido'
    ]);
    exit;
}

try {

    $pdo->beginTransaction();

    // ======================
    // 🔍 CREAR O ACTUALIZAR CONTROL
    // ======================
    $stmt = $pdo->prepare("SELECT id FROM control_diario WHERE fecha = ?");
    $stmt->execute([$hoy]);

    if (!$stmt->fetchColumn()) {

        $stmt = $pdo->prepare("
            INSERT INTO control_diario (fecha, monto_inicial)
            VALUES (?, ?)
        ");
        $stmt->execute([$hoy, $montoInicial]);

    } else {

        $stmt = $pdo->prepare("
            UPDATE control_diario
            SET monto_inicial = ?
            WHERE fecha = ?
        ");
        $stmt->execute([$montoInicial, $hoy]);
    }

    // ======================
    // 📊 TRAER RESUMEN REAL
    // ======================
    $stmt = $pdo->prepare("
        SELECT 
            total_cajas,
            total_fondos,
            saldo_total
        FROM resumen_financiero_diario
        WHERE fecha = ?
    ");
    $stmt->execute([$hoy]);

    $resumen = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
        'total_cajas' => 0,
        'total_fondos' => 0,
        'saldo_total' => $montoInicial
    ];

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'data' => [
            'monto_inicial' => $montoInicial,
            'total_cajas' => (float)$resumen['total_cajas'],
            'total_fondos' => (float)$resumen['total_fondos'],
            'total_final' => (float)$resumen['saldo_total']
        ]
    ]);

} catch (Exception $e) {

    $pdo->rollBack();

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}