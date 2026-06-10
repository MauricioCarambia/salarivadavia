<?php
require_once __DIR__ . '/../inc/session.php';

if (empty($_SESSION['login']) || $_SESSION['login'] !== 'si') {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}
require_once __DIR__ . '/../inc/db.php';

header('Content-Type: application/json');

try {

    $stmt = $pdo->query("
        SELECT 
            fecha,
            monto_inicial,

            total_cajas,
            total_fondos,

            egresos_caja,
            egresos_fondos,

            (monto_inicial + total_cajas - egresos_caja) AS saldo_caja,
            (total_fondos - egresos_fondos) AS saldo_fondo,

            (
                (monto_inicial + total_cajas - egresos_caja)
                + (total_fondos - egresos_fondos)
            ) AS saldo_total

        FROM resumen_financiero_diario
        ORDER BY fecha DESC
        LIMIT 30
    ");

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($rows);

} catch (Throwable $e) {

    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}