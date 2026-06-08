<?php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/services/afiliados.php';
header('Content-Type: application/json');

try {

    $turno_id = (int) ($_POST['turno_id'] ?? 0);
    $practicas = $_POST['practicas'] ?? [];
    $medio_pago = $_POST['medio_pago'] ?? 'efectivo';
    $transferencia_tipo = $_POST['transferencia_tipo'] ?? 'clinica';

    $empleado_destino_id = !empty($_POST['empleado_destino_id'])
        ? (int)$_POST['empleado_destino_id']
        : null;

    /* =========================
       🔒 VALIDACIONES
    ========================= */
    if ($medio_pago === 'transferencia' && !$empleado_destino_id) {
        throw new Exception("Debe seleccionar el destino de la transferencia");
    }

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


    $estadoAfiliado = obtenerEstadoAfiliado($pdo, $paciente_id);

    $tipoPaciente = $estadoAfiliado['cobra_como'];

    $detalle = [];
    $totales = [
        'total' => 0,
        'destinos' => [],
        'categorias' => [],
        'deuda_clinica' => 0
    ];

    $liquidacion_profesional = 0;
    $deuda_clinica = 0;

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
        $nombre = $stmt->fetchColumn() ?: 'Práctica #' . $practica_id;

        /* =========================
           🔀 REPARTO
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

        $repartoFormateado = [];

        if ($rep_id) {

            $stmt = $pdo->prepare("
                SELECT d.valor, t.nombre AS tipo, dr.nombre AS destino, dr.categoria
                FROM practicas_reparto_detalle d
                INNER JOIN tipos_reparto t ON t.id = d.tipo_id
                INNER JOIN destinos_reparto dr ON dr.id = d.destino_id
                WHERE d.reparto_id=?
                ORDER BY d.orden
            ");
            $stmt->execute([$rep_id]);
            $reglas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $reparto = [];
            $totalFijos = 0;
            $porcentajes = [];

            foreach ($reglas as $r) {
                $dest = $r['destino']; // ❗ SIN strtolower
                $reparto[$dest] = [
                    'monto' => 0,
                    'categoria' => $r['categoria']
                ];
            }

            foreach ($reglas as $r) {
                $dest = $r['destino'];

                if ($r['tipo'] === 'monto fijo') {
                    $totalFijos += (float)$r['valor'];
                    $reparto[$dest]['monto'] += (float)$r['valor'];
                } else {
                    $porcentajes[] = $r;
                }
            }

            $base = $precio - $totalFijos;

            if ($base < 0) {
                throw new Exception("Error reparto práctica $practica_id");
            }

            foreach ($porcentajes as $r) {
                $dest = $r['destino'];
                $reparto[$dest]['monto'] += ($base * $r['valor']) / 100;
            }

            foreach ($reparto as $dest => $info) {

                $monto = (float)$info['monto'];
                $categoria = $info['categoria'];

                $repartoFormateado[] = [
                    'destino' => $dest,
                    'valor' => $monto,
                    'categoria' => $categoria
                ];

                $totales['destinos'][$dest] = ($totales['destinos'][$dest] ?? 0) + $monto;
                $totales['categorias'][$categoria] = ($totales['categorias'][$categoria] ?? 0) + $monto;

                if ($medio_pago === 'transferencia' && $transferencia_tipo === 'profesional') {
                    if ($categoria === 'profesional') {
                        $liquidacion_profesional += $monto;
                    } else {
                        $deuda_clinica += $monto;
                        $totales['deuda_clinica'] += $monto;
                    }
                }
            }
        }

        $detalle[] = [
            'nombre' => $nombre,
            'precio' => $precio,
            'reparto' => $repartoFormateado
        ];

        $totales['total'] += $precio;
    }

    echo json_encode([
        'success' => true,
        'detalle' => $detalle,
        'totales' => $totales,
        'finanzas' => [
            'transferencia_tipo' => $transferencia_tipo,
            'liquidacion_profesional' => $liquidacion_profesional,
            'deuda_clinica' => $deuda_clinica
        ]
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
