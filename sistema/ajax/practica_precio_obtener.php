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

$practica_id = $_GET['practica_id'] ?? null;

$stmt = $pdo->prepare("
    SELECT p.nombre, pp.tipo_paciente, pp.precio, pp.activo
    FROM practicas_precios pp
    INNER JOIN practicas p ON p.id = pp.practica_id
    WHERE pp.practica_id = ?
");
$stmt->execute([$practica_id]);

$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

$response = [
    'success' => true,
    'practica' => $data[0]['nombre'] ?? '',
    'particular' => null,
    'socio' => null,
    'activo' => 1
];

foreach ($data as $row) {

    if ($row['tipo_paciente'] === 'particular') {
        $response['particular'] = $row['precio'];
    }

    if ($row['tipo_paciente'] === 'socio') {
        $response['socio'] = $row['precio'];
    }

    $response['activo'] = $row['activo'];
}

echo json_encode($response);