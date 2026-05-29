<?php
require_once __DIR__ . '/../inc/db.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);

$tipo = $data['tipo'];
$monto = floatval($data['monto']);
$concepto = $data['concepto'];
$profesional_id = $data['profesional_id'] ?: null;

try {

    $pdo->beginTransaction();

    /* =========================
        1. INSERT EGRESO
    ========================= */
    $stmt = $pdo->prepare("
        INSERT INTO egresos
        (fecha, tipo, concepto, monto, profesional_id)
        VALUES (CURDATE(), ?, ?, ?, ?)
    ");

    $stmt->execute([
        $tipo,
        $concepto,
        $monto,
        $profesional_id
    ]);

    /* =========================
        2. IMPACTAR SALDOS
    ========================= */

    if ($tipo === 'caja') {

        $stmt = $pdo->prepare("
            UPDATE resumen_financiero_diario
            SET 
                egresos_caja = egresos_caja + ?,
                saldo_caja = saldo_caja - ?
            WHERE fecha = CURDATE()
        ");

        $stmt->execute([$monto, $monto]);
    }

    if ($tipo === 'fondo') {

        $stmt = $pdo->prepare("
            UPDATE resumen_financiero_diario
            SET 
                egresos_fondos = egresos_fondos + ?,
                saldo_fondo = saldo_fondo - ?
            WHERE fecha = CURDATE()
        ");

        $stmt->execute([$monto, $monto]);
    }

    /* =========================
        3. SALDO TOTAL REAL
    ========================= */

    $pdo->query("
        UPDATE resumen_financiero_diario
        SET saldo_total = monto_inicial + saldo_caja + saldo_fondo
        WHERE fecha = CURDATE()
    ");

    $pdo->commit();

    echo json_encode(["success" => true]);

} catch (Exception $e) {

    $pdo->rollBack();

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}