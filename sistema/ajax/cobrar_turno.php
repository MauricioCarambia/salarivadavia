<?php
require_once __DIR__ . '/../inc/session.php';
require_once __DIR__ . '/../inc/csrf.php';
requerirCsrf();
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/services/afiliados.php';
header('Content-Type: application/json');

try {
    $turno_id = (int) ($_POST['turno_id'] ?? 0);
    $practicas = $_POST['practicas'] ?? [];
    $medio_pago = $_POST['medio_pago'] ?? 'efectivo';
    $transferencia_tipo = $medio_pago === 'transferencia'
        ? ($_POST['transferencia_tipo'] ?? null)
        : null;

    $empleado_destino_id = !empty($_POST['empleado_destino_id'])
        ? (int) $_POST['empleado_destino_id']
        : null;

    if ($medio_pago === 'transferencia') {

        // Solo se exige empleado si es transferencia a clínica
        if ($transferencia_tipo === 'clinica' && !$empleado_destino_id) {
            throw new Exception("Debe seleccionar el empleado destino de la transferencia");
        }

        // Si es profesional, forzamos null (no se usa empleado)
        if ($transferencia_tipo === 'profesional') {
            $empleado_destino_id = null;
        }
    }

    if (!$turno_id || empty($practicas)) {
        throw new Exception("Datos incompletos");
    }

    $usuarioId = $_SESSION['user_id'] ?? 0;
    if (!$usuarioId) {
        throw new Exception('Usuario no autenticado');
    }

    /* =========================
       CAJA
    ========================= */
    function obtenerCajaAbierta($pdo, $usuarioId)
    {
        $stmt = $pdo->prepare("
            SELECT cs.*, c.id AS caja_id
            FROM caja_sesion cs
            INNER JOIN cajas c ON c.id = cs.caja_id
            WHERE cs.estado = 'abierta'
              AND cs.usuario_id = ?
            LIMIT 1
        ");
        $stmt->execute([$usuarioId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }



    $caja = obtenerCajaAbierta($pdo, $usuarioId);
    if (!$caja) throw new Exception("No hay caja abierta");

    $caja_id = (int)$caja['caja_id'];
    $caja_sesion_id = (int)$caja['id'];

    $pdo->beginTransaction();

    /* =========================
       TURNO
    ========================= */
    $stmt = $pdo->prepare("
        SELECT t.*, p.Id AS paciente_id, pr.Id AS profesional_id
        FROM turnos t
        INNER JOIN pacientes p ON p.Id = t.paciente_id
        INNER JOIN profesionales pr ON pr.Id = t.profesional_id
        WHERE t.Id = ?
        LIMIT 1
    ");
    $stmt->execute([$turno_id]);
    $turno = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$turno) throw new Exception("Turno no encontrado");

    $paciente_id = (int)$turno['paciente_id'];
    $profesional_id = (int)$turno['profesional_id'];

    $estadoAfiliado = obtenerEstadoAfiliado($pdo, $paciente_id);

    $tipoPaciente = $estadoAfiliado['cobra_como']; // socio o particular

    /* =========================
       NUMERACIÓN
    ========================= */
    $stmt = $pdo->prepare("SELECT MAX(numero) FROM cobros WHERE punto_venta = ? FOR UPDATE");
    $stmt->execute([$caja_id]);
    $ultimo = (int)$stmt->fetchColumn();
    $nuevo = $ultimo ? $ultimo + 1 : 1;

    $numeroCompleto = str_pad($caja_id, 4, '0', STR_PAD_LEFT) . '-' .
        str_pad($nuevo, 8, '0', STR_PAD_LEFT);

    $total = 0;
    $detalle = [];
    $repartoTotal = [];

    /* FIX TRANSFERENCIAS */
    $deudaClinica = 0;
    $liqProfesional = 0;

    foreach ($practicas as $idPrac) {

        $idPrac = (int)$idPrac;

        $stmt = $pdo->prepare("
            SELECT precio 
            FROM practicas_precios
            WHERE practica_id = ? AND tipo_paciente = ? AND activo = 1
            LIMIT 1
        ");
        $stmt->execute([$idPrac, $tipoPaciente]);
        $precio = (float)$stmt->fetchColumn();

        if (!$precio) throw new Exception("Sin precio práctica $idPrac");

        $stmt = $pdo->prepare("SELECT nombre FROM practicas WHERE id = ?");
        $stmt->execute([$idPrac]);
        $nombre = $stmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT id FROM practicas_reparto
            WHERE practica_id = ? 
              AND (profesional_id = ? OR profesional_id IS NULL)
              AND tipo_paciente = ?
            ORDER BY profesional_id DESC
            LIMIT 1
        ");
        $stmt->execute([$idPrac, $profesional_id, $tipoPaciente]);
        $rep_id = $stmt->fetchColumn();

        $reparto = [];

        if ($rep_id) {

            $stmt = $pdo->prepare("
                SELECT d.valor, t.nombre AS tipo, dr.nombre AS destino, dr.categoria
                FROM practicas_reparto_detalle d
                INNER JOIN tipos_reparto t ON t.id = d.tipo_id
                INNER JOIN destinos_reparto dr ON dr.id = d.destino_id
                WHERE d.reparto_id = ?
            ");
            $stmt->execute([$rep_id]);
            $reglas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $fijos = 0;
            $porc = [];

            foreach ($reglas as $r) {

                $dest = strtolower($r['destino']);

                if (!isset($reparto[$dest])) {
                    $reparto[$dest] = [
                        'monto' => 0,
                        'categoria' => $r['categoria']
                    ];
                }

                if ($r['tipo'] === 'monto fijo') {
                    $fijos += $r['valor'];
                    $reparto[$dest]['monto'] += $r['valor'];
                } else {
                    $porc[] = $r;
                }
            }

            $base = $precio - $fijos;

            foreach ($porc as $r) {
                $dest = strtolower($r['destino']);
                $reparto[$dest]['monto'] += round(($base * $r['valor']) / 100, 2);
            }

            // Compensar diferencia de centavos para que el reparto cierre exacto
            $sumaReparto = array_sum(array_column($reparto, 'monto'));
            $diferencia  = round($precio - $sumaReparto, 2);
            if ($diferencia != 0.0) {
                $destMayor = null;
                foreach ($reparto as $k => $v) {
                    if ($destMayor === null || $v['monto'] > $reparto[$destMayor]['monto']) {
                        $destMayor = $k;
                    }
                }
                $reparto[$destMayor]['monto'] = round($reparto[$destMayor]['monto'] + $diferencia, 2);
            }
        }

        foreach ($reparto as $dest => $info) {
            $repartoTotal[$dest]['monto'] = ($repartoTotal[$dest]['monto'] ?? 0) + $info['monto'];
            $repartoTotal[$dest]['categoria'] = $info['categoria'];
        }

        $total += $precio;

        $detalle[] = [
            'practica_id' => $idPrac,
            'nombre' => $nombre,
            'precio' => $precio
        ];
    }

    /* =========================
       FIX TRANSFERENCIAS
    ========================= */
    if ($medio_pago === 'transferencia' && $transferencia_tipo === 'profesional') {
        foreach ($repartoTotal as $r) {
            if (($r['categoria'] ?? '') === 'profesional') {
                $liqProfesional += $r['monto'];
            } else {
                $deudaClinica += $r['monto'];
            }
        }
    }

    /* =========================
       INSERT COBRO (RESTO ORIGINAL)
    ========================= */
    $stmt = $pdo->prepare("
    INSERT INTO cobros 
    (
        turno_id,
        paciente_id,
        profesional_id,
        total,
        usuario_id,
        tipo,
        caja_id,
        caja_sesion_id,
        punto_venta,
        numero,
        numero_completo,
        medio_pago,
        empleado_destino_id,
        transferencia_tipo,
        deuda_clinica,
        concepto
    )
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
");

    $concepto = implode(', ', array_column($detalle, 'nombre'));
    $tipoCobro = 'ingreso';

    $stmt->execute([
        $turno_id,
        $paciente_id,
        $profesional_id,
        $total,
        $usuarioId,
        $tipoCobro,
        $caja_id,
        $caja_sesion_id,
        $caja_id,
        $nuevo,
        $numeroCompleto,
        $medio_pago,
        $empleado_destino_id,
        $transferencia_tipo,
        $deudaClinica,
        $concepto
    ]);

    $cobro_id = $pdo->lastInsertId();

    /* =========================
       DETALLE COBRO (NO TOCADO)
    ========================= */
    $stmt = $pdo->prepare("
        INSERT INTO cobros_detalle (cobro_id, practica_id, nombre, precio)
        VALUES (?,?,?,?)
    ");

    foreach ($detalle as $d) {
        $stmt->execute([
            $cobro_id,
            $d['practica_id'],
            $d['nombre'],
            $d['precio']
        ]);
    }

    /* =========================
       REPARTO COBRO (NO TOCADO)
    ========================= */
    $stmt = $pdo->prepare("
        INSERT INTO cobros_reparto (cobro_id, destino_id, monto)
        VALUES (?,?,?)
    ");

    $stmtDest = $pdo->query("SELECT id, nombre FROM destinos_reparto");
    $map = [];

    foreach ($stmtDest as $d) {
        $map[strtolower($d['nombre'])] = $d['id'];
    }

    foreach ($repartoTotal as $dest => $info) {

        if ($info['monto'] <= 0) continue;

        $key = strtolower($dest);

        if (!isset($map[$key])) {
            throw new Exception("Destino no existe: $dest");
        }

        $stmt->execute([
            $cobro_id,
            $map[$key],
            $info['monto']
        ]);
    }


    /* =========================
       UPDATE TURNO
    ========================= */
    $stmt = $pdo->prepare("UPDATE turnos SET asistio = 1, pago = COALESCE(pago, 0) + ?, fecha_actual = NOW() WHERE Id = ?");
    $stmt->execute([$total, $turno_id]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'total' => $total,
        'numero' => $numeroCompleto,
        'detalle' => $detalle,
        'medio_pago' => $medio_pago
    ]);
} catch (Exception $e) {

    if ($pdo->inTransaction()) $pdo->rollBack();

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
