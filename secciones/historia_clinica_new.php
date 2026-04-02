<?php
require_once __DIR__ . '/../inc/db.php';

$mensaje = '';
$tipoMsg = 'success';
$redirigir = null;

$pacienteId = (int) ($_GET['paciente_id'] ?? 0);
$profesionalId = $_SESSION['user_id'] ?? 0;

// =========================
// GUARDAR
// =========================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $pacienteId = (int) ($_POST['paciente_id'] ?? 0);

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

    $vitales = "TA: $presion | FC: $fc | Temp: $temp °C | SpO2: $spo2 % | FR: $fr | Peso: $peso Kg | Altura: $altura Cm";

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
                            <input type="text" name="sintomas" class="form-control" placeholder="Ej: dolor, fiebre...">
                        </div>

                        <div class="form-group">
                            <label>Estudios solicitados</label>
                            <input type="text" name="examenes" class="form-control"
                                placeholder="Ej: laboratorio, RX...">
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
                            <label>Presión</label>
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

                        <div class="form-group">
                            <label>Peso</label>
                            <input type="text" name="peso" class="form-control" placeholder="kg">
                        </div>

                        <div class="form-group">
                            <label>Altura</label>
                            <input type="text" name="altura" class="form-control" placeholder="cm"
                                onkeyup="calcularIMC()">
                        </div>

                        <div class="form-group">
                            <label>IMC</label>
                            <input type="text" id="imc" class="form-control" readonly>
                        </div>

                    </div>

                </div>
                <input type="hidden" name="paciente_id" value="<?= $pacienteId ?>">

                <div >
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
        document.addEventListener('DOMContentLoaded', function () {

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
    document.addEventListener("DOMContentLoaded", function () {

        var quill = new Quill('#editorHC', {
            theme: 'snow',
            placeholder: 'Escribir evolución clínica...',
            modules: {
                toolbar: [
                    [{ header: [1, 2, false] }],
                    ['bold', 'italic', 'underline'],
                    [{ list: 'ordered' }, { list: 'bullet' }],
                    ['link'],
                    ['clean']
                ]
            }
        });

        document.getElementById('formHC').addEventListener('submit', function () {
            document.getElementById('texto').value = quill.getText().trim();
        });

    });
    function calcularIMC() {
        let peso = parseFloat(document.querySelector('[name="peso"]').value);
        let altura = parseFloat(document.querySelector('[name="altura"]').value) / 100;

        if (peso && altura) {
            let imc = (peso / (altura * altura)).toFixed(2);
            document.getElementById('imc').value = imc;
        }
    }
</script>