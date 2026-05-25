<?php
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
    // 🔁 TRAER SALDO ANTERIOR
    // ==========================
    $stmt = $pdo->query("
        SELECT saldo_total 
        FROM resumen_financiero_diario 
        ORDER BY fecha DESC 
        LIMIT 1
    ");

    $saldoAnterior = (float)($stmt->fetchColumn() ?? 0);

    // ==========================
    // 🆕 CREAR DÍA LIMPIO
    // ==========================
    $stmt = $pdo->prepare("
        INSERT INTO resumen_financiero_diario (
            fecha,
            monto_inicial,
            total_cajas,
            total_fondos,
            egresos_caja,
            egresos_fondos,
            saldo_total
        ) VALUES (?, ?, 0, 0, 0, 0, ?)
    ");

    $stmt->execute([
        $hoy,
        $saldoAnterior,
        $saldoAnterior // arranca igual, pero después se recalcula
    ]);

    echo json_encode([
        'success' => true,
        'monto_inicial' => $saldoAnterior
    ]);

} catch (Throwable $e) {

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}