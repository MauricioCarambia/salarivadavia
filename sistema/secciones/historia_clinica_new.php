<?php
require_once __DIR__ . '/../inc/db.php';

$mensaje = '';
$tipoMsg = 'success';
$redirigir = null;

$pacienteId = (int) ($_GET['paciente_id'] ?? 0);
$turnoId = (int) ($_GET['turno'] ?? 0);
$profesionalId = $_SESSION['user_id'] ?? 0;

// Datos del paciente para mostrar en el encabezado
$paciente = null;
if ($pacienteId) {
    $stmtP = $pdo->prepare("SELECT nombre, apellido, documento, nacimiento, celular, nro_afiliado FROM pacientes WHERE Id = ? LIMIT 1");
    $stmtP->execute([$pacienteId]);
    $paciente = $stmtP->fetch(PDO::FETCH_ASSOC);
}

// =========================
// GUARDAR
// =========================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $pacienteId = (int) ($_POST['paciente_id'] ?? 0);
    $turnoId = (int) ($_POST['turno_id'] ?? 0);

    $motivo = trim($_POST['motivo'] ?? '');
    $diagnostico = trim($_POST['diagnostico'] ?? '');
    $medicamento = trim($_POST['medicamento'] ?? '');
    $sintomas = trim($_POST['sintomas'] ?? '');
    $estudios = trim($_POST['estudios'] ?? '');
    $examenes = trim($_POST['examenes'] ?? '');
    $texto = $_POST['texto'] ?? '';

    // eliminar espacios, saltos y etiquetas vacías
    $textoLimpio = trim(preg_replace('/\s+/', '', strip_tags($texto)));

    if ($textoLimpio === '') {
        $texto = null;
    }

    // Signos vitales estructurados
    $presion = trim($_POST['presion'] ?? '');
    $fc = trim($_POST['fc'] ?? '');
    $temp = trim($_POST['temp'] ?? '');
    $peso = trim($_POST['peso'] ?? '');
    $spo2 = trim($_POST['spo2'] ?? '');
    $fr = trim($_POST['fr'] ?? '');
    $altura = trim($_POST['altura'] ?? '');
    $imc = trim($_POST['imc'] ?? '');

    $vitales = "Peso: $peso Kg | Talla: $altura Cm | IMC: $imc | TA: $presion | FC: $fc | Temp: $temp °C | SpO2: $spo2 % | FR: $fr";

    if (!$pacienteId || !$profesionalId) {
        $mensaje = "Datos inválidos";
        $tipoMsg = "error";
    } elseif (empty($motivo)) {
        $mensaje = "El motivo es obligatorio";
        $tipoMsg = "warning";
    } else {

        $sql = "INSERT INTO historias_clinicas 
(paciente_id, profesional_id, fecha, motivo, sintomas, vitales, examenes, diagnostico, medicamento, texto)
VALUES 
(:paciente_id, :profesional_id, NOW(), :motivo, :sintomas, :vitales, :examenes, :diagnostico, :medicamento, :texto)";

        $stmt = $pdo->prepare($sql);

        $ok = $stmt->execute([
            ':paciente_id' => $pacienteId,
            ':profesional_id' => $profesionalId,
            ':motivo' => $motivo,
            ':sintomas' => $sintomas,
            ':vitales' => $vitales,
            ':examenes' => $examenes,
            ':diagnostico' => $diagnostico,
            ':medicamento' => $medicamento,
            ':texto' => $texto
        ]);

        if ($ok) {
            $mensaje = "Historia clínica guardada correctamente";
            $tipoMsg = "success";
            $redirigir = $pacienteId;

            // Marcar el turno como atendido automáticamente al cargar la HC
            if ($turnoId) {
                $pdo->prepare("UPDATE turnos SET atendido = 1 WHERE Id = :turno_id")
                    ->execute([':turno_id' => $turnoId]);
            }
        } else {
            $mensaje = "Error al guardar";
            $tipoMsg = "error";
        }
    }
}
?>
<div class="card card-info card-outline">

    <h1 class="card-title m-4 ">
        Nueva Historia Clinica
    </h1>

    <?php if ($paciente): ?>
    <?php
        $edad = '';
        if (!empty($paciente['nacimiento'])) {
            $nac = new DateTime($paciente['nacimiento']);
            $edad = $nac->diff(new DateTime())->y . ' años';
        }
    ?>
    <div class="paciente-info-bar mx-4 mb-3 p-3 rounded">
        <div class="d-flex flex-wrap align-items-center" style="gap:24px;">
            <div style="font-size:2rem;color:#17a2b8;"><i class="fas fa-user-circle"></i></div>
            <div>
                <div class="paciente-info-nombre" style="font-size:1.2rem;font-weight:700;color:#1a2a3a;">
                    <?= htmlspecialchars($paciente['apellido'] . ', ' . $paciente['nombre']) ?>
                </div>
                <?php if ($edad): ?>
                    <small class="text-muted"><?= $edad ?></small>
                <?php endif; ?>
            </div>
            <div class="d-flex flex-wrap" style="gap:16px;font-size:.92rem;">
                <?php if (!empty($paciente['documento'])): ?>
                <span><i class="fas fa-id-card text-secondary mr-1"></i><b>DNI:</b> <?= htmlspecialchars($paciente['documento']) ?></span>
                <?php endif; ?>
                <?php if (!empty($paciente['nacimiento'])): ?>
                <span><i class="fas fa-birthday-cake text-secondary mr-1"></i><b>Nac:</b> <?= date('d/m/Y', strtotime($paciente['nacimiento'])) ?></span>
                <?php endif; ?>
                <?php if (!empty($paciente['celular'])): ?>
                <span><i class="fas fa-phone text-secondary mr-1"></i><?= htmlspecialchars($paciente['celular']) ?></span>
                <?php endif; ?>
                <?php if (!empty($paciente['nro_afiliado'])): ?>
                <span><i class="fas fa-id-badge text-secondary mr-1"></i><b>N° socio:</b> <?= htmlspecialchars($paciente['nro_afiliado']) ?></span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <form method="POST" id="formHC">

        <!-- ================= DATOS PRINCIPALES ================= -->
        <div class="row m-1">

            <!-- ================= COLUMNA IZQUIERDA ================= -->
            <div class="col-md-8">
                <div class="card card-outline card-info">
                    <div class="card-body p-2">
                        <div class="form-group">
                            <label>Motivo de consulta *</label>
                            <input type="text" name="motivo" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label>Síntomas</label>
                            <input type="text" name="sintomas" class="form-control">
                        </div>

                        <div class="form-group">
                            <label>Estudios solicitados</label>
                            <input type="text" name="examenes" class="form-control">
                        </div>

                        <div class="form-group">
                            <label>Medicación</label>
                            <input type="text" name="medicamento" class="form-control">
                        </div>

                        <div class="form-group">
                            <label>Diagnóstico</label>
                            <input type="text" name="diagnostico" class="form-control">
                        </div>
                        <div class="form-group mt-3">
                            <label><b>Evolución Clínica</b></label>

                            <div id="editorHC" style="height: 350px; "></div>

                            <input type="hidden" name="texto" id="texto">
                        </div>
                    </div>
                </div>
            </div>

            <!-- ================= COLUMNA DERECHA ================= -->
            <div class="col-md-4">

                <div class="card card-outline card-info">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fa fa-heartbeat"></i> Signos Vitales
                        </h5>
                    </div>

                    <div class="card-body p-2">
                        <div class="form-group">
                            <label>Peso</label>
                            <input type="text" name="peso" class="form-control" placeholder="kg">
                        </div>

                        <div class="form-group">
                            <label>Talla</label>
                            <input type="text" name="altura" class="form-control" placeholder="cm"
                                onkeyup="calcularIMC()">
                        </div>

                        <div class="form-group">
                            <label>IMC</label>
                            <input type="text" id="imc" name="imc" class="form-control" readonly>
                        </div>
                        <div class="form-group">
                            <label>Tension arterial</label>
                            <input type="text" name="presion" class="form-control" placeholder="120/80">
                        </div>

                        <div class="form-group">
                            <label>Frecuencia Cardíaca</label>
                            <input type="text" name="fc" class="form-control" placeholder="Lat/min">
                        </div>

                        <div class="form-group">
                            <label>Temperatura</label>
                            <input type="text" name="temp" class="form-control" placeholder="°C">
                        </div>

                        <div class="form-group">
                            <label>SpO2</label>
                            <input type="text" name="spo2" class="form-control" placeholder="%">
                        </div>

                        <div class="form-group">
                            <label>Frecuencia Respiratoria</label>
                            <input type="text" name="fr" class="form-control" placeholder="Resp/min">
                        </div>



                    </div>

                </div>
                <input type="hidden" name="paciente_id" value="<?= $pacienteId ?>">
                <input type="hidden" name="turno_id" value="<?= $turnoId ?>">

                <div>
                    <button type="submit" class="btn btn-success">
                        <i class="fa fa-save"></i> Guardar
                    </button>

                    <a href="./?seccion=historia_clinica&id=<?= $pacienteId ?>" class="btn btn-secondary">
                        Volver
                    </a>
                </div>
            </div>

        </div>

    </form>
