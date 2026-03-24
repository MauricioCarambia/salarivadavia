<?php
require_once __DIR__ . "/../inc/db.php";

if (session_status() === PHP_SESSION_NONE) session_start();

date_default_timezone_set('America/Argentina/Buenos_Aires');
header('Content-Type: application/json');

// ============================== INPUT ==============================
$profesional = filter_input(INPUT_GET, 'p', FILTER_VALIDATE_INT);
$paciente    = filter_input(INPUT_GET, 'paciente', FILTER_VALIDATE_INT);
$fechaInput  = $_GET['fecha'] ?? null;

$id_usuario = $_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? 0;

// ============================== VALIDACIONES ==============================
if (!$profesional || !$paciente || !$fechaInput) {
    echo json_encode(['success' => false, 'error' => 'Parámetros incompletos.']);
    exit;
}

// validar fecha correctamente
$timestamp = strtotime($fechaInput);
if (!$timestamp) {
    echo json_encode(['success' => false, 'error' => 'Fecha inválida']);
    exit;
}

$fecha = date('Y-m-d H:i:s', $timestamp);

// ============================== PROCESO ==============================
try {

    $conexion->beginTransaction();

    // 🔒 BLOQUEO PARA EVITAR DOBLE TURNO
    $stmt = $conexion->prepare("
        SELECT id 
        FROM turnos 
        WHERE profesional_id = :p 
          AND fecha = :f 
        LIMIT 1 
        FOR UPDATE
    ");

    $stmt->execute([
        ':p' => $profesional,
        ':f' => $fecha
    ]);

    $sobreturno = $stmt->fetchColumn() ? 1 : 0;

    // ============================== INSERT ==============================
    $stmt = $conexion->prepare("
        INSERT INTO turnos 
        (profesional_id, fecha, paciente_id, sobreturno, pago, asistio, usuario_id, fecha_actual, atendido)
        VALUES
        (:p, :f, :pa, :s, 0, 0, :u, NOW(), 0)
    ");

    $stmt->execute([
        ':p'  => $profesional,
        ':f'  => $fecha,
        ':pa' => $paciente,
        ':s'  => $sobreturno,
        ':u'  => $id_usuario
    ]);

    $last_id = $conexion->lastInsertId();

    // ============================== PROFESIONAL ==============================
    $stmt = $conexion->prepare("
        SELECT apellido, nombre 
        FROM profesionales 
        WHERE id = :id 
        LIMIT 1
    ");
    $stmt->execute([':id' => $profesional]);

    $p = $stmt->fetch(PDO::FETCH_ASSOC);
    $nombreProfesional = $p ? $p['apellido'] . ' ' . $p['nombre'] : '';

    $conexion->commit();

    // ============================== RESPONSE ==============================
    echo json_encode([
        'success'       => true,
        'id_turno'      => $last_id,
        'sobreturno'    => $sobreturno,
        'mensaje'       => $sobreturno
            ? 'Sobreturno agregado satisfactoriamente.'
            : 'Turno agregado satisfactoriamente.',
        'fecha'         => $fecha,
        'hora'          => date('H:i', $timestamp),
        'profesional'   => $nombreProfesional
    ]);

} catch (Throwable $e) {

    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }

    echo json_encode([
        'success' => false,
        'error'   => 'Error al guardar el turno'
        // en producción NO mostrar $e->getMessage()
    ]);
}