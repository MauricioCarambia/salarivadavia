<?php
require_once "../inc/db.php";
header('Content-Type: application/json');

try {

    $turno_id = (int) ($_POST['turno_id'] ?? 0);
    $practicas = $_POST['practicas'] ?? [];

    if (!$turno_id || empty($practicas)) {
        throw new Exception("Datos incompletos");
    }

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

        if (!$ultimaFecha) return 'particular';

        $ultimo = new DateTime(date('Y-m-01', strtotime($ultimaFecha)));
        $actual = new DateTime(date('Y-m-01'));

        $diff = $ultimo->diff($actual);
        $mesesDeuda = ($diff->y * 12) + $diff->m;

        return ($mesesDeuda <= 3) ? 'socio' : 'particular';
    }

    $tipoPaciente = strtolower(obtenerTipoPaciente($pdo, $paciente_id));

    $detalle = [];
    $totales = [
        'total' => 0,
        'destinos' => []
    ];

    /* =========================
       🔁 RECORRER PRÁCTICAS
    ========================= */
    foreach ($practicas as $practica_id) {

        $practica_id = (int) $practica_id;

        /* =========================
           💰 PRECIO
        ========================= */
        $stmt = $pdo->prepare("
            SELECT precio 
            FROM practicas_precios
            WHERE practica_id=? 
              AND tipo_paciente=? 
              AND activo=1
            ORDER BY fecha_desde DESC
            LIMIT 1
        ");
        $stmt->execute([$practica_id, $tipoPaciente]);
        $precio = (float) $stmt->fetchColumn();

        if (!$precio) {
            throw new Exception("Falta precio para práctica ID $practica_id");
        }

        /* =========================
           📄 NOMBRE
        ========================= */
        $stmt = $pdo->prepare("SELECT nombre FROM practicas WHERE id=?");
        $stmt->execute([$practica_id]);
        $nombre = $stmt->fetchColumn();

        if (!$nombre) {
            $nombre = 'Práctica #' . $practica_id;
        }

        /* =========================
           🔀 REPARTO CONFIG
        ========================= */
        $stmt = $pdo->prepare("
            SELECT id 
            FROM practicas_reparto
            WHERE practica_id=?
              AND (profesional_id=? OR profesional_id IS NULL)
              AND tipo_paciente=?
            ORDER BY profesional_id DESC
            LIMIT 1
        ");
        $stmt->execute([$practica_id, $profesional_id, $tipoPaciente]);
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
                WHERE d.reparto_id=?
                ORDER BY d.orden
            ");
            $stmt->execute([$rep_id]);

            $reglas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $totalFijos = 0;
            $porcentajes = [];

            foreach ($reglas as $r) {
                $dest = strtolower($r['destino']);

                $reparto[$dest] = [
                    'monto' => 0,
                    'categoria' => $r['categoria']
                ];
            }

            foreach ($reglas as $r) {

                $dest = strtolower($r['destino']);

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

           $totalCalc = array_sum(array_column($reparto, 'monto'));
            if (round($totalCalc, 2) != round($precio, 2)) {
                throw new Exception("Error de reparto en práctica $practica_id");
            }
        }

        /* =========================
           📦 FORMATEO
        ========================= */
        $repartoFormateado = [];

        foreach ($reparto as $dest => $info) {

    $monto = $info['monto'];
    $categoria = $info['categoria'];

    $repartoFormateado[] = [
        'destino' => $dest,
        'valor' => (float)$monto,
        'categoria' => $categoria
    ];

    if (!isset($totales['destinos'][$dest])) {
        $totales['destinos'][$dest] = 0;
    }

    $totales['destinos'][$dest] += $monto;

    // 🔥 NUEVO: TOTAL POR CATEGORÍA
    if (!isset($totales['categorias'][$categoria])) {
        $totales['categorias'][$categoria] = 0;
    }

    $totales['categorias'][$categoria] += $monto;
}

        $detalle[] = [
            'nombre' => $nombre,
            'precio' => (float)$precio,
            'reparto' => $repartoFormateado
        ];

        $totales['total'] += $precio;
    }

    echo json_encode([
        'success' => true,
        'detalle' => $detalle,
        'totales' => [
            'total' => (float)$totales['total'],
            'destinos' => $totales['destinos'],
            'categorias' => $totales['categorias'] ?? []
        ]
    ]);
} catch (Exception $e) {

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
