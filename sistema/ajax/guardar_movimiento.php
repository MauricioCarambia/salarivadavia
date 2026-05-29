<?php
require_once __DIR__ . '/../inc/session.php';
require_once __DIR__ . '/../inc/db.php';



header('Content-Type: application/json');

try {

    $pdo->beginTransaction();

    /* ==============================
       📥 DATOS
    ============================== */
    $tipo           = strtolower(trim($_POST['tipo'] ?? ''));
    $concepto       = trim($_POST['concepto'] ?? '');
    $monto_input    = (float)($_POST['monto'] ?? 0);
    $practica_id    = (int)($_POST['practica_id'] ?? 0);
    $profesional_id = (int)($_POST['profesional_id'] ?? 0);
    $paciente_id    = (int)($_POST['paciente_id'] ?? 0);
    $usuario_id     = $_SESSION['user_id'] ?? 0;
    $destino_id     = (int)($_POST['destino_id'] ?? 0);

    $medio_pago = $_POST['medio_pago'] ?? 'efectivo';
    $empleado_destino_id = !empty($_POST['empleado_destino_id'])
        ? (int)$_POST['empleado_destino_id']
        : null;

    /* ==============================
       🧠 VALIDACIONES
    ============================== */
    if (!in_array($tipo, ['ingreso', 'egreso'], true)) {
        throw new Exception("Tipo inválido");
    }

    if ($concepto === '') {
        throw new Exception("Debe ingresar un concepto");
    }

    if (!$usuario_id) {
        throw new Exception("Sesión expirada");
    }

    if ($medio_pago === 'transferencia' && !$empleado_destino_id) {
        throw new Exception("Debe seleccionar empleado destino");
    }

    /* ==============================
       🧠 FUNCION TIPO PACIENTE (CLAVE)
    ============================== */
    function obtenerTipoPaciente(PDO $pdo, int $paciente_id): string
    {
        if ($paciente_id <= 0) return 'particular';

        $stmt = $pdo->prepare("
        SELECT 
            p.tipo_socio, 
            p.fecha_alta,
            GREATEST(0, COALESCE(PERIOD_DIFF(
                DATE_FORMAT(CURDATE(), '%Y%m'),
                DATE_FORMAT(MAX(pa.fecha_correspondiente), '%Y%m')
            ), 999)) AS meses_adeudados,
            MAX(pa.fecha_correspondiente) AS ultimo_pago
        FROM pacientes p
        LEFT JOIN pagos_afiliados pa ON pa.paciente_id = p.Id
        WHERE p.Id = ?
        GROUP BY p.Id
    ");
        $stmt->execute([$paciente_id]);
        $p = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$p) return 'particular';

        // 🟣 Vitalicio
        if (strtolower($p['tipo_socio']) === 'vitalicio') return 'socio';

        // 🟡 Primeros 90 días → particular (ANTES que al día)
        if (!empty($p['fecha_alta']) && $p['fecha_alta'] !== '0000-00-00') {
            $dias = (new DateTime())->diff(new DateTime($p['fecha_alta']))->days;
            if ($dias <= 90) return 'particular';
        }

        // ✅ Al día → socio
        if ($p['ultimo_pago'] && (int)$p['meses_adeudados'] <= 1) return 'socio';

        // 🔴 Nunca pagó o moroso
        return 'particular';
    }

    $tipoPaciente = strtolower(obtenerTipoPaciente($pdo, $paciente_id));

    /* ==============================
       🏦 CAJA ABIERTA
    ============================== */
    $stmt = $pdo->prepare("
        SELECT * 
        FROM caja_sesion 
        WHERE usuario_id = ? AND estado = 'abierta'
        LIMIT 1
    ");
    $stmt->execute([$usuario_id]);
    $caja = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$caja) {
        throw new Exception("No hay caja abierta");
    }

    $caja_id = (int)$caja['caja_id'];
    $caja_sesion_id = (int)$caja['id'];

    /* ==============================
       🔢 NUMERACIÓN
    ============================== */
    $stmt = $pdo->prepare("SELECT MAX(numero) FROM cobros WHERE punto_venta = ? FOR UPDATE");
    $stmt->execute([$caja_id]);

    $ultimo = (int)$stmt->fetchColumn();
    $nuevo  = $ultimo ? $ultimo + 1 : 1;

    $numeroCompleto = str_pad($caja_id, 4, '0', STR_PAD_LEFT) . '-' .
        str_pad($nuevo, 8, '0', STR_PAD_LEFT);

    /* ==============================
       💰 PRECIO REAL (CLAVE)
    ============================== */
    $monto = $monto_input;

    if ($practica_id > 0) {

        $stmt = $pdo->prepare("
            SELECT precio 
            FROM practicas_precios
            WHERE practica_id = ? 
              AND tipo_paciente = ?
              AND activo = 1
            LIMIT 1
        ");
        $stmt->execute([$practica_id, $tipoPaciente]);

        $precio = (float)$stmt->fetchColumn();

        if (!$precio) {
            throw new Exception("No hay precio configurado para esta práctica");
        }

        // 🔥 USAMOS EL PRECIO REAL
        $monto = $precio;
    }

    if ($monto <= 0) {
        throw new Exception("Monto inválido");
    }

    /* ==============================
       📄 INSERT COBRO
    ============================== */
    $stmt = $pdo->prepare("
        INSERT INTO cobros 
        (fecha, tipo, total, concepto, usuario_id, caja_id, caja_sesion_id, estado, paciente_id, profesional_id, punto_venta, numero, numero_completo, medio_pago, empleado_destino_id, transferencia_tipo)
        VALUES (NOW(), ?, ?, ?, ?, ?, ?, 'activo', ?, ?, ?, ?, ?, ?, ?, 'clinica')
    ");

    $stmt->execute([
        $tipo,
        $monto,
        $concepto,
        $usuario_id,
        $caja_id,
        $caja_sesion_id,
        $paciente_id ?: null,
        $profesional_id ?: null,
        $caja_id,
        $nuevo,
        $numeroCompleto,
        $medio_pago,
        $empleado_destino_id
    ]);

    $cobro_id = $pdo->lastInsertId();

    /* ==============================
       📋 DETALLE
    ============================== */
    if ($practica_id > 0) {

        $stmt = $pdo->prepare("SELECT nombre FROM practicas WHERE id = ?");
        $stmt->execute([$practica_id]);
        $nombrePractica = $stmt->fetchColumn() ?: 'Práctica';

        $stmt = $pdo->prepare("
            INSERT INTO cobros_detalle
            (cobro_id, practica_id, nombre, precio)
            VALUES (?, ?, ?, ?)
        ");

        $stmt->execute([
            $cobro_id,
            $practica_id,
            $nombrePractica,
            $monto
        ]);
    }

    /* ==============================
       🔥 REPARTO (MISMO QUE TURNOS)
    ============================== */
    $stmtInsert = $pdo->prepare("
        INSERT INTO cobros_reparto (cobro_id, destino_id, monto)
        VALUES (?, ?, ?)
    ");

    if ($practica_id > 0) {

        $stmt = $pdo->prepare("
            SELECT id 
            FROM practicas_reparto
            WHERE practica_id = ? 
              AND (profesional_id = ? OR profesional_id IS NULL)
              AND tipo_paciente = ?
            ORDER BY profesional_id DESC
            LIMIT 1
        ");
        $stmt->execute([$practica_id, $profesional_id, $tipoPaciente]);

        $rep_id = $stmt->fetchColumn();

        if (!$rep_id) {
            throw new Exception("No hay reparto configurado");
        }

        $stmt = $pdo->prepare("
            SELECT destino_id, tipo_id, valor
            FROM practicas_reparto_detalle
            WHERE reparto_id = ?
        ");
        $stmt->execute([$rep_id]);
        $reglas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $fijos = 0;
        $porc = [];
        $repartoFinal = [];

        foreach ($reglas as $r) {

            $dest = (int)$r['destino_id'];

            if (!isset($repartoFinal[$dest])) {
                $repartoFinal[$dest] = 0;
            }

            if ((int)$r['tipo_id'] === 2) {
                $fijos += $r['valor'];
                $repartoFinal[$dest] += $r['valor'];
            } else {
                $porc[] = $r;
            }
        }

        if ($fijos > $monto) {
            throw new Exception("Error de reparto: fijos ($fijos) > total ($monto)");
        }

        $base = $monto - $fijos;

        foreach ($porc as $r) {
            $dest = (int)$r['destino_id'];
            $repartoFinal[$dest] += ($base * $r['valor']) / 100;
        }

        foreach ($repartoFinal as $dest => $m) {
            $stmtInsert->execute([$cobro_id, $dest, round($m, 2)]);
        }
    } else {

        if ($destino_id <= 0) {
            throw new Exception("Debe seleccionar un destino");
        }

        $stmtInsert->execute([$cobro_id, $destino_id, $monto]);
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'cobro_id' => $cobro_id,
        'numero'   => $numeroCompleto
    ]);
} catch (Exception $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
