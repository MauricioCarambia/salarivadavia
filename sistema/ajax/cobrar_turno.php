<?php
session_name("turnos");
session_start();
require_once "../inc/db.php";
header('Content-Type: application/json');

try {
    $turno_id = (int) ($_POST['turno_id'] ?? 0);
    $practicas = $_POST['practicas'] ?? [];

    if (!$turno_id || empty($practicas)) {
        throw new Exception("Datos incompletos");
    }

    $usuarioId = $_SESSION['user_id'] ?? 0;
    if (!$usuarioId) {
        throw new Exception('Usuario no autenticado');
    }

    /* =========================
       🔒 FUNCIONES
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

    function obtenerTipoPaciente($pdo, $paciente_id)
    {
        $stmt = $pdo->prepare("
            SELECT MAX(fecha_correspondiente) 
            FROM pagos_afiliados
            WHERE paciente_id = ?
        ");
        $stmt->execute([$paciente_id]);

        $ultimaFecha = $stmt->fetchColumn();

        if (!$ultimaFecha) return 'particular';

        $ultimo = new DateTime(date('Y-m-01', strtotime($ultimaFecha)));
        $actual = new DateTime(date('Y-m-01'));

        $diff = $ultimo->diff($actual);
        $mesesDeuda = ($diff->y * 12) + $diff->m;

        return ($mesesDeuda <= 3) ? 'socio' : 'particular';
    }

    /* =========================
       🔒 CAJA
    ========================= */
    $cajaAbierta = obtenerCajaAbierta($pdo, $usuarioId);

    if (!$cajaAbierta) {
        throw new Exception('No hay caja abierta');
    }

    $caja_sesion_id = (int) $cajaAbierta['id'];
    $caja_id = (int) $cajaAbierta['caja_id'];

    $pdo->beginTransaction();

    /* =========================
       🔎 TURNO
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

    if (!$turno) {
        throw new Exception("Turno no encontrado");
    }

    $paciente_id = (int) $turno['paciente_id'];
    $profesional_id = (int) $turno['profesional_id'];

    $tipoPacienteDB = strtolower(obtenerTipoPaciente($pdo, $paciente_id));

    /* =========================
       🧾 NUMERACIÓN
    ========================= */
    $stmt = $pdo->prepare("SELECT MAX(numero) FROM cobros WHERE punto_venta = ? FOR UPDATE");
    $stmt->execute([$caja_id]);
    $ultimoNumero = (int) $stmt->fetchColumn();
    $nuevoNumero = $ultimoNumero ? $ultimoNumero + 1 : 1;

    $numeroCompleto = str_pad($caja_id, 4, '0', STR_PAD_LEFT) . '-' .
                      str_pad($nuevoNumero, 8, '0', STR_PAD_LEFT);

    /* =========================
       💰 PROCESO
    ========================= */
    $totalGeneral = 0;
    $detalle = [];
    $repartoTotal = [];

    foreach ($practicas as $practica_id) {

        $practica_id = (int) $practica_id;

        // PRECIO
        $stmt = $pdo->prepare("
            SELECT precio 
            FROM practicas_precios
            WHERE practica_id = ? 
              AND tipo_paciente = ? 
              AND activo = 1
            LIMIT 1
        ");
        $stmt->execute([$practica_id, $tipoPacienteDB]);
        $precio = (float) $stmt->fetchColumn();

        if (!$precio) {
            throw new Exception("Falta precio para práctica ID $practica_id");
        }

        // NOMBRE
        $stmt = $pdo->prepare("SELECT nombre FROM practicas WHERE id = ?");
        $stmt->execute([$practica_id]);
        $nombre = $stmt->fetchColumn();

        // REPARTO
        $stmt = $pdo->prepare("
            SELECT id FROM practicas_reparto
            WHERE practica_id = ? 
              AND (profesional_id = ? OR profesional_id IS NULL)
              AND tipo_paciente = ?
            ORDER BY profesional_id DESC
            LIMIT 1
        ");
        $stmt->execute([$practica_id, $profesional_id, $tipoPacienteDB]);
        $rep_id = $stmt->fetchColumn();

        $reparto = [];

        if ($rep_id) {

            $stmt = $pdo->prepare("
                SELECT 
                    d.valor,
                    t.nombre AS tipo,
                    dr.nombre AS destino,
                    dr.categoria
                FROM practicas_reparto_detalle d
                INNER JOIN tipos_reparto t ON t.id = d.tipo_id
                INNER JOIN destinos_reparto dr ON dr.id = d.destino_id
                WHERE d.reparto_id = ?
            ");
            $stmt->execute([$rep_id]);

            $reglas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $totalFijos = 0;
            $porcentajes = [];

            foreach ($reglas as $r) {

                $dest = strtolower($r['destino']);

                if (!isset($reparto[$dest])) {
                   $reparto[$dest] = [
    'monto' => 0,
    'categoria' => $r['categoria']
];
                }

                if ($r['tipo'] === 'monto fijo') {

                    $valor = (float)$r['valor'];
                    $totalFijos += $valor;
                   $reparto[$dest]['monto'] += $valor;

                } else {
                    $porcentajes[] = $r;
                }
            }

            $base = $precio - $totalFijos;

            if ($base < 0) {
                throw new Exception("Los fijos superan el precio (práctica $practica_id)");
            }

            foreach ($porcentajes as $r) {

                $dest = strtolower($r['destino']);
                $valor = ($base * $r['valor']) / 100;

               $reparto[$dest]['monto'] += $valor;
            }

            // VALIDACIÓN
            if (round(array_sum(array_column($reparto, 'monto')), 2) != round($precio, 2)) {
                throw new Exception("Error de reparto en práctica $practica_id");
            }
        }

       foreach ($reparto as $dest => $info) {

    $monto = $info['monto'];
    $categoria = $info['categoria'];

    if (!isset($repartoTotal[$dest])) {
        $repartoTotal[$dest] = [
            'monto' => 0,
            'categoria' => $categoria
        ];
    }

    $repartoTotal[$dest]['monto'] += $monto;
}

        $totalGeneral += $precio;

        $detalle[] = [
            'practica_id' => $practica_id,
            'nombre' => $nombre,
            'precio' => $precio
        ];
    }

    /* =========================
       🧾 INSERT COBRO
    ========================= */
    $stmt = $pdo->prepare("
        INSERT INTO cobros 
        (turno_id, paciente_id, profesional_id, total, usuario_id, caja_id, caja_sesion_id, punto_venta, numero, numero_completo)
        VALUES (?,?,?,?,?,?,?,?,?,?)
    ");

    $stmt->execute([
        $turno_id,
        $paciente_id,
        $profesional_id,
        $totalGeneral,
        $usuarioId,
        $caja_id,
        $caja_sesion_id,
        $caja_id,
        $nuevoNumero,
        $numeroCompleto
    ]);

    $cobro_id = $pdo->lastInsertId();

    /* =========================
       DETALLE
    ========================= */
    $stmtDetalle = $pdo->prepare("
        INSERT INTO cobros_detalle (cobro_id, practica_id, nombre, precio)
        VALUES (?,?,?,?)
    ");

    foreach ($detalle as $d) {
        $stmtDetalle->execute([
            $cobro_id,
            $d['practica_id'],
            $d['nombre'],
            $d['precio']
        ]);
    }

    /* =========================
       REPARTO
    ========================= */
$stmtReparto = $pdo->prepare("
    INSERT INTO cobros_reparto (cobro_id, destino_id, monto)
    VALUES (?,?,?)
");

$stmt = $pdo->query("SELECT id, nombre FROM destinos_reparto");
$mapDestinos = [];

foreach ($stmt as $d) {
    $mapDestinos[strtolower($d['nombre'])] = $d['id'];
}

foreach ($repartoTotal as $destino => $info) {

    $monto = $info['monto'];

    if ($monto <= 0) continue;

    $destinoKey = strtolower($destino);

    if (!isset($mapDestinos[$destinoKey])) {
        throw new Exception("Destino no encontrado: $destino");
    }

    $stmtReparto->execute([
        $cobro_id,
        $mapDestinos[$destinoKey],
        $monto
    ]);
}

    /* =========================
       CAJA
    ========================= */
    $stmt = $pdo->prepare("
        INSERT INTO caja_movimientos 
        (caja_id, caja_sesion_id, tipo, concepto, monto, fecha, cobro_id, descripcion)
        VALUES (?,?,?,?,?,NOW(),?,?)
    ");

    $stmt->execute([
        $caja_id,
        $caja_sesion_id,
        'INGRESO',
        'Cobro ' . $numeroCompleto,
        $totalGeneral,
        $cobro_id,
        "Cobro turno #$turno_id"
    ]);
$totalesCategoria = [];

foreach ($repartoTotal as $info) {
    $cat = $info['categoria'];

    if (!isset($totalesCategoria[$cat])) {
        $totalesCategoria[$cat] = 0;
    }

    $totalesCategoria[$cat] += $info['monto'];
}
    /* =========================
       TURNO
    ========================= */
    $stmt = $pdo->prepare("UPDATE turnos SET asistio = 1, pago = ?, fecha_actual = NOW() WHERE Id = ?");
    $stmt->execute([$totalGeneral, $turno_id]);

    $pdo->commit();

 echo json_encode([
    'success' => true,
    'total' => $totalGeneral,
    'numero' => $numeroCompleto,
    'detalle' => array_map(function ($d) {
        return [
            'nombre' => $d['nombre'],
            'precio' => $d['precio']
        ];
    }, $detalle)
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