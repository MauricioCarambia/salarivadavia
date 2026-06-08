<?php
session_name("turnos");
session_start();
require_once __DIR__ . '/../inc/db.php';

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

if (empty($items)) {
    echo json_encode(['ok' => false, 'msg' => 'No hay ítems para guardar']);
    exit;
}

/* =========================
   CAJA ABIERTA
========================= */
$stmtCaja = $conexion->prepare("
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

/* =========================
   NRO AFILIADO DEL PACIENTE
========================= */
$stmtNro = $conexion->prepare("SELECT nro_afiliado FROM pacientes WHERE Id = ?");
$stmtNro->execute([$paciente_id]);
$rowNro    = $stmtNro->fetch(PDO::FETCH_ASSOC);
$nro_base  = trim(explode('/', $rowNro['nro_afiliado'] ?? '?')[0]);

$meses_es = [
    '01'=>'enero','02'=>'febrero','03'=>'marzo','04'=>'abril',
    '05'=>'mayo','06'=>'junio','07'=>'julio','08'=>'agosto',
    '09'=>'septiembre','10'=>'octubre','11'=>'noviembre','12'=>'diciembre'
];

try {
    $conexion->beginTransaction();

    /* ---- Prepared statements ---- */
    $stmtPago = $conexion->prepare("
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

    $stmtCobro = $conexion->prepare("
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

    foreach ($items as $item) {
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

        // 2. Número con lock (igual que los otros endpoints)
        $stmtNum = $conexion->prepare("SELECT MAX(numero) FROM cobros WHERE punto_venta = ? FOR UPDATE");
        $stmtNum->execute([$caja_id]);
        $ultimo = (int)$stmtNum->fetchColumn();
        $nuevo  = $ultimo ? $ultimo + 1 : 1;

        $numeroCompleto = str_pad($caja_id, 4, '0', STR_PAD_LEFT) . '-' .
                          str_pad($nuevo,   8, '0', STR_PAD_LEFT);

        // 3. Concepto: "cuota junio socio 90000"
        [$anio, $mes_num] = explode('-', $ym);
        $mes_nombre = $meses_es[$mes_num] ?? $mes_num;
        $concepto   = "cuota {$mes_nombre} socio {$nro_base}";

        // 4. cobros — orden: total, usuario_id, caja_id, caja_sesion_id, punto_venta, numero, numero_completo, concepto
        $stmtCobro->execute([
            $monto,
            $usuario_id,
            $caja_id,          // caja_id
            $caja_sesion_id,   // caja_sesion_id (FK que fallaba — estaba invertido)
            $caja_id,          // punto_venta = caja_id
            $nuevo,
            $numeroCompleto,
            $concepto,
        ]);
    }

    $conexion->commit();

    /* ---- Afiliados para el ticket ---- */
    $stmtAf = $conexion->prepare("
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
    $conexion->rollBack();
    echo json_encode(['ok' => false, 'msg' => 'Error al guardar: ' . $e->getMessage()]);
}