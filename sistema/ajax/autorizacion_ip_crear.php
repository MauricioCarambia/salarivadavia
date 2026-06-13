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
        throw new Exception('No tenés permisos para otorgar autorizaciones');
    }

    $tipoUsuario = $_POST['tipo_usuario'] ?? '';
    $usuarioId   = (int) ($_POST['usuario_id'] ?? 0);
    $horas       = (int) ($_POST['horas'] ?? 0);

    if (!in_array($tipoUsuario, ['empleado', 'profesional'], true)) {
        throw new Exception('Tipo de usuario inválido');
    }

    if ($usuarioId <= 0) {
        throw new Exception('Debe seleccionar un usuario');
    }

    if ($horas <= 0 || $horas > 24 * 30) {
        throw new Exception('Duración inválida');
    }

    if ($tipoUsuario === 'empleado') {
        $stmt = $pdo->prepare("SELECT nombre FROM empleados WHERE Id = ?");
        $stmt->execute([$usuarioId]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $stmt = $pdo->prepare("SELECT CONCAT(nombre, ' ', apellido) AS nombre FROM profesionales WHERE Id = ?");
        $stmt->execute([$usuarioId]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$usuario) {
        throw new Exception('Usuario no encontrado');
    }

    $stmt = $pdo->prepare("
        INSERT INTO autorizaciones_ip (tipo_usuario, usuario_id, autorizado_por, expira_en)
        VALUES (:tipo_usuario, :usuario_id, :autorizado_por, DATE_ADD(NOW(), INTERVAL :horas HOUR))
    ");
    $stmt->execute([
        ':tipo_usuario'   => $tipoUsuario,
        ':usuario_id'     => $usuarioId,
        ':autorizado_por' => $_SESSION['user_id'] ?? null,
        ':horas'          => $horas,
    ]);

    registrarAuditoria(
        $pdo,
        'autorizacion_ip_otorgada',
        "Se autorizó el acceso remoto de {$tipoUsuario} {$usuario['nombre']} por {$horas} hs"
    );

    echo json_encode(['ok' => true, 'mensaje' => 'Autorización otorgada correctamente']);

} catch (PDOException $e) {

    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Error en base de datos']);

} catch (Exception $e) {

    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
