<?php
require_once __DIR__ . '/../inc/session.php';

if (empty($_SESSION['login']) || $_SESSION['login'] !== 'si') {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}
require_once __DIR__ . '/../inc/db.php';
header('Content-Type: application/json');

try {

    $cobro_id = (int)($_GET['cobro_id'] ?? 0);

    if ($cobro_id <= 0) {
        throw new Exception('ID inválido');
    }

    /* =========================
       📄 CABECERA
    ========================= */
    $stmt = $pdo->prepare("
        SELECT c.*, 
               CONCAT(COALESCE(p.apellido,''),' ',COALESCE(p.nombre,'')) AS paciente,
               CONCAT(COALESCE(pr.apellido,''),' ',COALESCE(pr.nombre,'')) AS profesional
        FROM cobros c
        LEFT JOIN pacientes p ON p.Id = c.paciente_id
        LEFT JOIN profesionales pr ON pr.Id = c.profesional_id
        WHERE c.id = ?
        LIMIT 1
    ");
    $stmt->execute([$cobro_id]);
    $c = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$c) {
        throw new Exception('Cobro no encontrado');
    }

    /* =========================
       📦 DETALLE
    ========================= */
    $stmt = $pdo->prepare("
        SELECT nombre, precio
        FROM cobros_detalle
        WHERE cobro_id = ?
    ");
    $stmt->execute([$cobro_id]);
    $detalle = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($detalle as &$d) {
        $d['precio'] = (float)$d['precio'];
        $d['nombre'] = trim($d['nombre']);
    }
    unset($d);

    /* =========================
       🔀 REPARTO
    ========================= */
    $stmt = $pdo->prepare("
        SELECT dr.nombre AS destino, cr.monto
        FROM cobros_reparto cr
        INNER JOIN destinos_reparto dr ON dr.id = cr.destino_id
        WHERE cr.cobro_id = ?
    ");
    $stmt->execute([$cobro_id]);

    $repartoMap = [];

    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {

        $destino = trim($r['destino']);

        if ($destino === '') continue;

        if (!isset($repartoMap[$destino])) {
            $repartoMap[$destino] = 0;
        }

        $repartoMap[$destino] += (float)$r['monto'];
    }

    $reparto = [];

    foreach ($repartoMap as $destino => $monto) {
        $reparto[] = [
            'destino' => $destino,
            'valor'   => (float)$monto
        ];
    }

    /* =========================
       🧾 FORMATEOS
    ========================= */
    $fecha = !empty($c['fecha'])
        ? date('d/m/Y H:i', strtotime($c['fecha']))
        : null;

    $paciente = trim($c['paciente']) ?: null;
    $profesional = trim($c['profesional']) ?: null;

    /* =========================
       RESPUESTA
    ========================= */
    echo json_encode([
        'success' => true,
        'data' => [
            'paciente'        => $paciente,
            'profesional'     => $profesional,
            'numero_completo' => $c['numero_completo'],
            'fecha'           => $fecha,
            'total'           => (float)$c['total'],
            'detalle'         => $detalle,
            'reparto'         => $reparto,
            'tipo'            => $c['tipo'] ?? 'ingreso'
        ]
    ]);

} catch (Exception $e) {

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}