<?php
session_name("turnos");
session_start();

header('Content-Type: application/json');

require_once "../inc/db.php";

try {

    $input = json_decode(file_get_contents("php://input"), true);
    $id = $input['cobro_id'] ?? 0;

    if (!$id) {
        echo json_encode([
            'success' => false,
            'message' => 'ID inválido'
        ]);
        exit;
    }

    /* =============================
       🧾 CABECERA COBRO
    ============================= */
    $stmt = $pdo->prepare("
        SELECT 
            c.*,
            p.nombre AS nombre, p.apellido as apellido
        FROM cobros c
        LEFT JOIN pacientes p ON p.id = c.paciente_id
        WHERE c.id = ?
        LIMIT 1
    ");
    $stmt->execute([$id]);
    $cobro = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cobro) {
        echo json_encode([
            'success' => false,
            'message' => 'Cobro no encontrado'
        ]);
        exit;
    }

    /* =============================
       📋 DETALLE
    ============================= */
    $stmt = $pdo->prepare("
        SELECT nombre, precio
        FROM cobros_detalle
        WHERE cobro_id = ?
    ");
    $stmt->execute([$id]);
    $detalle = $stmt->fetchAll(PDO::FETCH_ASSOC);

    /* =============================
       💰 REPARTO
    ============================= */
    $stmt = $pdo->prepare("
        SELECT destino, SUM(monto) AS total
        FROM cobros_reparto
        WHERE cobro_id = ?
        GROUP BY destino
    ");
    $stmt->execute([$id]);
    $reparto = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'cobro' => [
            'id' => $cobro['id'],
            'numero' => $cobro['numero_completo'] ?? '',
            'paciente' => $cobro['nombre'] . ' ' . $cobro['apellido'] ?? 'Sin paciente',
            'fecha' => $cobro['fecha'] ?? '',
            'total' => (float) ($cobro['total'] ?? 0)
        ],
        'detalle' => array_map(fn($d) => [
            'nombre' => $d['nombre'],
            'precio' => (float) $d['precio']
        ], $detalle),
        'reparto' => array_map(fn($r) => [
            'destino' => $r['destino'],
            'total' => (float) $r['total']
        ], $reparto)
    ]);

} catch (Exception $e) {

    echo json_encode([
        'success' => false,
        'message' => 'Error del servidor',
        'error' => $e->getMessage()
    ]);
}