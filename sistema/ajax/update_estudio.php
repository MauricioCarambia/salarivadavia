<?php
require_once __DIR__ . '/../inc/db.php';

$id = $_POST['id'];
$estudio = trim($_POST['estudio']);
$valor = str_replace(',', '.', $_POST['valor']);

$update = $pdo->prepare("
    UPDATE estudio_lab
    SET estudio = :estudio,
        valor = :valor
    WHERE id = :id
");

$update->execute([
    ':estudio' => $estudio,
    ':valor' => $valor,
    ':id' => $id
]);

echo json_encode(['status' => 'ok']);