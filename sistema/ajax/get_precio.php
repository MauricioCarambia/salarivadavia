<?php
require_once __DIR__ . '/../inc/session.php';

if (empty($_SESSION['login']) || $_SESSION['login'] !== 'si') {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

require_once __DIR__ . '/../inc/csrf.php';
requerirCsrf();
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/services/afiliados.php';
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
$estadoAfiliado = obtenerEstadoAfiliado($pdo, $paciente_id);

$tipoPaciente = $estadoAfiliado['cobra_como'];

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
    ':practica_id'   => $practica_id,
    ':tipo_paciente' => $tipoPaciente
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

    'tipo_paciente' => $tipoPaciente, // socio o particular

    'afiliado' => [
        'tipo' => $estadoAfiliado['tipo'], // socio, moroso, nuevo, vitalicio, particular
        'cobra_como' => $estadoAfiliado['cobra_como'],
        'meses_adeudados' => $estadoAfiliado['meses_adeudados'],
        'ultimo_pago' => $estadoAfiliado['ultimo_pago']
    ]
]);
