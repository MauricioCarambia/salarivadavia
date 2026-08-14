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

try {

    date_default_timezone_set('America/Argentina/Buenos_Aires');
    $hoy = date('Y-m-d');

    // ==========================
    // 🔍 EXISTE EL DÍA?
    // ==========================
    $stmt = $pdo->prepare("
        SELECT id 
        FROM resumen_financiero_diario 
        WHERE fecha = ?
    ");
    $stmt->execute([$hoy]);

    if ($stmt->fetch()) {
        echo json_encode(['success' => true]);
        exit;
    }

    // ==========================
    // 🔁 TRAER SALDOS ANTERIORES (caja y fondo POR SEPARADO)
    // ==========================
    $stmt = $pdo->query("
        SELECT saldo_caja, saldo_fondo
        FROM resumen_financiero_diario
        ORDER BY fecha DESC
        LIMIT 1
    ");

    $anterior = $stmt->fetch(PDO::FETCH_ASSOC);

    $montoInicial = (float)($anterior['saldo_caja']  ?? 0);
    $fondoInicial = (float)($anterior['saldo_fondo'] ?? 0);

    // ==========================
    // 🆕 CREAR DÍA LIMPIO
    // ==========================
    $stmt = $pdo->prepare("
        INSERT INTO resumen_financiero_diario (
            fecha,
            monto_inicial,
            fondo_inicial,
            total_cajas,
            total_fondos,
            egresos_caja,
            egresos_fondos,
            saldo_caja,
            saldo_fondo,
            saldo_total
        ) VALUES (?, ?, ?, 0, 0, 0, 0, ?, ?, ?)
    ");

    $stmt->execute([
        $hoy,
        $montoInicial,
        $fondoInicial,
        $montoInicial, // arranca igual, pero después se recalcula
        $fondoInicial,
        $montoInicial + $fondoInicial
    ]);

    echo json_encode([
        'success' => true,
        'monto_inicial' => $montoInicial,
        'fondo_inicial' => $fondoInicial
    ]);

} catch (Throwable $e) {

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}