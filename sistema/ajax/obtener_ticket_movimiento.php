<?php
require_once "../inc/db.php";
header('Content-Type: application/json');

try {

    $cobro_id = (int)($_GET['cobro_id'] ?? 0);

    if (!$cobro_id) {
        throw new Exception("ID inválido");
    }

    $stmt = $pdo->prepare("
        SELECT 
            m.tipo,
            m.concepto,
            m.monto,
            m.fecha,
            m.descripcion,
            p.nombre AS paciente_nombre,
            p.apellido AS paciente_apellido,
            pr.nombre AS profesional_nombre,
            pr.apellido AS profesional_apellido
        FROM caja_movimientos m
        LEFT JOIN cobros c ON c.id = m.cobro_id
        LEFT JOIN pacientes p ON p.Id = c.paciente_id
        LEFT JOIN profesionales pr ON pr.Id = c.profesional_id
        WHERE m.cobro_id = ?
        LIMIT 1
    ");

    $stmt->execute([$cobro_id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$data) {
        throw new Exception("Movimiento no encontrado");
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'tipo' => $data['tipo'],
            'fecha' => date('d/m/Y H:i', strtotime($data['fecha'])),
            'paciente' => trim(($data['paciente_nombre'] ?? '') . ' ' . ($data['paciente_apellido'] ?? '')),
            'profesional' => trim(($data['profesional_nombre'] ?? '') . ' ' . ($data['profesional_apellido'] ?? '')),
            'concepto' => $data['concepto'],
            'descripcion' => $data['descripcion'],
            'total' => $data['monto']
        ]
    ]);

} catch (Exception $e) {

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}