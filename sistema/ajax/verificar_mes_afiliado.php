<?php
require_once __DIR__ . '/../inc/session.php';
require_once __DIR__ . '/../inc/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['login']) || $_SESSION['login'] !== 'si') {
    echo json_encode(['ok' => false, 'msg' => 'Sesión expirada']);
    exit;
}

$paciente_id = (int)($_GET['paciente_id'] ?? 0);
$fecha       = $_GET['fecha'] ?? ''; // YYYY-MM

if (!$paciente_id || !preg_match('/^\d{4}-\d{2}$/', $fecha)) {
    echo json_encode(['ok' => false, 'msg' => 'Parámetros inválidos']);
    exit;
}

$fecha_completa = $fecha . '-01';

/*
 * Buscar si algún paciente del mismo grupo de socio ya tiene
 * registrado ese mes en pagos_afiliados.
 * Devuelve los que ya lo tienen pagado para informar al usuario.
 */
$stmt = $pdo->prepare("
    SELECT 
        pa.paciente_id,
        p.apellido,
        p.nombre,
        p.nro_afiliado,
        pa.monto,
        pa.fecha_pago
    FROM pagos_afiliados pa
    INNER JOIN pacientes p ON p.Id = pa.paciente_id
    WHERE pa.fecha_correspondiente = :fecha
      AND p.Id IN (
          SELECT Id FROM pacientes
          WHERE SUBSTRING_INDEX(SUBSTRING_INDEX(nro_afiliado, '/', 1), ' ', 1) = (
              SELECT SUBSTRING_INDEX(SUBSTRING_INDEX(nro_afiliado, '/', 1), ' ', 1)
              FROM pacientes WHERE Id = :paciente_id
          )
      )
    ORDER BY p.apellido, p.nombre
");

$stmt->execute([
    ':fecha'       => $fecha_completa,
    ':paciente_id' => $paciente_id,
]);

$pagados = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'ok'     => true,
    'pagado' => count($pagados) > 0,
    'lista'  => $pagados,
]);