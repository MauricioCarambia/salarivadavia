<?php
session_start();
require_once "inc/db.php"; // $pdo
$mensaje = '';
$disabled = false;

$id = $_GET['id'] ?? null;
$rand = mt_rand();

// Inicializar variables de formulario
$motivo = $sintomas = $vitales = $examenes = $diagnostico = $medicamento = $texto = '';

// Guardar HC si se envía el formulario
if (isset($_POST['guardar'])) {
    $pacienteID = $_POST['paciente_id'] ?? '';
    $motivo = $_POST['motivo'] ?? '';
    $sintomas = $_POST['sintomas'] ?? '';
    $vitales = $_POST['vitales'] ?? '';
    $examenes = $_POST['examenes'] ?? '';
    $diagnostico = $_POST['diagnostico'] ?? '';
    $medicamento = $_POST['medicamento'] ?? '';
    $texto = $_POST['texto'] ?? '';

    $profesionalId = $_SESSION['user_id'] ?? null;

    if ($pacienteID && $profesionalId) {
        $sqlInsert = "INSERT INTO historias_clinicas 
            (paciente_id, profesional_id, fecha, motivo, sintomas, vitales, examenes, diagnostico, medicamento, texto)
            VALUES (:paciente_id, :profesional_id, NOW(), :motivo, :sintomas, :vitales, :examenes, :diagnostico, :medicamento, :texto)";
        
        $stmt = $pdo->prepare($sqlInsert);
        $exito = $stmt->execute([
            ':paciente_id' => $pacienteID,
            ':profesional_id' => $profesionalId,
            ':motivo' => $motivo,
            ':sintomas' => $sintomas,
            ':vitales' => $vitales,
            ':examenes' => $examenes,
            ':diagnostico' => $diagnostico,
            ':medicamento' => $medicamento,
            ':texto' => $texto
        ]);

        if ($exito) {
            $mensaje = '<div class="alert alert-success">Historia clínica guardada con éxito.</div>';
            $disabled = true;
        } else {
            $mensaje = '<div class="alert alert-danger">Error al guardar la historia clínica.</div>';
        }
    }
}

// Obtener datos del paciente
$paciente = $pacienteId = $celular = $documento = $fecha = $historia_clinica = '';

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM pacientes WHERE Id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $rArray = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($rArray) {
        $paciente = $rArray['apellido'] . ' ' . $rArray['nombre'];
        $pacienteId = $rArray['Id'];
        $celular = $rArray['celular'];
        $documento = $rArray['documento'];
        $fecha = $rArray['nacimiento'];
        $historia_clinica = $rArray['historia_clinica'];
    }
}
?>
<div id="wrapper">
    <div class="normalheader transition animated fadeIn small-header">
        <div class="hpanel">
            <div class="panel-body">
                <h2>Alta de Historia Clínica</h2>
            </div>
        </div>
    </div>
    <?php echo $mensaje; ?>
    <div class="content animate-panel">
        <div class="row">
            <div class="col-lg-12">
                <div class="hpanel">
                    <div class="panel-heading hbuilt">Detalle del paciente:</div>
                    <div class="panel-body">
                        <label>Paciente:</label> <?= htmlspecialchars($paciente) ?><br>
                        <label>Documento:</label> <?= htmlspecialchars($documento) ?><br>
                        <label>Fecha Nac.:</label> <?= htmlspecialchars($fecha) ?><br>
                        <label>Celular:</label> <?= htmlspecialchars($celular) ?><br>
                        <label>Nro HC:</label> <?= htmlspecialchars($historia_clinica) ?><br>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-12">
                <div class="hpanel">
                    <div class="panel-body">
                        <form class="form-horizontal" method="POST" 
                            action="./?seccion=historia_clinica_new&v=<?= urlencode($_GET['v'] ?? '') ?>&nc=<?= $rand ?>">
                            
                            <?php 
                            $campos = [
                                'motivo' => 'Motivo de consulta',
                                'sintomas' => 'Síntomas',
                                'vitales' => 'Signos Vitales',
                                'examenes' => 'Exámenes Solicitados',
                                'diagnostico' => 'Diagnóstico',
                                'medicamento' => 'Medicamentos Prescriptos',
                                'texto' => 'Observaciones'
                            ];
                            foreach ($campos as $name => $label): ?>
                                <div class="form-group">
                                    <label class="col-sm-2 control-label"><?= $label ?></label>
                                    <div class="col-sm-10">
                                        <input type="text" name="<?= $name ?>" class="form-control" 
                                            value="<?= htmlspecialchars($$name) ?>" 
                                            <?= $disabled ? 'disabled' : '' ?>
                                            <?= $name === 'motivo' ? 'required' : '' ?>>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <div class="form-group">
                                <div class="col-sm-12 text-right">
                                    <input type="hidden" name="paciente_id" value="<?= $pacienteId ?>">
                                    <?php if (!empty($_GET['v'])): ?>
                                        <a href="./<?= $_SESSION["volver"] ?? '' ?>&nc=<?= $rand ?>" class="btn btn-info">Volver al turno</a>
                                    <?php else: ?>
                                        <a href="./?seccion=turnos_profesional&nc=<?= $rand ?>" class="btn btn-danger">Volver</a>
                                    <?php endif; ?>
                                    <input type="submit" class="btn btn-info" name="guardar" value="Guardar">
                                </div>
                            </div>
                            <p>Una vez guardada debe volver para cargar o modificar otra HC</p>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>