<?php
require_once __DIR__ . '/../inc/db.php';

/* =============================
   FUNCIÓN: tipo paciente
=============================*/
function obtenerTipoPaciente(PDO $pdo, int $paciente_id): string
{
    if ($paciente_id <= 0) {
        return 'particular';
    }

    $stmt = $pdo->prepare("
        SELECT MAX(fecha_correspondiente) as ultima_cuota
        FROM pagos_afiliados
        WHERE paciente_id = :id
    ");

    $stmt->execute([':id' => $paciente_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || empty($row['ultima_cuota'])) {
        return 'particular';
    }

    $ultima = new DateTime($row['ultima_cuota']);
    $hoy = new DateTime();

    $ultima->modify('first day of this month');
    $hoy->modify('first day of this month');

    $diff = ($hoy->format('Y') - $ultima->format('Y')) * 12;
    $diff += ($hoy->format('m') - $ultima->format('m'));

    return ($diff >= 3) ? 'particular' : 'socio';
}

/* =============================
   INPUTS
=============================*/
$profesional_id = (int)($_GET['profesional_id'] ?? 0);
$paciente_id    = (int)($_GET['paciente_id'] ?? 0);

/* =============================
   VALIDACIÓN
=============================*/
if ($profesional_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Profesional inválido'
    ]);
    exit;
}

/* =============================
   LOGICA
=============================*/
$tipo_paciente = strtolower(obtenerTipoPaciente($pdo, $paciente_id));

/* =============================
   QUERY
=============================*/
$stmt = $pdo->prepare("
    SELECT DISTINCT p.Id, p.nombre
    FROM practicas p
    INNER JOIN practicas_reparto pr 
        ON pr.practica_id = p.Id
    WHERE (pr.profesional_id = :profesional_id OR pr.profesional_id IS NULL)
    AND pr.tipo_paciente = :tipo_paciente
    AND pr.activo = 1
    AND p.activo = 1
    ORDER BY p.nombre
");

$stmt->execute([
    ':profesional_id' => $profesional_id,
    ':tipo_paciente'  => $tipo_paciente
]);

$practicas = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =============================
   RESPUESTA
=============================*/
echo json_encode([
    'success' => true,
    'tipo_paciente' => $tipo_paciente, // 🔥 útil para debug/UI
    'data' => $practicas
]);