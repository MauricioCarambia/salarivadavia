<?php
require_once __DIR__ . '/../inc/session.php';
require_once __DIR__ . '/../inc/csrf.php';
requerirCsrf();
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auditoria.php';

header('Content-Type: application/json');

if (!isset($_SESSION['login']) || $_SESSION['login'] !== 'si') {
    echo json_encode(['ok' => false, 'msg' => 'Sesión expirada']);
    exit;
}

$paciente_id = (int)($_GET['paciente_id'] ?? 0);
$usuario_id  = (int)($_SESSION['user_id'] ?? 0);

if (!$paciente_id) {
    echo json_encode(['ok' => false, 'msg' => 'Paciente no especificado']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$items = $input['items'] ?? [];
$sinCobro = !empty($input['sin_cobro']);

if (empty($items)) {
    echo json_encode(['ok' => false, 'msg' => 'No hay ítems para guardar']);
    exit;
}

/* =========================
   CAJA ABIERTA
   (no aplica si la cuota ya fue abonada por otro medio)
========================= */
$caja_id        = null;
$caja_sesion_id = null;

if (!$sinCobro) {
    $stmtCaja = $pdo->prepare("
        SELECT cs.id AS caja_sesion_id, cs.caja_id
        FROM caja_sesion cs
        INNER JOIN cajas c ON c.id = cs.caja_id
        WHERE cs.estado = 'abierta'
          AND cs.usuario_id = ?
        LIMIT 1
    ");
    $stmtCaja->execute([$usuario_id]);
    $caja = $stmtCaja->fetch(PDO::FETCH_ASSOC);

    if (!$caja) {
        echo json_encode(['ok' => false, 'msg' => 'No hay una caja abierta. Abrí una caja antes de registrar pagos.']);
        exit;
    }

    $caja_id        = (int)$caja['caja_id'];
    $caja_sesion_id = (int)$caja['caja_sesion_id'];
}

/* =========================
   NRO AFILIADO DEL PACIENTE
========================= */
$stmtNro = $pdo->prepare("SELECT nro_afiliado, apellido, nombre FROM pacientes WHERE Id = ?");
$stmtNro->execute([$paciente_id]);
$rowNro   = $stmtNro->fetch(PDO::FETCH_ASSOC);
$nro_base = trim(explode('/', $rowNro['nro_afiliado'] ?? '?')[0]);

/* =========================
   ¿YA TIENE PAGOS PREVIOS?
   (antes de esta transacción)
========================= */
$stmtPrevios = $pdo->prepare("
    SELECT COUNT(*) FROM pagos_afiliados pa
    INNER JOIN pacientes p ON p.Id = pa.paciente_id
    WHERE SUBSTRING_INDEX(SUBSTRING_INDEX(p.nro_afiliado, '/', 1), ' ', 1) = (
        SELECT SUBSTRING_INDEX(SUBSTRING_INDEX(nro_afiliado, '/', 1), ' ', 1)
        FROM pacientes WHERE Id = ?
    )
");
$stmtPrevios->execute([$paciente_id]);
$tenia_pagos_previos = (int)$stmtPrevios->fetchColumn() > 0;

$meses_es = [
    '01'=>'enero','02'=>'febrero','03'=>'marzo','04'=>'abril',
    '05'=>'mayo','06'=>'junio','07'=>'julio','08'=>'agosto',
    '09'=>'septiembre','10'=>'octubre','11'=>'noviembre','12'=>'diciembre'
];

// Destinos fijos
const DESTINO_CUOTA       = 6;   // ingreso / caja
const DESTINO_CUOTA_INICIAL = 11;  // ingreso / fondo

try {
    $pdo->beginTransaction();

    /* ---- Prepared statements ---- */
    $stmtPago = $pdo->prepare("
        INSERT INTO pagos_afiliados (paciente_id, monto, fecha_pago, fecha_correspondiente)
        SELECT
            p.Id,
            :monto,
            CURDATE(),
            :fecha
        FROM pacientes p
        WHERE SUBSTRING_INDEX(SUBSTRING_INDEX(p.nro_afiliado, '/', 1), ' ', 1) = (
            SELECT SUBSTRING_INDEX(SUBSTRING_INDEX(nro_afiliado, '/', 1), ' ', 1)
            FROM pacientes
            WHERE Id = :paciente_id
        )
    ");

    $stmtCobro = $pdo->prepare("
        INSERT INTO cobros (
            turno_id, paciente_id, profesional_id,
            total, tipo, fecha,
            usuario_id, caja_id, caja_sesion_id,
            punto_venta, numero, numero_completo,
            estado, medio_pago,
            empleado_destino_id, profesional_destino_id,
            transferencia_tipo, deuda_clinica,
            concepto
        ) VALUES (
            NULL, NULL, NULL,
            ?, 'ingreso', NOW(),
            ?, ?, ?,
            ?, ?, ?,
            'activo', 'efectivo',
            NULL, NULL,
            NULL, 0.00,
            ?
        )
    ");

    $stmtReparto = $pdo->prepare("
        INSERT INTO cobros_reparto (cobro_id, destino_id, monto)
        VALUES (?, ?, ?)
    ");

    // El primer ítem del carrito es la primera cuota nueva que se paga.
    // Si el afiliado no tenía pagos previos, ese primer ítem genera fondo jorge.
    $es_primer_pago = !$tenia_pagos_previos;

    foreach ($items as $idx => $item) {
        $monto = (float)($item['monto'] ?? 0);
        $fecha = ($item['fecha'] ?? '') . '-01';   // YYYY-MM-01
        $ym    = $item['fecha'] ?? '';             // YYYY-MM

        if ($monto <= 0 || !$item['fecha']) continue;

        // 1. pagos_afiliados
        $stmtPago->execute([
            ':paciente_id' => $paciente_id,
            ':monto'       => $monto,
            ':fecha'       => $fecha,
        ]);

        // Si ya fue abonado por otro medio, no se genera cobro/reparto en caja
        if ($sinCobro) {
            $es_primer_pago = false;
            continue;
        }

        // 2. Número con lock
        $stmtNum = $pdo->prepare("SELECT MAX(numero) FROM cobros WHERE punto_venta = ? FOR UPDATE");
        $stmtNum->execute([$caja_id]);
        $ultimo = (int)$stmtNum->fetchColumn();
        $nuevo  = $ultimo ? $ultimo + 1 : 1;

        $numeroCompleto = str_pad($caja_id, 4, '0', STR_PAD_LEFT) . '-' .
                          str_pad($nuevo,   8, '0', STR_PAD_LEFT);

        // 3. Concepto
        [$anio, $mes_num] = explode('-', $ym);
        $mes_nombre = $meses_es[$mes_num] ?? $mes_num;
        $concepto   = "cuota {$mes_nombre} socio {$nro_base}";

        // 4. cobro
        $stmtCobro->execute([
            $monto,
            $usuario_id,
            $caja_id,
            $caja_sesion_id,
            $caja_id,          // punto_venta
            $nuevo,
            $numeroCompleto,
            $concepto,
        ]);
        $cobro_id = (int)$pdo->lastInsertId();

        // 5. reparto
        //    - siempre: destino 6 (cuota / caja)
        //    - solo primer mes nuevo: destino 11 (cuota inicial / fondo)


        if ($es_primer_pago) {
            $stmtReparto->execute([$cobro_id, DESTINO_CUOTA_INICIAL, $monto]);
            $es_primer_pago = false;   // solo aplica al primer ítem
        }else{
            $stmtReparto->execute([$cobro_id, DESTINO_CUOTA, $monto]);
        }
    }

    $pdo->commit();

    /* ---- Afiliados para el ticket ---- */
    $stmtAf = $pdo->prepare("
        SELECT apellido, nombre, nro_afiliado
        FROM pacientes
        WHERE SUBSTRING_INDEX(SUBSTRING_INDEX(nro_afiliado, '/', 1), ' ', 1) = (
            SELECT SUBSTRING_INDEX(SUBSTRING_INDEX(nro_afiliado, '/', 1), ' ', 1)
            FROM pacientes WHERE Id = ?
        )
        ORDER BY apellido, nombre
    ");
    $stmtAf->execute([$paciente_id]);
    $afiliados = $stmtAf->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'ok'        => true,
        'msg'       => 'Pagos guardados correctamente',
        'afiliados' => $afiliados,
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['ok' => false, 'msg' => 'Error al guardar: ' . $e->getMessage()]);
}