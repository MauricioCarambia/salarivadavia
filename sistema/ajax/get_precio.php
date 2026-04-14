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
    if ($paciente_id <= 0) {
        return 'particular';
    }

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

    $ultimo = new DateTime(date('Y-m-01', strtotime($ultimaFecha)));
    $actual = new DateTime(date('Y-m-01'));

    $diff = $ultimo->diff($actual);
    $mesesDeuda = ($diff->y * 12) + $diff->m;

    return ($mesesDeuda <= 3) ? 'socio' : 'particular';
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
    'nombre' => $nombre,
    'precio' => (float)$precio,
    'tipo_paciente' => $tipo_paciente
]);