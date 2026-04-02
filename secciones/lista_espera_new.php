<?php
require_once 'inc/db.php';

$mensaje = '';
$tipoMensaje = '';

$nombre = $apellido = $documento = $especialidad = '';
$disponibilidad = $horario = $celular = $edad = '';
$estudio = $profesional = $asignado = '';

/* =========================
   ESPECIALIDADES (DB)
========================= */
$stmt = $conexion->query("SELECT especialidad FROM especialidades ORDER BY especialidad ASC");
$especialidades = $stmt->fetchAll(PDO::FETCH_COLUMN);

/* =========================
   GUARDAR
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nombre = trim($_POST['nombre']);
    $apellido = trim($_POST['apellido']);
    $documento = trim($_POST['documento']);
    $especialidad = $_POST['especialidad'];
    $disponibilidad = $_POST['disponibilidad'];
    $horario = $_POST['horario'];
    $celular = $_POST['celular'];
    $edad = $_POST['edad'];
    $estudio = $_POST['estudio'];
    $profesional = $_POST['profesional'];
    $asignado = $_POST['asignado'];

    if ($nombre && $apellido && $documento && $especialidad) {

        $stmt = $conexion->prepare("
            INSERT INTO lista_espera
            (nombre, apellido, documento, especialidad, disponibilidad, horario, celular, edad, estudio, profesional, asignado)
            VALUES
            (:nombre, :apellido, :documento, :especialidad, :disponibilidad, :horario, :celular, :edad, :estudio, :profesional, :asignado)
        ");

        $stmt->execute([
            ':nombre' => $nombre,
            ':apellido' => $apellido,
            ':documento' => $documento,
            ':especialidad' => $especialidad,
            ':disponibilidad' => $disponibilidad,
            ':horario' => $horario,
            ':celular' => $celular,
            ':edad' => $edad,
            ':estudio' => $estudio,
            ':profesional' => $profesional,
            ':asignado' => $asignado
        ]);

        $tipoMensaje = 'success';
        $mensaje = 'Paciente agregado correctamente';

        // limpiar
        $nombre = $apellido = $documento = $especialidad = '';
        $disponibilidad = $horario = $celular = $edad = '';
        $estudio = $profesional = $asignado = '';

    } else {
        $tipoMensaje = 'error';
        $mensaje = 'Complete los campos obligatorios';
    }
}
?>
<div class="row mb-3">
    <div class="col-12">
        <div class="card card-info card-outline">
            <h1 class="ml-2">Alta en lista de espera</h1>


            <div class="card-body">

                <form method="POST" id="formPaciente">

                    <div class="row">

                        <div class="col-md-6">
                            <label>DNI</label>
                            <input type="number" name="documento" id="dni" class="form-control"
                                value="<?= $documento ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label>Nombre</label>
                            <input type="text" name="nombre" id="nombre" class="form-control" value="<?= $nombre ?>"
                                required>
                        </div>

                        <div class="col-md-6">
                            <label>Apellido</label>
                            <input type="text" name="apellido" id="apellido" class="form-control"
                                value="<?= $apellido ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label>Especialidad</label>
                            <select name="especialidad" class="form-control" required>
                                <option value="">Seleccionar</option>
                                <?php foreach ($especialidades as $esp): ?>
                                    <option value="<?= $esp ?>" <?= ($especialidad == $esp ? 'selected' : '') ?>>
                                        <?= $esp ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label>Disponibilidad</label>
                            <select name="disponibilidad" class="form-control">
                                <option value="">Seleccionar</option>
                                <option <?= $disponibilidad == 'Mañana' ? 'selected' : '' ?>>Mañana</option>
                                <option <?= $disponibilidad == 'Tarde' ? 'selected' : '' ?>>Tarde</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label>Preferencia horario</label>
                            <input type="text" name="horario" class="form-control" value="<?= $horario ?>">
                        </div>

                        <div class="col-md-4">
                            <label>Edad</label>
                            <input type="number" name="edad" class="form-control" value="<?= $edad ?>">
                        </div>

                        <div class="col-md-6">
                            <label>Celular</label>
                            <input type="text" name="celular" id="celular" class="form-control" value="<?= $celular ?>">
                        </div>

                        <div class="col-md-6">
                            <label>Profesional</label>
                            <input type="text" name="profesional" class="form-control" value="<?= $profesional ?>">
                        </div>

                       
                        <div class="col-md-6 mt-2">
                            <label>Estado</label>
                            <select name="asignado" class="form-control">
                                <option value="">Seleccionar</option>
                                <option <?= $asignado == 'Confirmo' ? 'selected' : '' ?>>Confirmo</option>
                                <option <?= $asignado == 'No Confirmo' ? 'selected' : '' ?>>No Confirmo</option>
                                <option <?= $asignado == 'Pendiente Confirmacion' ? 'selected' : '' ?>>Pendiente
                                    Confirmacion
                                </option>

                        </div>

                    </div>

                    <button class="btn btn-success mt-3 float-right">
                        <i class="fa fa-save"></i> Guardar
                    </button>

                </form>

            </div>
        </div>
    </div>

</div>
<script>
    $('#dni').on('blur', function () {

        let dni = $(this).val();

        if (dni.length < 6) return;

        $.post('ajax/lista_buscar_paciente.php', { dni: dni }, function (resp) {

            if (resp.encontrado) {
                $('#nombre').val(resp.nombre);
                $('#apellido').val(resp.apellido);
                $('#celular').val(resp.celular);

                Swal.fire({
                    icon: 'info',
                    title: 'Paciente encontrado',
                    timer: 1200,
                    showConfirmButton: false
                });
            }

        }, 'json');

    });
</script>
<?php if ($mensaje): ?>
    <script>
        Swal.fire({
            icon: '<?= $tipoMensaje ?>',
            title: '<?= $mensaje ?>',
            timer: 1500,
            showConfirmButton: false
        });
    </script>
<?php endif; ?>