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
require_once __DIR__ . '/../inc/auditoria.php';

header('Content-Type: application/json');

$response = ['success' => false];

/* ===============================
   VALIDAR ID Y MOTIVO
================================ */
$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$motivo = trim($_POST['motivo'] ?? '');

if ($id <= 0) {
    $response['message'] = 'ID inválido';
    echo json_encode($response);
    exit;
}

if ($motivo === '') {
    $response['message'] = 'Debe indicar el motivo de la eliminación';
    echo json_encode($response);
    exit;
}

/* ===============================
   DATOS PARA AUDITORÍA
================================ */
$stmt = $pdo->prepare("
    SELECT pa.monto, pa.fecha_correspondiente, p.apellido, p.nombre, p.nro_afiliado
    FROM pagos_afiliados pa
    INNER JOIN pacientes p ON p.Id = pa.paciente_id
    WHERE pa.Id = ?
");
$stmt->execute([$id]);
$pagoInfo = $stmt->fetch(PDO::FETCH_ASSOC);

/* ===============================
   ELIMINAR
================================ */
$stmt = $pdo->prepare("
    DELETE FROM pagos_afiliados
    WHERE Id = :id
");

if ($stmt->execute([':id' => $id])) {

    if ($stmt->rowCount() > 0) {
        $response['success'] = true;

        if ($pagoInfo) {
            $detalle = "Se eliminó la cuota de {$pagoInfo['fecha_correspondiente']} (\${$pagoInfo['monto']}) "
                . "del socio {$pagoInfo['apellido']} {$pagoInfo['nombre']} (N° {$pagoInfo['nro_afiliado']})";
        } else {
            $detalle = "Se eliminó el pago de afiliado N° {$id}";
        }

        registrarAuditoria($pdo, 'pago_afiliado_eliminado', $detalle, $motivo);

    } else {
        $response['message'] = 'No se encontró el registro';
    }

} else {
    $response['message'] = 'Error al eliminar';
}

echo json_encode($response);
