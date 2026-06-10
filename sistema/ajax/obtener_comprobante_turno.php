<?php
require_once __DIR__ . '/../inc/session.php';

if (empty($_SESSION['login']) || $_SESSION['login'] !== 'si') {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

require_once __DIR__ . '/../inc/db.php';

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT
        t.Id,
        t.fecha,
        t.sobreturno,
        p.apellido AS paciente_apellido,
        p.nombre AS paciente_nombre,
        p.documento,
        pr.apellido AS profesional_apellido,
        pr.nombre AS profesional_nombre,
        e.especialidad AS profesional_especialidad
    FROM turnos t
    INNER JOIN pacientes p
        ON p.Id = t.paciente_id
    INNER JOIN profesionales pr
        ON pr.Id = t.profesional_id
    INNER JOIN especialidades e
        ON e.Id = pr.especialidad_id
    WHERE t.Id = :id
");

$stmt->execute([
    ':id' => $id
]);

echo json_encode(
    $stmt->fetch(PDO::FETCH_ASSOC)
);