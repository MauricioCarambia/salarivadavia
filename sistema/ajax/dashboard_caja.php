<?php
require_once __DIR__ . '/../inc/db.php';
header('Content-Type: application/json');

try {

    $hoy = date('Y-m-d');

    $stmt = $pdo->prepare("
        SELECT 
            fecha,
            monto_inicial,
            total_cajas,
            total_fondos,
            egresos_caja,
            egresos_fondos,

            (total_cajas - egresos_caja) AS saldo_caja,
            (total_fondos - egresos_fondos) AS saldo_fondo,

            (
                monto_inicial 
                + (total_cajas - egresos_caja)
                + (total_fondos - egresos_fondos)
            ) AS saldo_total

        FROM resumen_financiero_diario
        WHERE fecha = ?
    ");

    $stmt->execute([$hoy]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    // fallback seguro
    if (!$row) {
        $row = [
            'fecha' => $hoy,
            'monto_inicial' => 0,
            'total_cajas' => 0,
            'total_fondos' => 0,
            'egresos_caja' => 0,
            'egresos_fondos' => 0,
            'saldo_caja' => 0,
            'saldo_fondo' => 0,
            'saldo_total' => 0
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => $row
    ]);

} catch (Throwable $e) {

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}