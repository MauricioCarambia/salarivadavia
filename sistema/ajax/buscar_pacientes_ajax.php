<?php
require_once __DIR__ . '/../inc/session.php';

if (empty($_SESSION['login']) || $_SESSION['login'] !== 'si') {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}
require_once __DIR__ . '/../inc/db.php';

$busqueda = trim($_GET['q'] ?? '');
$data = [];

if ($busqueda !== '') {

    $palabras = preg_split('/\s+/', $busqueda);

    $where = [];
    $params = [];

    foreach ($palabras as $palabra) {
        $where[] = "(
            nombre LIKE ?
            OR apellido LIKE ?
            OR documento LIKE ?
        )";

        $term = "%{$palabra}%";

        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
    }

    $sql = "
        SELECT
            Id AS id,
            CONCAT(
                apellido,
                ' ',
                nombre,
                ' - DNI: ',
                COALESCE(documento,'')
            ) AS text
        FROM pacientes
        WHERE " . implode(' AND ', $where) . "
        ORDER BY apellido, nombre
        LIMIT 20
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

header('Content-Type: application/json');
echo json_encode($data);