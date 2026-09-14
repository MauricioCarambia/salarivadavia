<?php
require_once __DIR__ . '/../inc/session.php';

if (empty($_SESSION['login'])) {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['error' => 'No autenticado']);
    exit;
}

if (empty($_SESSION['es_admin']) && !in_array('estadisticas', $_SESSION['accesos'] ?? [])) {
    header('Content-Type: application/json');
    http_response_code(403);
    echo json_encode(['error' => 'No tenés permisos para acceder a esta sección']);
    exit;
}

require_once __DIR__ . '/../inc/db.php';

header('Content-Type: application/json');

/* ============================
   RANGO FIJO: 30 días antes / 30 días después de hoy
============================ */
$inicio = date('Y-m-d', strtotime('-30 days'));
$fin = date('Y-m-d', strtotime('+30 days'));

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

$columnas = ['paciente', 'celular', 'profesional', 'fecha_turno_raw', 'empleado', 'sobreturno', 'fecha_dado_raw'];
$ordenCol = (int)($_GET['order'][0]['column'] ?? 3);
$ordenDir = strtolower($_GET['order'][0]['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
$ordenCampo = $columnas[$ordenCol] ?? 'fecha_turno_raw';

$baseSql = "
    FROM turnos t
    LEFT JOIN pacientes p ON p.Id = t.paciente_id
    LEFT JOIN profesionales pr ON pr.Id = t.profesional_id
    LEFT JOIN empleados e ON e.id = t.usuario_iD
";

$selectSql = "
    SELECT
        t.Id,
        TRIM(CONCAT(COALESCE(p.apellido, ''), ' ', COALESCE(p.nombre, ''))) AS paciente,
        p.celular AS celular,
        TRIM(CONCAT(COALESCE(pr.apellido, ''), ' ', COALESCE(pr.nombre, ''))) AS profesional,
        t.fecha AS fecha_turno_raw,
        COALESCE(e.nombre, e.usuario, 'Sistema') AS empleado,
        COALESCE(t.sobreturno, 0) AS sobreturno,
        t.fecha_actual AS fecha_dado_raw
    $baseSql
    WHERE DATE(t.fecha) BETWEEN :inicio AND :fin
";

$params = [':inicio' => $inicio, ':fin' => $fin];

if ($buscar !== '') {
    $selectSql .= "
        HAVING paciente LIKE :buscar1
            OR profesional LIKE :buscar2
            OR empleado LIKE :buscar3
            OR celular LIKE :buscar4
    ";
    $params[':buscar1'] = $params[':buscar2'] = $params[':buscar3'] = $params[':buscar4'] = '%' . $buscar . '%';
}

/* ============================
   TOTAL SIN FILTRO
============================ */
$stmtTotal = $pdo->prepare("SELECT COUNT(*) $baseSql WHERE DATE(t.fecha) BETWEEN :inicio AND :fin");
$stmtTotal->execute([':inicio' => $inicio, ':fin' => $fin]);
$recordsTotal = (int)$stmtTotal->fetchColumn();

/* ============================
   TOTAL FILTRADO
============================ */
$sqlFiltrado = "SELECT COUNT(*) FROM ($selectSql) sub";
$stmtFiltrado = $pdo->prepare($sqlFiltrado);
$stmtFiltrado->execute($params);
$recordsFiltered = (int)$stmtFiltrado->fetchColumn();

/* ============================
   PÁGINA DE RESULTADOS
============================ */
$sqlPagina = "$selectSql ORDER BY $ordenCampo $ordenDir LIMIT :start, :length";
$stmt = $pdo->prepare($sqlPagina);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue(':start', $start, PDO::PARAM_INT);
$stmt->bindValue(':length', $length, PDO::PARAM_INT);
$stmt->execute();
$filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$data = [];

foreach ($filas as $r) {
    $esSobreturno = (int)$r['sobreturno'] === 1;

    $data[] = [
        'paciente' => htmlspecialchars($r['paciente'] ?: '(sin paciente)'),
        'celular' => htmlspecialchars($r['celular'] ?: '-'),
        'profesional' => htmlspecialchars($r['profesional'] ?: '(sin profesional)'),
        'fecha_turno' => $r['fecha_turno_raw'] ? date('d/m/Y H:i', strtotime($r['fecha_turno_raw'])) : '-',
        'empleado' => htmlspecialchars($r['empleado']),
        'sobreturno' => $esSobreturno ? 1 : 0,
        'sobreturno_label' => $esSobreturno
            ? '<span class="badge badge-warning"><i class="fa fa-exclamation-circle"></i> Sí</span>'
            : '<span class="badge badge-light">No</span>',
        'fecha_dado' => $r['fecha_dado_raw'] ? date('d/m/Y H:i', strtotime($r['fecha_dado_raw'])) : '-',
    ];
}

echo json_encode([
    'draw' => $draw,
    'recordsTotal' => $recordsTotal,
    'recordsFiltered' => $recordsFiltered,
    'data' => $data,
]);
