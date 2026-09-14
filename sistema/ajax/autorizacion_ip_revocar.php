<?php
require_once __DIR__ . '/../inc/session.php';

if (empty($_SESSION['login']) || $_SESSION['login'] !== 'si') {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'No autenticado']);
    exit;
}

require_once __DIR__ . '/../inc/csrf.php';
requerirCsrf();

require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/permisos.php';
require_once __DIR__ . '/../inc/auditoria.php';

header('Content-Type: application/json');

try {

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }

    if (!tieneAcceso('autorizaciones_ip')) {
        throw new Exception('No tenés permisos para revocar autorizaciones');
    }

    $id = (int) ($_POST['id'] ?? 0);

    if ($id <= 0) {
        throw new Exception('Datos inválidos');
    }

    $stmt = $pdo->prepare("
        SELECT a.tipo_usuario, a.usuario_id,
            CASE a.tipo_usuario
                WHEN 'empleado' THEN e.nombre
                WHEN 'profesional' THEN CONCAT(p.nombre, ' ', p.apellido)
            END AS usuario_nombre
        FROM autorizaciones_ip a
        LEFT JOIN empleados e ON a.tipo_usuario = 'empleado' AND e.Id = a.usuario_id
        LEFT JOIN profesionales p ON a.tipo_usuario = 'profesional' AND p.Id = a.usuario_id
        WHERE a.id = ?
    ");
    $stmt->execute([$id]);
    $autorizacion = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$autorizacion) {
        throw new Exception('Autorización no encontrada');
    }

    $stmt = $pdo->prepare("DELETE FROM autorizaciones_ip WHERE id = ?");
    $stmt->execute([$id]);

    registrarAuditoria(
        $pdo,
        'autorizacion_ip_revocada',
        "Se revocó la autorización de acceso remoto de {$autorizacion['tipo_usuario']} {$autorizacion['usuario_nombre']}"
    );

    echo json_encode(['ok' => true, 'mensaje' => 'Autorización revocada']);

} catch (PDOException $e) {

    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Error en base de datos']);

} catch (Exception $e) {

    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
