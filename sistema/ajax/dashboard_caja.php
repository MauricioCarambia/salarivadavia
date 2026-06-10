<?php
require_once __DIR__ . '/../inc/session.php';
require_once __DIR__ . '/../inc/db.php';
header('Content-Type: application/json');

try {
    $usuarioId = $_SESSION['user_id'] ?? 0;
    if (!$usuarioId) throw new Exception('Usuario no autenticado');

    $hoy = date('Y-m-d');

    /* ======================
       🔎 BUSCAR HOY
    ====================== */
    $stmt = $pdo->prepare("
        SELECT 
            fecha,
            monto_inicial,
            total_cajas,
            total_fondos,
            egresos_caja,
            egresos_fondos,
            (monto_inicial + total_cajas - egresos_caja)        AS saldo_caja,
            (total_fondos - egresos_fondos)                     AS saldo_fondo,
            (
                (monto_inicial + total_cajas - egresos_caja)
                + (total_fondos - egresos_fondos)
            )                                                   AS saldo_total
        FROM resumen_financiero_diario
        WHERE fecha = ?
    ");
    $stmt->execute([$hoy]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    /* ======================
       🔁 SI NO EXISTE HOY →
       TOMAR SALDO DEL ÚLTIMO DÍA
       COMO MONTO INICIAL
    ====================== */
    if (!$row) {

        $stmt = $pdo->prepare("
            SELECT 
                (
                    (monto_inicial + total_cajas - egresos_caja)
                    + (total_fondos - egresos_fondos)
                ) AS saldo_total,
                (monto_inicial + total_cajas - egresos_caja) AS saldo_caja,
                (total_fondos - egresos_fondos)              AS saldo_fondo
            FROM resumen_financiero_diario
            WHERE fecha < ?
            ORDER BY fecha DESC
            LIMIT 1
        ");
        $stmt->execute([$hoy]);
        $anterior = $stmt->fetch(PDO::FETCH_ASSOC);

        $saldoAnterior      = (float)($anterior['saldo_total'] ?? 0);
        $saldoCajaAnterior  = (float)($anterior['saldo_caja']  ?? 0);
        $saldoFondoAnterior = (float)($anterior['saldo_fondo'] ?? 0);

        $row = [
            'fecha'          => $hoy,
            'monto_inicial'  => $saldoAnterior,
            'total_cajas'    => 0,
            'total_fondos'   => 0,
            'egresos_caja'   => 0,
            'egresos_fondos' => 0,
            'saldo_caja'     => $saldoCajaAnterior,
            'saldo_fondo'    => $saldoFondoAnterior,
            'saldo_total'    => $saldoAnterior,
        ];
    }

    echo json_encode([
        'success' => true,
        'data'    => $row
    ]);

} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}