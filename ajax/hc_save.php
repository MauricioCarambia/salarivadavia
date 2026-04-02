<?php

session_start();

require_once __DIR__ . '/../inc/db.php';
header('Content-Type: application/json');

// 🔥 PROFESIONAL SOLO DESDE SESSION
$profesionalId = $_SESSION['user_id'] ?? 0;



// Datos POST
$id = (int)($_POST['id'] ?? 0);
$pacienteId = (int)($_POST['paciente_id'] ?? 0);
$profesionalId = $_SESSION['user_id'] ?? ($_POST['profesional_id'] ?? 0);
$fecha = $_POST['fecha'] ?? date('Y-m-d');

$motivo = $_POST['motivo'] ?? '';
$sintomas = $_POST['sintomas'] ?? '';
$vitales = $_POST['vitales'] ?? '';
$examenes = $_POST['examenes'] ?? '';
$diagnostico = $_POST['diagnostico'] ?? '';
$medicamento = $_POST['medicamento'] ?? '';
$texto = $_POST['texto'] ?? '';

// Validación
if (!$pacienteId) {
    echo json_encode([
        'success' => false,
        'message' => 'Paciente inválido'
    ]);
    exit;
}

try {

    if ($id) {
        // UPDATE
        $stmt = $pdo->prepare("
            UPDATE historias_clinicas
            SET fecha = :fecha,
                motivo = :motivo,
                sintomas = :sintomas,
                vitales = :vitales,
                examenes = :examenes,
                diagnostico = :diagnostico,
                medicamento = :medicamento,
                texto = :texto
            WHERE Id = :id AND profesional_id = :profesional_id
        ");

        $stmt->execute([
            ':fecha' => $fecha,
            ':motivo' => $motivo,
            ':sintomas' => $sintomas,
            ':vitales' => $vitales,
            ':examenes' => $examenes,
            ':diagnostico' => $diagnostico,
            ':medicamento' => $medicamento,
            ':texto' => $texto,
            ':id' => $id,
            ':profesional_id' => $profesionalId
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Historia clínica actualizada'
        ]);

    } else {
        // INSERT
        $stmt = $pdo->prepare("
            INSERT INTO historias_clinicas
            (paciente_id, profesional_id, fecha, motivo, sintomas, vitales, examenes, diagnostico, medicamento, texto)
            VALUES
            (:paciente_id, :profesional_id, :fecha, :motivo, :sintomas, :vitales, :examenes, :diagnostico, :medicamento, :texto)
        ");

        $stmt->execute([
            ':paciente_id' => $pacienteId,
            ':profesional_id' => $profesionalId,
            ':fecha' => $fecha,
            ':motivo' => $motivo,
            ':sintomas' => $sintomas,
            ':vitales' => $vitales,
            ':examenes' => $examenes,
            ':diagnostico' => $diagnostico,
            ':medicamento' => $medicamento,
            ':texto' => $texto
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Historia clínica creada'
        ]);
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
