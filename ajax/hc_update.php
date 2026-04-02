<?php
require_once __DIR__ . '/../inc/db.php';

header('Content-Type: application/json');

$hcId = (int) ($_POST['id'] ?? 0);

$sql = "UPDATE historias_clinicas SET 
    motivo = :motivo,
    sintomas = :sintomas,
    vitales = :vitales,
    examenes = :examenes,
    diagnostico = :diagnostico,
    medicamento = :medicamento,
    texto = :texto
WHERE Id = :id";

$stmt = $pdo->prepare($sql);

$ok = $stmt->execute([
    ':motivo' => $_POST['motivo'] ?? '',
    ':sintomas' => $_POST['sintomas'] ?? '',
    ':vitales' => $_POST['vitales'] ?? '',
    ':examenes' => $_POST['examenes'] ?? '',
    ':diagnostico' => $_POST['diagnostico'] ?? '',
    ':medicamento' => $_POST['medicamento'] ?? '',
    ':texto' => $_POST['texto'] ?? '',
    ':id' => $hcId
]);

echo json_encode(['success' => $ok]);