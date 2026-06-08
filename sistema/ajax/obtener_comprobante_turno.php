<?php

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
        pr.nombre AS profesional_nombre
    FROM turnos t
    INNER JOIN pacientes p
        ON p.Id = t.paciente_id
    INNER JOIN profesionales pr
        ON pr.Id = t.profesional_id
    WHERE t.Id = :id
");

$stmt->execute([
    ':id' => $id
]);

echo json_encode(
    $stmt->fetch(PDO::FETCH_ASSOC)
);