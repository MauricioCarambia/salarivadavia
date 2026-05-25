<?php
require_once __DIR__ . '/../inc/caja_data.php';

header('Content-Type: application/json');

$movimientos = $movimientos ?? [];
$ingEfectivo = $ingEfectivo ?? 0;
$ingTransferencia = $ingTransferencia ?? 0;
$egrEfectivo = $egrEfectivo ?? 0;
$egrTransferencia = $egrTransferencia ?? 0;
$balanceFisico = $balanceFisico ?? 0;

echo json_encode([
    'success' => true,
    'kpis' => [
        'ingEfectivo' => $ingEfectivo,
        'ingTransferencia' => $ingTransferencia,
        'egrEfectivo' => $egrEfectivo,
        'egrTransferencia' => $egrTransferencia,
        'balanceFisico' => $balanceFisico
    ],
    'movimientos' => $movimientos
]);