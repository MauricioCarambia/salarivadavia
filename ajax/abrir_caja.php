<?php
session_name("turnos");
session_start();
header('Content-Type: application/json');
require_once "../inc/db.php";

$usuarioId = $_SESSION['user_id'] ?? null;
if (!$usuarioId) {
    echo json_encode(['success' => false, 'message' => 'Usuario no autenticado']);
    exit;
}

// Leer datos enviados en JSON
$input = json_decode(file_get_contents('php://input'), true);

$cajaId = (int) ($input['caja_id'] ?? 0);
$montoInicial = floatval($input['monto_inicial'] ?? 0);
$turno = $input['turno'] ?? '';

if (!$cajaId) {
    echo json_encode(['success' => false, 'message' => 'Caja inválida']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Verificar si la caja existe y está inactiva
    $stmt = $pdo->prepare("SELECT activo, nombre FROM cajas WHERE id = ?");
    $stmt->execute([$cajaId]);
    $caja = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$caja)
        throw new Exception("Caja no encontrada");
    if ($caja['activo'])
        throw new Exception("Caja ya está activa");

    // Crear sesión de caja
    $stmt = $pdo->prepare("
        INSERT INTO caja_sesion (caja_id, usuario_id, estado, fecha_apertura, turno, monto_inicial)
VALUES (?, ?, 'abierta', NOW(), ?, ?)
    ");
    $stmt->execute([
    $cajaId,
    $usuarioId,
    $turno,
    $montoInicial
]);
    $cajaSesionId = $pdo->lastInsertId();

    // Actualizar estado de la caja
    $stmt = $pdo->prepare("UPDATE cajas SET activo = 1 WHERE id = ?");
    $stmt->execute([$cajaId]);

    // Insertar movimiento inicial si monto > 0
    if ($montoInicial > 0) {
        $stmt = $pdo->prepare("
            INSERT INTO caja_movimientos (caja_id, tipo, monto, fecha, usuario_id, caja_sesion_id)
            VALUES (?, 'INGRESO', ?, NOW(), ?, ?)
        ");
        $stmt->execute([$cajaId, $montoInicial, $usuarioId, $cajaSesionId]);
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'cajaAbierta' => [
            'id' => $cajaSesionId,
            'nombre' => $caja['nombre'],
            'turno' => $turno
        ]
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}