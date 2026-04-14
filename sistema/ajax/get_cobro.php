<?php
session_name("turnos");
session_start();

header('Content-Type: application/json');

require_once "../inc/db.php";

try {

    // 🔥 UNIFICAMOS: usar GET (más simple para AJAX)
    $id = (int) ($_GET['cobro_id'] ?? 0);

    if (!$id) {
        throw new Exception('ID inválido');
    }

    /* =============================
       🧾 CABECERA COBRO
    ============================= */
    $stmt = $pdo->prepare("
        SELECT 
            c.*,
            p.nombre,
            p.apellido
        FROM cobros c
        LEFT JOIN pacientes p ON p.id = c.paciente_id
        WHERE c.id = ?
        LIMIT 1
    ");
    $stmt->execute([$id]);
    $cobro = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cobro) {
        throw new Exception('Cobro no encontrado');
    }

    // 🔥 paciente seguro
    $paciente = trim(($cobro['apellido'] ?? '') . ' ' . ($cobro['nombre'] ?? ''));
    if (!$paciente) {
        $paciente = 'Sin paciente';
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

    $detalle = array_map(function ($d) {
        return [
            'nombre' => $d['nombre'],
            'precio' => (float) $d['precio']
        ];
    }, $detalle);

    /* =============================
       💰 REPARTO (CORREGIDO 🔥)
    ============================= */
    $stmt = $pdo->prepare("
        SELECT dr.nombre AS destino, SUM(cr.monto) AS total
        FROM cobros_reparto cr
        INNER JOIN destinos_reparto dr ON dr.id = cr.destino_id
        WHERE cr.cobro_id = ?
        GROUP BY dr.nombre
    ");
    $stmt->execute([$id]);
    $reparto = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $reparto = array_map(function ($r) {
        return [
            'destino' => $r['destino'],
            'total' => (float) $r['total']
        ];
    }, $reparto);

    /* =============================
       📤 RESPUESTA
    ============================= */
    echo json_encode([
        'success' => true,
        'cobro' => [
            'id' => (int) $cobro['id'],
            'numero' => $cobro['numero_completo'] ?? '',
            'paciente' => $paciente,
            'fecha' => date('d/m/Y H:i', strtotime($cobro['fecha'])),
            'total' => (float) ($cobro['total'] ?? 0)
        ],
        'detalle' => $detalle,
        'reparto' => $reparto
    ]);

} catch (Exception $e) {

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}