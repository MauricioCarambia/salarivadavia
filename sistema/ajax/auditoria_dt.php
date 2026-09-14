<?php
require_once __DIR__ . '/../inc/session.php';

if (empty($_SESSION['login'])) {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['error' => 'No autenticado']);
    exit;
}

if (empty($_SESSION['es_admin']) && !in_array('auditoria', $_SESSION['accesos'] ?? [])) {
    header('Content-Type: application/json');
    http_response_code(403);
    echo json_encode(['error' => 'No tenés permisos para acceder a esta sección']);
    exit;
}

require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/auditoria.php';

header('Content-Type: application/json');

/* ============================
   PARAMS DATATABLES
============================ */
$draw = (int)($_GET['draw'] ?? 1);
$start = (int)($_GET['start'] ?? 0);
$length = (int)($_GET['length'] ?? 25);
if ($length <= 0 || $length > 500) {
    $length = 25;
}
$buscar = trim($_GET['search']['value'] ?? '');

$columnas = ['creado_en', 'usuario_nombre', 'accion', 'detalle', 'motivo'];
$ordenCol = (int)($_GET['order'][0]['column'] ?? 0);
$ordenDir = strtolower($_GET['order'][0]['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
$ordenCampo = $columnas[$ordenCol] ?? 'creado_en';

/* ============================
   TOTAL SIN FILTRO
============================ */
$recordsTotal = (int)$pdo->query("SELECT COUNT(*) FROM auditoria")->fetchColumn();

/* ============================
   FILTRO DE BÚSQUEDA
============================ */
$where = '';
$params = [];

if ($buscar !== '') {
    $where = "
        WHERE usuario_nombre LIKE :b1
            OR accion LIKE :b2
            OR detalle LIKE :b3
            OR motivo LIKE :b4
            OR ip LIKE :b5
    ";
    $params[':b1'] = $params[':b2'] = $params[':b3'] = $params[':b4'] = $params[':b5'] = '%' . $buscar . '%';
}

$stmtFiltrado = $pdo->prepare("SELECT COUNT(*) FROM auditoria $where");
$stmtFiltrado->execute($params);
$recordsFiltered = (int)$stmtFiltrado->fetchColumn();

/* ============================
   PÁGINA DE RESULTADOS
============================ */
$sql = "
    SELECT id, usuario_nombre, accion, detalle, motivo, ip, creado_en
    FROM auditoria
    $where
    ORDER BY $ordenCampo $ordenDir
    LIMIT :start, :length
";
$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue(':start', $start, PDO::PARAM_INT);
$stmt->bindValue(':length', $length, PDO::PARAM_INT);
$stmt->execute();
$filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$data = [];

foreach ($filas as $r) {
    $data[] = [
        'fecha' => date('d/m/Y H:i:s', strtotime($r['creado_en'])),
        'usuario' => htmlspecialchars($r['usuario_nombre'] ?? '-'),
        'accion' => htmlspecialchars(auditoriaAccionLabel($r['accion'])),
        'detalle' => nl2br(htmlspecialchars($r['detalle'] ?? '')),
        'motivo' => nl2br(htmlspecialchars($r['motivo'] ?? '')),
    ];
}

echo json_encode([
    'draw' => $draw,
    'recordsTotal' => $recordsTotal,
    'recordsFiltered' => $recordsFiltered,
    'data' => $data,
]);
