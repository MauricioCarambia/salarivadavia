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