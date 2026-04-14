<?php
require_once "../inc/db.php";
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
               CONCAT(p.apellido,' ',p.nombre) AS paciente,
               CONCAT(pr.apellido,' ',pr.nombre) AS profesional
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
    }
    unset($d);

    /* =========================
       🔀 REPARTO DINÁMICO
    ========================= */
    $stmt = $pdo->prepare("
        SELECT dr.nombre AS destino, cr.monto
        FROM cobros_reparto cr
        INNER JOIN destinos_reparto dr ON dr.id = cr.destino_id
        WHERE cr.cobro_id = ?
    ");
    $stmt->execute([$cobro_id]);

    $repartoTmp = [];

    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {

        $destino = strtolower(trim($r['destino']));

        if (!isset($repartoTmp[$destino])) {
            $repartoTmp[$destino] = 0;
        }

        $repartoTmp[$destino] += (float)$r['monto'];
    }

    // 🔥 Formato final compatible con frontend
    $reparto = [];

    foreach ($repartoTmp as $dest => $monto) {
        $reparto[] = [
            'destino' => $dest,
            'valor'   => (float)$monto
        ];
    }

    /* =========================
       RESPUESTA
    ========================= */
    echo json_encode([
        'success' => true,
        'data' => [
            'paciente'     => $c['paciente'],
            'profesional'  => $c['profesional'],
            'numero'       => $c['numero_completo'],
            'fecha'        => date('d/m/Y H:i', strtotime($c['fecha'])),
            'total'        => (float)$c['total'],
            'detalle'      => $detalle,
            'reparto'      => $reparto
        ]
    ]);

} catch (Exception $e) {

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);

}