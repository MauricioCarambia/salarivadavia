<?php

function obtenerEstadoAfiliado(PDO $pdo, int $paciente_id): array
{
    $stmt = $pdo->prepare("
        SELECT
            p.tipo_socio,
            p.fecha_alta,

            MAX(pa.fecha_correspondiente) AS ultimo_pago,

            CASE
    WHEN MAX(pa.fecha_correspondiente) IS NULL THEN 999
    ELSE GREATEST(
        0,
        PERIOD_DIFF(
            DATE_FORMAT(
                DATE_SUB(CURDATE(), INTERVAL 1 MONTH),
                '%Y%m'
            ),
            DATE_FORMAT(
                MAX(pa.fecha_correspondiente),
                '%Y%m'
            )
        )
    )
END AS meses_adeudados

        FROM pacientes p

        LEFT JOIN pagos_afiliados pa
            ON pa.paciente_id = p.Id

        WHERE p.Id = ?

        GROUP BY p.Id
    ");

    $stmt->execute([$paciente_id]);

    $r = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$r) {

        return [
            'tipo'            => 'particular',
            'cobra_como'      => 'particular',
            'meses_adeudados' => 999,
            'ultimo_pago'     => null
        ];
    }

    // Vitalicio
    if (strtolower($r['tipo_socio'] ?? '') === 'vitalicio') {

        return [
            'tipo'            => 'vitalicio',
            'cobra_como'      => 'socio',
            'meses_adeudados' => 0,
            'ultimo_pago'     => $r['ultimo_pago']
        ];
    }

    // Período de gracia (3 meses)
    if (
        !empty($r['fecha_alta']) &&
        $r['fecha_alta'] !== '0000-00-00'
    ) {

        $alta = new DateTime($r['fecha_alta']);
        $hoy  = new DateTime();

        $dias = $alta->diff($hoy)->days;

        if ($dias <= 90) {

            return [
                'tipo'            => 'nuevo',
                'cobra_como'      => 'particular',
                'meses_adeudados' => (int)$r['meses_adeudados'],
                'ultimo_pago'     => $r['ultimo_pago']
            ];
        }
    }

    // Nunca pagó
    if (!$r['ultimo_pago']) {

        return [
            'tipo'            => 'particular',
            'cobra_como'      => 'particular',
            'meses_adeudados' => 999,
            'ultimo_pago'     => null
        ];
    }

    // Hasta 2 meses adeudados
    if ((int)$r['meses_adeudados'] <= 2) {

        return [
            'tipo'            => 'socio',
            'cobra_como'      => 'socio',
            'meses_adeudados' => (int)$r['meses_adeudados'],
            'ultimo_pago'     => $r['ultimo_pago']
        ];
    }

    // Moroso
    return [
        'tipo'            => 'moroso',
        'cobra_como'      => 'particular',
        'meses_adeudados' => (int)$r['meses_adeudados'],
        'ultimo_pago'     => $r['ultimo_pago']
    ];
}
