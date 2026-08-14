<?php
require_once __DIR__ . '/../inc/session.php';

if (empty($_SESSION['login']) || $_SESSION['login'] !== 'si') {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

require_once __DIR__ . '/../inc/csrf.php';
requerirCsrf();
require_once __DIR__ . '/../inc/db.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);

$montoInicial = (float)($data['monto_inicial'] ?? 0);
$hoy = date('Y-m-d');

try {

    $pdo->beginTransaction();

    // ======================
    // 🔒 LOCK DEL DÍA
    // ======================
    $stmt = $pdo->prepare("
        SELECT id 
        FROM resumen_financiero_diario 
        WHERE fecha = ?
        FOR UPDATE
    ");
    $stmt->execute([$hoy]);
    $existe = $stmt->fetchColumn();

    // ======================
    // 🆕 SI NO EXISTE → CREAR SOLO BASE
    // ======================
   if (!$existe) {
    // Tomar saldo del día anterior como base
    $stmt = $pdo->prepare("
        SELECT saldo_caja, saldo_fondo
        FROM resumen_financiero_diario
        WHERE fecha < ?
        ORDER BY fecha DESC
        LIMIT 1
    ");
    $stmt->execute([$hoy]);
    $anterior = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['saldo_caja' => 0, 'saldo_fondo' => 0];

    $fondoInicial = (float)$anterior['saldo_fondo'];

    $stmt = $pdo->prepare("
        INSERT INTO resumen_financiero_diario
        (fecha, monto_inicial, fondo_inicial, total_cajas, total_fondos, egresos_caja, egresos_fondos,
         saldo_caja, saldo_fondo, saldo_total)
        VALUES (?, ?, ?, 0, 0, 0, 0, ?, ?, ?)
    ");
    $stmt->execute([$hoy, $montoInicial, $fondoInicial, $montoInicial, $fondoInicial, $montoInicial + $fondoInicial]);

} else {
    $stmt = $pdo->prepare("
        UPDATE resumen_financiero_diario
        SET monto_inicial = ?
        WHERE fecha = ?
    ");
    $stmt->execute([$montoInicial, $hoy]);
}

    $pdo->commit();

    echo json_encode(['success' => true]);

} catch (Throwable $e) {

    $pdo->rollBack();

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}