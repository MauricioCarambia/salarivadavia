<?php
require_once __DIR__ . '/../inc/session.php';
require_once __DIR__ . '/../inc/csrf.php';
requerirCsrf();
require_once __DIR__ . '/../inc/db.php';
header('Content-Type: application/json');

try {
    $usuarioId = $_SESSION['user_id'] ?? 0;
    if (!$usuarioId) throw new Exception('Usuario no autenticado');

    $data           = json_decode(file_get_contents("php://input"), true);
    $tipo           = $data['tipo']           ?? '';
    $monto          = (float)($data['monto']  ?? 0);
    $concepto       = trim($data['concepto']  ?? '');
    $profesional_id = !empty($data['profesional_id']) ? (int)$data['profesional_id'] : null;
    $hoy            = date('Y-m-d');

    if (!in_array($tipo, ['caja', 'fondo'], true)) {
        throw new Exception("Tipo inválido");
    }
    if ($monto <= 0) {
        throw new Exception("Monto inválido");
    }
    if ($concepto === '') {
        throw new Exception("Concepto requerido");
    }

    $pdo->beginTransaction();

    /* =========================
       1. INSERT EGRESO
    ========================= */
    $pdo->prepare("
        INSERT INTO egresos (fecha, tipo, concepto, monto, profesional_id)
        VALUES (CURDATE(), ?, ?, ?, ?)
    ")->execute([$tipo, $concepto, $monto, $profesional_id]);

    /* =========================
       2. ASEGURAR FILA HOY
          Si no existe, crearla
          heredando saldo anterior
    ========================= */
    $stmt = $pdo->prepare("
        SELECT id FROM resumen_financiero_diario WHERE fecha = ? FOR UPDATE
    ");
    $stmt->execute([$hoy]);

    if (!$stmt->fetchColumn()) {

        $stmt = $pdo->prepare("
            SELECT saldo_caja, saldo_fondo
            FROM resumen_financiero_diario
            WHERE fecha < ?
            ORDER BY fecha DESC
            LIMIT 1
        ");
        $stmt->execute([$hoy]);
        $ant = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['saldo_caja' => 0, 'saldo_fondo' => 0];

        $saldoCajaInicial  = (float)$ant['saldo_caja'];
        $saldoFondoInicial = (float)$ant['saldo_fondo'];

        $pdo->prepare("
            INSERT INTO resumen_financiero_diario (
                fecha, monto_inicial,
                total_cajas, total_fondos,
                egresos_caja, egresos_fondos,
                saldo_caja, saldo_fondo, saldo_total
            ) VALUES (?, ?, 0, 0, 0, 0, ?, ?, ?)
        ")->execute([
            $hoy,
            $saldoCajaInicial + $saldoFondoInicial,   // monto_inicial = referencia histórica
            $saldoCajaInicial,
            $saldoFondoInicial,
            $saldoCajaInicial + $saldoFondoInicial,   // saldo_total = caja + fondo
        ]);
    }

    /* =========================
       3. ACTUALIZAR SALDOS
          Acumula el egreso y
          recalcula saldo_total
    ========================= */
    if ($tipo === 'caja') {
        $pdo->prepare("
            UPDATE resumen_financiero_diario
            SET
                egresos_caja = egresos_caja + ?,
                saldo_caja   = saldo_caja   - ?
            WHERE fecha = ?
        ")->execute([$monto, $monto, $hoy]);
    } else {
        $pdo->prepare("
            UPDATE resumen_financiero_diario
            SET
                egresos_fondos = egresos_fondos + ?,
                saldo_fondo    = saldo_fondo    - ?
            WHERE fecha = ?
        ")->execute([$monto, $monto, $hoy]);
    }

    /* =========================
       4. RECALCULAR TOTAL
    ========================= */
    $pdo->prepare("
        UPDATE resumen_financiero_diario
        SET saldo_total = saldo_caja + saldo_fondo
        WHERE fecha = ?
    ")->execute([$hoy]);

    $pdo->commit();

    echo json_encode(['success' => true]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}