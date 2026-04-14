<?php
require_once "../inc/db.php";

date_default_timezone_set('America/Argentina/Buenos_Aires');

header('Content-Type: application/json');

// Recibimos los datos
$id         = $_POST['id'] ?? 0;
$fecha      = $_POST['fecha'] ?? null;
// Usamos null coalescing y validamos si existe en el POST
$sobreturno = isset($_POST['sobreturno']) ? (int)$_POST['sobreturno'] : null;

if (!$id || !$fecha) {
    echo json_encode([
        'ok' => false,
        'error' => 'Datos inválidos'
    ]);
    exit;
}

try {
    // Preparamos la consulta dinámica
    // Si $sobreturno no es null, lo incluimos en el UPDATE
    if ($sobreturno !== null) {
        $sql = "UPDATE turnos SET fecha = :fecha, sobreturno = :sobreturno WHERE Id = :id";
        $params = [
            ':fecha'      => $fecha,
            ':id'         => $id,
            ':sobreturno' => $sobreturno
        ];
    } else {
        // Comportamiento original si no se envía el dato de sobreturno
        $sql = "UPDATE turnos SET fecha = :fecha WHERE Id = :id";
        $params = [
            ':fecha' => $fecha,
            ':id'    => $id
        ];
    }

    $stmt = $conexion->prepare($sql);
    $resultado = $stmt->execute($params);

    if ($resultado) {
        echo json_encode(['ok' => true]);
    } else {
        echo json_encode(['ok' => false, 'error' => 'No se pudo actualizar el registro']);
    }

} catch (PDOException $e) {
    echo json_encode([
        'ok' => false, 
        'error' => 'Error de base de datos: ' . $e->getMessage()
    ]);
}