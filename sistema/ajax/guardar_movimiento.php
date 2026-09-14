<?php
require_once __DIR__ . '/../inc/session.php';
require_once __DIR__ . '/../inc/csrf.php';
requerirCsrf();
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/services/afiliados.php';
header('Content-Type: application/json');

try {
    $pdo->beginTransaction();

    /* ==============================
       📥 DATOS
    ============================== */
    $tipo                = strtolower(trim($_POST['tipo'] ?? ''));
    $concepto            = trim($_POST['concepto'] ?? '');
    $monto_input         = (float)($_POST['monto'] ?? 0);
    $practica_id         = (int)($_POST['practica_id'] ?? 0);
    $profesional_id      = (int)($_POST['profesional_id'] ?? 0);
    $paciente_id         = (int)($_POST['paciente_id'] ?? 0);
    $destino_id          = (int)($_POST['destino_id'] ?? 0);
    $medio_pago          = $_POST['medio_pago'] ?? 'efectivo';
    $transferencia_tipo  = $_POST['transferencia_tipo'] ?? null;

    $usuarioId = $_SESSION['user_id'] ?? 0;
    if (!$usuarioId) {
        throw new Exception('Usuario no autenticado');
    }

    $empleado_destino_id = !empty($_POST['empleado_destino_id'])
        ? (int)$_POST['empleado_destino_id']
        : null;

    $profesional_destino_id = null;

    /* ==============================
       🧠 VALIDACIONES
    ============================== */
    if (!in_array($tipo, ['ingreso', 'egreso'], true)) {
        throw new Exception("Tipo inválido");
    }

    if ($concepto === '') {
        throw new Exception("Debe ingresar un concepto");
    }

    if ($medio_pago === 'transferencia') {
        if (!in_array($transferencia_tipo, ['clinica', 'profesional'], true)) {
            throw new Exception("Tipo de transferencia inválido");
        }
        if ($transferencia_tipo === 'clinica' && !$empleado_destino_id) {
            throw new Exception("Debe seleccionar empleado destino");
        }
        if ($transferencia_tipo === 'profesional') {
            $profesional_destino_id = $profesional_id;
            $empleado_destino_id    = null;
        }
    }

    /* ==============================
       🧠 TIPO PACIENTE
    ============================== */
    $estadoAfiliado = obtenerEstadoAfiliado($pdo, $paciente_id);

    $tipoPaciente = $estadoAfiliado['cobra_como'];

    /* ==============================
       🏦 CAJA ABIERTA
    ============================== */
    $stmt = $pdo->prepare("
        SELECT cs.*, c.id AS caja_id
        FROM caja_sesion cs
        INNER JOIN cajas c ON c.id = cs.caja_id
        WHERE cs.estado = 'abierta'
          AND cs.usuario_id = ?
        LIMIT 1
    ");
    $stmt->execute([$usuarioId]);
    $caja = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$caja) throw new Exception("No hay caja abierta");

    $caja_id        = (int)$caja['caja_id'];
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
       💰 PRECIO REAL
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

        if (!$precio) throw new Exception("No hay precio configurado para esta práctica");

        $monto = $precio;
    }

    if ($monto <= 0) throw new Exception("Monto inválido");

    /* ==============================
       📄 INSERT COBRO
    ============================== */
    $deudaClinica = 0;

    $stmt = $pdo->prepare("
        INSERT INTO cobros (
            fecha, tipo, total, concepto,
            usuario_id, caja_id, caja_sesion_id, estado,
            paciente_id, profesional_id,
            punto_venta, numero, numero_completo,
            medio_pago, empleado_destino_id,
            transferencia_tipo, deuda_clinica
        ) VALUES (
            NOW(), ?, ?, ?,
            ?, ?, ?, 'activo',
            ?, ?,
            ?, ?, ?,
            ?, ?,
            ?, ?
        )
    ");

    $stmt->execute([
        $tipo,
        $monto,
        $concepto,
        $usuarioId,
        $caja_id,
        $caja_sesion_id,
        $paciente_id  ?: null,
        $practica_id > 0 ? $profesional_id : null,
        $caja_id,
        $nuevo,
        $numeroCompleto,
        $medio_pago,
        $empleado_destino_id,
        $transferencia_tipo,
        $deudaClinica
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
            INSERT INTO cobros_detalle (cobro_id, practica_id, nombre, precio)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$cobro_id, $practica_id, $nombrePractica, $monto]);
    }

    /* ==============================
       🔁 REPARTO
    ============================== */
    $stmtInsert = $pdo->prepare("
        INSERT INTO cobros_reparto (cobro_id, destino_id, monto)
        VALUES (?, ?, ?)
    ");

    $repartoFinal  = [];
    $liqProfesional = 0;

    if ($practica_id > 0) {

        $stmt = $pdo->prepare("
            SELECT id FROM practicas_reparto
            WHERE practica_id = ?
              AND (profesional_id = ? OR profesional_id IS NULL)
              AND tipo_paciente = ?
            ORDER BY profesional_id DESC
            LIMIT 1
        ");
        $stmt->execute([$practica_id, $profesional_id, $tipoPaciente]);
        $rep_id = $stmt->fetchColumn();

        if (!$rep_id) throw new Exception("No hay reparto configurado para esta práctica");

        $stmt = $pdo->prepare("
            SELECT d.destino_id, d.tipo_id, d.valor, dr.categoria
            FROM practicas_reparto_detalle d
            INNER JOIN destinos_reparto dr ON dr.id = d.destino_id
            WHERE d.reparto_id = ?
        ");
        $stmt->execute([$rep_id]);
        $reglas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $fijos = 0;
        $porc  = [];

        foreach ($reglas as $r) {
            $dest = (int)$r['destino_id'];

            if (!isset($repartoFinal[$dest])) {
                $repartoFinal[$dest] = ['monto' => 0, 'categoria' => $r['categoria']];
            }

            if ((int)$r['tipo_id'] === 2) {
                $fijos += $r['valor'];
                $repartoFinal[$dest]['monto'] += $r['valor'];
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
            $repartoFinal[$dest]['monto'] += ($base * $r['valor']) / 100;
        }

        foreach ($repartoFinal as $dest => $info) {
            $stmtInsert->execute([$cobro_id, $dest, round($info['monto'], 2)]);

            if ($transferencia_tipo === 'profesional') {
                if (strtolower($info['categoria']) === 'profesional') {
                    $liqProfesional += $info['monto'];
                } else {
                    $deudaClinica += $info['monto'];
                }
            }
        }

        if ($transferencia_tipo === 'profesional' && $deudaClinica > 0) {
            $stmt = $pdo->prepare("UPDATE cobros SET deuda_clinica = ? WHERE id = ?");
            $stmt->execute([round($deudaClinica, 2), $cobro_id]);
        }
    } else {

        if ($destino_id <= 0) throw new Exception("Debe seleccionar un destino");

        $stmtInsert->execute([$cobro_id, $destino_id, $monto]);
    }

    $pdo->commit();

    echo json_encode([
        'success'  => true,
        'cobro_id' => $cobro_id,
        'numero'   => $numeroCompleto
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