</div>


<?php if ($mensaje): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {

            Swal.fire({
                icon: '<?= $tipoMsg ?>',
                title: <?= json_encode($mensaje) ?>,
                showConfirmButton: <?= $redirigir ? 'false' : 'true' ?>,
                timer: <?= $redirigir ? '1500' : 'null' ?>
            }).then(() => {

                <?php if ($redirigir): ?>
                    window.location.href = './?seccion=historia_clinica&id=<?= $redirigir ?>';
                <?php endif; ?>

            });

        });
    </script>
<?php endif; ?>
<script>
    document.addEventListener("DOMContentLoaded", function() {

        var quill = new Quill('#editorHC', {
            theme: 'snow',
            placeholder: 'Escribir evolución clínica...',
            modules: {
                toolbar: [
                    [{
                        header: [1, 2, false]
                    }],
                    ['bold', 'italic', 'underline'],
                    [{
                        list: 'ordered'
                    }, {
                        list: 'bullet'
                    }],
                    ['link'],
                    ['clean']
                ]
            }
        });
        document.querySelector('[name="peso"]').addEventListener('keyup', calcularIMC);
        document.querySelector('[name="altura"]').addEventListener('keyup', calcularIMC);
        document.getElementById('formHC').addEventListener('submit', function() {
            calcularIMC(); // 🔥 FORZAR cálculo
            document.getElementById('texto').value = quill.getText().trim();
        });

    });

    function calcularIMC() {
        let peso = parseFloat(document.querySelector('[name="peso"]').value);
        let alturaCm = parseFloat(document.querySelector('[name="altura"]').value);

        if (peso > 0 && alturaCm > 0) {
            let altura = alturaCm / 100;
            let imc = (peso / (altura * altura)).toFixed(2);
            document.getElementById('imc').value = imc;
        } else {
            document.getElementById('imc').value = '';
        }
    }
</script>