<?php
require_once __DIR__ . '/../inc/session.php';
require_once __DIR__ . '/../inc/db.php';
header('Content-Type: application/json');

try {
    $id = (int)($_GET['id'] ?? 0);

    if (!$id) throw new Exception("ID de cobro inválido");

    $usuarioId = $_SESSION['user_id'] ?? 0;
    if (!$usuarioId) throw new Exception('Usuario no autenticado');

    $stmt = $pdo->prepare("
        SELECT
            c.id,
            c.numero_completo,
            c.fecha,
            c.total,
            c.concepto,
            c.tipo,
            c.estado,
            c.medio_pago,
            c.transferencia_tipo,
            CONCAT_WS(' ', pa.apellido, pa.nombre) AS paciente,
            CONCAT_WS(' ', pr.apellido, pr.nombre) AS profesional
        FROM cobros c
        LEFT JOIN pacientes     pa ON pa.Id  = c.paciente_id
        LEFT JOIN profesionales pr ON pr.Id  = c.profesional_id
        WHERE c.id = ?
        LIMIT 1
    ");
    $stmt->execute([$id]);
    $cobro = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cobro) throw new Exception("Cobro no encontrado");

    echo json_encode([
        'success' => true,
        ...$cobro
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}