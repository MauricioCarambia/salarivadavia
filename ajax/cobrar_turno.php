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
        echo json_encode([
            'success' => false,
            'message' => 'Usuario no autenticado'
        ]);
        exit;
    }

    /* =========================
       🔒 FUNCIONES
    ========================= */
    function obtenerCajaAbierta($pdo, $usuarioId)
{
    $stmt = $pdo->prepare("
        SELECT cs.*, c.id AS caja_id, c.nombre
        FROM caja_sesion cs
        INNER JOIN cajas c ON c.id = cs.caja_id
        WHERE cs.estado = 'abierta'
          AND cs.usuario_id = ?
        LIMIT 1
    ");
    $stmt->execute([$usuarioId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}


    /* =========================
       🔒 VERIFICAR CAJA ABIERTA
    ========================= */
    $cajaAbierta = obtenerCajaAbierta($pdo, $usuarioId);

    if (!$cajaAbierta) {
        echo json_encode([
            'success' => false,
            'message' => 'No se puede cobrar: la caja está cerrada'
        ]);
        exit;
    }
$caja_sesion_id = (int) $cajaAbierta['id'];
    $caja_id = (int) $cajaAbierta['caja_id'];

    $pdo->beginTransaction();

    /* =========================
       🔎 OBTENER TURNO
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

    /* =========================
       🧠 TIPO PACIENTE
    ========================= */
function obtenerTipoPaciente($pdo, $paciente_id)
{
    $stmt = $pdo->prepare("
        SELECT MAX(fecha_correspondiente) 
        FROM pagos_afiliados
        WHERE paciente_id = ?
    ");
    $stmt->execute([$paciente_id]);

    $ultimaFecha = $stmt->fetchColumn();

    if (!$ultimaFecha) {
        return 'particular';
    }

    // 🔥 convertir a año-mes
    $ultimo = new DateTime(date('Y-m-01', strtotime($ultimaFecha)));
    $actual = new DateTime(date('Y-m-01'));

    $diff = $ultimo->diff($actual);

    $mesesDeuda = ($diff->y * 12) + $diff->m;

    return ($mesesDeuda <= 3) ? 'socio' : 'particular';
}

    $tipoPaciente = obtenerTipoPaciente($pdo, $turno['paciente_id']);
    $tipoPacienteDB = strtolower($tipoPaciente);

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
       💰 TOTALES Y DETALLE
    ========================= */
    $totalGeneral = 0;
    $total_prof = $total_cli = $total_far = $total_pat = 0;
    $detalle = [];

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

        $r_prof = $r_cli = $r_far = $r_pat = 0;

        if ($rep_id) {
            $stmt = $pdo->prepare("SELECT * FROM practicas_reparto_detalle WHERE reparto_id = ?");
            $stmt->execute([$rep_id]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $valor = ($r['tipo'] === 'porcentaje') ? ($precio * $r['valor']) / 100 : (float) $r['valor'];
                switch ($r['destino']) {
                    case 'profesional':
                        $r_prof += $valor;
                        break;
                    case 'clinica':
                        $r_cli += $valor;
                        break;
                    case 'farmacia':
                        $r_far += $valor;
                        break;
                    case 'patologia':
                        $r_pat += $valor;
                        break;
                }
            }
        }

        $totalGeneral += $precio;
        $total_prof += $r_prof;
        $total_cli += $r_cli;
        $total_far += $r_far;
        $total_pat += $r_pat;

        $detalle[] = [
            'practica_id' => $practica_id,
            'nombre' => $nombre,
            'precio' => $precio
        ];
    }

    /* =========================
       🧾 INSERTAR COBRO
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
    $caja_sesion_id, // 🔥 CLAVE
    $caja_id,
    $nuevoNumero,
    $numeroCompleto
]);
    $cobro_id = $pdo->lastInsertId();

    /* =========================
       COBROS DETALLE
    ========================= */
    $stmtDetalle = $pdo->prepare("
        INSERT INTO cobros_detalle (cobro_id, practica_id, nombre, precio)
        VALUES (?,?,?,?)
    ");
    foreach ($detalle as $d) {
        $stmtDetalle->execute([$cobro_id, $d['practica_id'], $d['nombre'], $d['precio']]);
    }

    /* =========================
       COBROS REPARTO
    ========================= */
    $stmtReparto = $pdo->prepare("INSERT INTO cobros_reparto (cobro_id, destino, monto) VALUES (?,?,?)");
    $repartos = [
        'profesional' => $total_prof,
        'clinica' => $total_cli,
        'farmacia' => $total_far,
        'patologia' => $total_pat
    ];
    foreach ($repartos as $destino => $monto) {
        if ($monto > 0) {
            $stmtReparto->execute([$cobro_id, $destino, $monto]);
        }
    }

    /* =========================
       CAJA MOVIMIENTOS
    ========================= */
    $descripcion = "Cobro turno #$turno_id - Paciente ID $paciente_id";
$caja_sesion_id = (int) $cajaAbierta['id'];

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
    $descripcion
]);
    /* =========================
       ACTUALIZAR TURNO
    ========================= */
    $stmt = $pdo->prepare("UPDATE turnos SET asistio = 1, pago = ? WHERE Id = ?");
    $stmt->execute([$totalGeneral, $turno_id]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'total' => $totalGeneral,
        'numero' => $numeroCompleto,
        'cobro_id' => $cobro_id,
        'detalle' => $detalle
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction())
        $pdo->rollBack();
    echo json_encode([
        'success' => false,
        'message' => 'Error al cobrar: ' . $e->getMessage()
    ]);
}