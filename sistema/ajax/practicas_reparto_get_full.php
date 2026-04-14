<?php
require_once "../inc/db.php";

header('Content-Type: application/json');

$id = $_GET['id'] ?? 0;

try {

    if (!$id) {
        throw new Exception("ID inválido");
    }

    /* =========================
       CABECERA
    ========================= */
    $stmt = $pdo->prepare("
        SELECT 
            id,
            practica_id,
            profesional_id,
            tipo_paciente
        FROM practicas_reparto
        WHERE id = ?
    ");
    $stmt->execute([$id]);

    $reparto = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reparto) {
        throw new Exception("Reparto no encontrado");
    }

    /* =========================
       REGLAS
    ========================= */
    $stmt = $pdo->prepare("
        SELECT 
            d.id,
            d.tipo_id,
            t.nombre AS tipo,
            d.valor,
            d.orden,
            d.destino_id,
            dr.nombre AS destino
        FROM practicas_reparto_detalle d
        INNER JOIN destinos_reparto dr ON dr.id = d.destino_id
        INNER JOIN tipos_reparto t ON t.id = d.tipo_id
        WHERE d.reparto_id = ?
        ORDER BY d.orden
    ");
    $stmt->execute([$id]);

    $reglas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'reparto' => $reparto,
        'reglas' => $reglas
    ]);

} catch (Exception $e) {

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}