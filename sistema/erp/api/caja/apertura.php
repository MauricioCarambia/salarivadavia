<?php
session_name("turnos");
session_start();

require_once __DIR__ . '/../../../inc/db.php';

header('Content-Type: application/json');

try {

    $data = json_decode(file_get_contents("php://input"), true) ?? [];

    $tieneMonto = array_key_exists('monto_inicial', $data) && $data['monto_inicial'] !== null && $data['monto_inicial'] !== '';
    $tieneFondo = array_key_exists('fondo_inicial', $data) && $data['fondo_inicial'] !== null && $data['fondo_inicial'] !== '';

    if (!$tieneMonto && !$tieneFondo) {
        throw new Exception("Ingresá al menos un valor para guardar");
    }

    $montoInicial = $tieneMonto ? (float)$data['monto_inicial'] : null;
    $fondoInicial = $tieneFondo ? (float)$data['fondo_inicial'] : null;

    $fecha = date('Y-m-d');

    if (($montoInicial !== null && $montoInicial < 0) || ($fondoInicial !== null && $fondoInicial < 0)) {
        throw new Exception("Los montos no pueden ser negativos");
    }

    $pdo->beginTransaction();

    /* =====================================
       BUSCAR SI EXISTE EL DÍA
    ===================================== */
    $stmt = $pdo->prepare("
        SELECT id
        FROM resumen_financiero_diario
        WHERE fecha = ?
        FOR UPDATE
    ");
    $stmt->execute([$fecha]);

    $existe = $stmt->fetchColumn();

    if (!$existe) {

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
            )
            VALUES (
                ?, ?, ?,
                0, 0,
                0, 0,
                0, 0,
                ?
            )
        ");

        $stmt->execute([
            $fecha,
            $montoInicial ?? 0,
            $fondoInicial ?? 0,
            ($montoInicial ?? 0) + ($fondoInicial ?? 0)
        ]);

    } else {

        // Solo actualiza los campos que efectivamente vinieron en el request,
        // para no pisar con 0 el valor que no se quiso modificar
        $sets = [];
        $params = [];

        if ($tieneMonto) {
            $sets[] = "monto_inicial = ?";
            $params[] = $montoInicial;
        }

        if ($tieneFondo) {
            $sets[] = "fondo_inicial = ?";
            $params[] = $fondoInicial;
        }

        $params[] = $fecha;

        $stmt = $pdo->prepare("
            UPDATE resumen_financiero_diario
            SET " . implode(', ', $sets) . "
            WHERE fecha = ?
        ");

        $stmt->execute($params);
    }

    reconstruirDia($pdo, $fecha);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Datos iniciales actualizados',
        'fecha'   => $fecha
    ]);

} catch (Throwable $e) {

    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}


/* =========================================
   RECONSTRUIR DÍA
========================================= */
function reconstruirDia(PDO $pdo, string $fecha): void
{
    /* ==========================
       ARQUEOS ACUMULADOS
    ========================== */
    $stmt = $pdo->prepare("
        SELECT
            COALESCE(SUM(total_caja),0)  AS cajas,
            COALESCE(SUM(total_fondo),0) AS fondos
        FROM arqueos_caja
        WHERE DATE(fecha) <= ?
    ");

    $stmt->execute([$fecha]);

    $ar = $stmt->fetch(PDO::FETCH_ASSOC);

    /* ==========================
       EGRESOS ACUMULADOS
    ========================== */
    $stmt = $pdo->prepare("
        SELECT
            COALESCE(
                SUM(
                    CASE
                        WHEN tipo='caja'
                        THEN monto
                        ELSE 0
                    END
                ),
            0) AS eg_caja,

            COALESCE(
                SUM(
                    CASE
                        WHEN tipo='fondo'
                        THEN monto
                        ELSE 0
                    END
                ),
            0) AS eg_fondo

        FROM egresos
        WHERE DATE(creado_en) <= ?
    ");

    $stmt->execute([$fecha]);

    $eg = $stmt->fetch(PDO::FETCH_ASSOC);

    /* ==========================
       INICIALES
    ========================== */
    $stmt = $pdo->prepare("
        SELECT
            monto_inicial,
            fondo_inicial
        FROM resumen_financiero_diario
        WHERE fecha = ?
        LIMIT 1
    ");

    $stmt->execute([$fecha]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $montoInicial = (float)($row['monto_inicial'] ?? 0);
    $fondoInicial = (float)($row['fondo_inicial'] ?? 0);

    /* ==========================
       ACUMULADOS
    ========================== */
    $saldoCaja =
        (float)$ar['cajas']
        - (float)$eg['eg_caja'];

    $saldoFondo =
        (float)$ar['fondos']
        - (float)$eg['eg_fondo'];

    /* ==========================
       TOTAL DISPONIBLE
    ========================== */
    $saldoTotal =
        $montoInicial
        + $fondoInicial
        + $saldoCaja
        + $saldoFondo;

    /* ==========================
       ACTUALIZAR RESUMEN
    ========================== */
    $stmt = $pdo->prepare("
        UPDATE resumen_financiero_diario
        SET
            total_cajas    = ?,
            total_fondos   = ?,
            egresos_caja   = ?,
            egresos_fondos = ?,
            saldo_caja     = ?,
            saldo_fondo    = ?,
            saldo_total    = ?
        WHERE fecha = ?
    ");

    $stmt->execute([
        $ar['cajas'],
        $ar['fondos'],
        $eg['eg_caja'],
        $eg['eg_fondo'],
        $saldoCaja,
        $saldoFondo,
        $saldoTotal,
        $fecha
    ]);
}