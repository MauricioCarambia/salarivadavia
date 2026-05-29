<?php
require_once __DIR__ . '/../inc/db.php';

header('Content-Type: application/json');

$practica_id = (int)($_GET['practica_id'] ?? 0);
$paciente_id = (int)($_GET['paciente_id'] ?? 0);

if ($practica_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Práctica inválida'
    ]);
    exit;
}

/* =============================
   🔥 TIPO DE PACIENTE
=============================*/
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

$tipo_paciente = obtenerTipoPaciente($pdo, $paciente_id);

/* =============================
   🔥 PRECIO
=============================*/
$stmt = $pdo->prepare("
    SELECT precio
    FROM practicas_precios
    WHERE practica_id = :practica_id
      AND tipo_paciente = :tipo_paciente
      AND activo = 1
      AND (fecha_desde IS NULL OR fecha_desde <= NOW())
      AND (fecha_hasta IS NULL OR fecha_hasta >= NOW())
    ORDER BY fecha_desde DESC
    LIMIT 1
");

$stmt->execute([
    ':practica_id' => $practica_id,
    ':tipo_paciente' => $tipo_paciente
]);

$precio = $stmt->fetchColumn();

/* =============================
   🔥 NOMBRE PRÁCTICA
=============================*/
$stmt = $pdo->prepare("
    SELECT nombre
    FROM practicas
    WHERE Id = :id
    LIMIT 1
");

$stmt->execute([':id' => $practica_id]);
$nombre = $stmt->fetchColumn();

/* =============================
   VALIDACIÓN
=============================*/
if ($precio === false || !$nombre) {
    echo json_encode([
        'success' => false,
        'message' => 'No se encontró precio o práctica'
    ]);
    exit;
}

/* =============================
   RESPUESTA
=============================*/
echo json_encode([
    'success' => true,
    'nombre' => $nombre . ' (' . strtoupper($tipo_paciente) . ')',
    'precio' => (float)$precio,
    'tipo_paciente' => $tipo_paciente
]);
