<?php
require_once "../inc/db.php";

header('Content-Type: application/json');

$sql = "
    SELECT 
        DATE(fecha) as fecha,
        SUM(CASE WHEN tipo = 'ingreso' THEN monto ELSE 0 END) as ingresos,
        SUM(CASE WHEN tipo = 'fondo' THEN monto ELSE 0 END) as fondos
    FROM caja_movimientos
    GROUP BY DATE(fecha)
    ORDER BY fecha DESC
";

$stmt = $pdo->query($sql);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($data);