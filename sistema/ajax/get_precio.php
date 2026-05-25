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

    /* =========================
       TRAER PACIENTE
    =========================*/
    $stmt = $pdo->prepare("
        SELECT tipo_socio, fecha_alta
        FROM pacientes
        WHERE Id = :id
    ");
    $stmt->execute([':id' => $paciente_id]);
    $paciente = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$paciente) {
        return 'particular';
    }

    // 🟣 1. VITALICIO → SIEMPRE SOCIO
   if (!empty($paciente['tipo_socio']) && strtolower(trim($paciente['tipo_socio'])) === 'vitalicio') {
    return 'socio';
}

    /* =========================
       🟡 2. SOCIO NUEVO (GRACIA)
    =========================*/
    if (!empty($paciente['fecha_alta'])) {
        $hoy = new DateTime();
        $alta = new DateTime($paciente['fecha_alta']);
        $dias = $hoy->diff($alta)->days;

        if ($dias <= 30) {
            return 'particular';
        }
    }

    /* =========================
       🔵 3. DEUDA
    =========================*/
    $stmt = $pdo->prepare("
        SELECT MAX(fecha_correspondiente)
        FROM pagos_afiliados
        WHERE paciente_id = :id
    ");
    $stmt->execute([':id' => $paciente_id]);

    $ultimaFecha = $stmt->fetchColumn();

    // Nunca pagó
    if (!$ultimaFecha) {
        return 'particular';
    }

    $ultimo = new DateTime(date('Y-m-01', strtotime($ultimaFecha)));
    $actual = new DateTime(date('Y-m-01'));

    $diff = $ultimo->diff($actual);
    $mesesDeuda = ($diff->y * 12) + $diff->m;

    // 🔵 hasta 3 meses → socio
    if ($mesesDeuda <= 3) {
        return 'socio';
    }

    // 🔴 más de 3 meses → particular
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
