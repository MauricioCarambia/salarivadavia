<?php
require_once "../inc/db.php";
header('Content-Type: application/json');

try {

    $turno_id = $_POST['turno_id'] ?? 0;
    $practicas = $_POST['practicas'] ?? [];

    if (!$turno_id || empty($practicas)) {
        throw new Exception("Datos incompletos");
    }

    /* =========================
       🔎 TURNO
    ========================= */
    $stmt = $pdo->prepare("
        SELECT t.*, p.obra_social_id, pr.id AS profesional_id
        FROM turnos t
        INNER JOIN pacientes p ON p.Id = t.paciente_id
        INNER JOIN profesionales pr ON pr.Id = t.profesional_id
        WHERE t.Id = ?
    ");
    $stmt->execute([$turno_id]);
    $turno = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$turno) {
        throw new Exception("Turno no encontrado");
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
    $profesional_id = $turno['profesional_id'];

    $detalle = [];

    $totales = [
        'total' => 0,
        'profesional' => 0,
        'clinica' => 0,
        'farmacia' => 0,
        'patologia' => 0
    ];

    foreach ($practicas as $practica_id) {

        /* =========================
           💰 PRECIO
        ========================= */
        $stmt = $pdo->prepare("
            SELECT precio 
            FROM practicas_precios
            WHERE practica_id=? AND tipo_paciente=? AND activo=1
            ORDER BY fecha_desde DESC
            LIMIT 1
        ");
        $stmt->execute([$practica_id, $tipoPaciente]);
        $precio = (float) $stmt->fetchColumn();

        /* =========================
           📄 NOMBRE
        ========================= */
        $stmt = $pdo->prepare("SELECT nombre FROM practicas WHERE id=?");
        $stmt->execute([$practica_id]);
        $nombre = $stmt->fetchColumn();

        /* =========================
           🔀 REPARTO
        ========================= */
        $stmt = $pdo->prepare("
            SELECT id FROM practicas_reparto
            WHERE practica_id=?
            AND (profesional_id=? OR profesional_id IS NULL)
            AND tipo_paciente=?
            ORDER BY profesional_id DESC
            LIMIT 1
        ");
        $stmt->execute([$practica_id, $profesional_id, $tipoPaciente]);
        $rep_id = $stmt->fetchColumn();

        $r_prof = 0;
        $r_cli = 0;
        $r_far = 0;
        $r_pat = 0;

        if ($rep_id) {

            $stmt = $pdo->prepare("
                SELECT * FROM practicas_reparto_detalle
                WHERE reparto_id=?
            ");
            $stmt->execute([$rep_id]);

            foreach ($stmt as $r) {

                $valor = ($r['tipo'] == 'porcentaje')
                    ? ($precio * $r['valor']) / 100
                    : $r['valor'];

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

        /* =========================
           📦 DETALLE
        ========================= */
        $detalle[] = [
            'nombre' => $nombre,
            'precio' => (float) $precio,
            'profesional' => (float) $r_prof,
            'clinica' => (float) $r_cli,
            'farmacia' => (float) $r_far,
            'patologia' => (float) $r_pat
        ];

        /* =========================
           📊 TOTALES
        ========================= */
        $totales['total'] += $precio;
        $totales['profesional'] += $r_prof;
        $totales['clinica'] += $r_cli;
        $totales['farmacia'] += $r_far;
        $totales['patologia'] += $r_pat;
    }

    echo json_encode([
        'success' => true,
        'detalle' => $detalle,
        'totales' => [
            'total' => (float) $totales['total'],
            'profesional' => (float) $totales['profesional'],
            'clinica' => (float) $totales['clinica'],
            'farmacia' => (float) $totales['farmacia'],
            'patologia' => (float) $totales['patologia']
        ]
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}