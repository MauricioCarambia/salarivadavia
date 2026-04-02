<?php
session_name("turnos"); // 🔥 ESTO FALTABA
session_start();

require_once '../inc/db.php';

header('Content-Type: application/json');

try {

    // =============================
    // VALIDAR MÉTODO
    // =============================
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }

    // =============================
    // VALIDAR DATOS
    // =============================
    if (!isset($_POST['id'], $_POST['rol'])) {
        throw new Exception('Datos incompletos');
    }

    $id  = (int) $_POST['id'];
    $rol = (int) $_POST['rol'];

    if ($id <= 0 || $rol <= 0) {
        throw new Exception('Datos inválidos');
    }

    // =============================
    // SESIÓN
    // =============================
    $user_id  = $_SESSION['user_id'] ?? 0;
    $es_admin = $_SESSION['es_admin'] ?? false;
    $accesos  = $_SESSION['accesos'] ?? [];

    // =============================
    // 🔥 PERMISOS
    // =============================
   function tieneAcceso($permiso) {
    if (!empty($_SESSION['es_admin'])) return true;
    return in_array($permiso, $_SESSION['accesos'] ?? []);
}

if (!tieneAcceso('gestionar_roles')) {
    throw new Exception('No tenés permisos para cambiar roles');
}
    // =============================
    // 🔥 NO CAMBIARSE A SÍ MISMO
    // =============================
    if ($id === $user_id) {
        throw new Exception('No podés modificar tu propio rol');
    }

    // =============================
    // VALIDAR EMPLEADO
    // =============================
    $stmt = $conexion->prepare("SELECT Id FROM empleados WHERE Id = ?");
    $stmt->execute([$id]);

    if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
        throw new Exception('Empleado no encontrado');
    }

    // =============================
    // VALIDAR ROL
    // =============================
    $stmt = $conexion->prepare("SELECT Id FROM roles WHERE Id = ?");
    $stmt->execute([$rol]);

    if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
        throw new Exception('Rol no válido');
    }

    // =============================
    // 🔥 EVITAR TOCAR ADMIN (SI NO SOS ADMIN)
    // =============================
    if (!$es_admin) {
        $stmt = $conexion->prepare("
            SELECT r.nombre 
            FROM empleados e
            LEFT JOIN roles r ON e.rol_id = r.Id
            WHERE e.Id = ?
        ");
        $stmt->execute([$id]);
        $emp = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($emp && strtolower($emp['nombre']) === 'administrador') {
            throw new Exception('No podés modificar un administrador');
        }
    }

    // =============================
    // UPDATE
    // =============================
    $stmt = $conexion->prepare("
        UPDATE empleados
        SET rol_id = :rol 
        WHERE Id = :id
    ");

   $stmt->execute([
    ':rol' => $rol,
    ':id'  => $id
]);

if ($stmt->rowCount() === 0) {
    throw new Exception('No se actualizó ningún registro');
}

    // =============================
    // RESPUESTA OK
    // =============================
    echo json_encode([
    'ok' => true,
    'mensaje' => 'Rol actualizado',
    'debug' => [
        'id' => $id,
        'rol' => $rol
    ]
]);
exit;

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        'ok' => false,
        'error' => 'Error en base de datos'
        // 'error' => $e->getMessage()
    ]);

} catch (Exception $e) {

    http_response_code(400);

    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ]);
}