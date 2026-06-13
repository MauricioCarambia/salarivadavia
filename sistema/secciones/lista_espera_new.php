<?php
require_once __DIR__ . '/../inc/db.php';

if (empty($_SESSION['login'])) {
    header("Location: login.php");
    exit;
}

if (empty($_SESSION['es_admin']) && !in_array('lista_espera', $_SESSION['accesos'] ?? [])) {
    die('<div class="alert alert-danger">No tenés permisos para acceder a esta sección</div>');
}

$mensaje = '';
$tipoMensaje = '';

$nombre = $apellido = $documento = '';
$derivacion = $horario = $celular = $edad = '';
$nota = $profesional = $asignado = '';

$especialidad = $_GET['especialidad'] ?? '';

/* =========================
   ESPECIALIDADES (DB)
========================= */
$stmt = $pdo->query("SELECT especialidad FROM especialidades ORDER BY especialidad ASC");
$especialidades = $stmt->fetchAll(PDO::FETCH_COLUMN);

/* =========================
   GUARDAR
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nombre = trim($_POST['nombre']);
    $apellido = trim($_POST['apellido']);
    $documento = trim($_POST['documento']);
    $especialidad = $_POST['especialidad'];
    $derivacion = $_POST['derivacion'];
    $horario = $_POST['horario'];
    $celular = $_POST['celular'];
    $edad = $_POST['edad'];
    $profesional = $_POST['profesional'];
    $asignado = $_POST['asignado'];
    $nota = $_POST['nota'];

    if ($nombre && $apellido && $documento && $especialidad) {

        $stmt = $pdo->prepare("
    INSERT INTO lista_espera
    (nombre, apellido, documento, especialidad, derivacion, horario, celular, edad, profesional, asignado, nota, created_date)
    VALUES
    (:nombre, :apellido, :documento, :especialidad, :derivacion, :horario, :celular, :edad, :profesional, :asignado, :nota, NOW())
");

        $stmt->execute([
            ':nombre'      => $nombre,
            ':apellido'    => $apellido,
            ':documento'   => $documento,
            ':especialidad' => $especialidad,
            ':derivacion'  => $derivacion,
            ':horario'     => $horario,
            ':celular'     => $celular,
            ':edad'        => $edad,
            ':profesional' => $profesional,
            ':asignado'    => $asignado,
            ':nota'        => $nota
            // Ya no pasamos :created_date porque NOW() lo maneja la DB
        ]);
        $tipoMensaje = 'success';
        $mensaje = 'Paciente agregado correctamente';

        // limpiar
        $nombre = $apellido = $documento = $especialidad = '';
        $derivacion = $horario = $celular = $edad = '';
        $profesional = $asignado = '';
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

                        <!-- DNI -->
                        <div class="col-md-4">
                            <label>DNI</label>
                            <input type="number" name="documento" id="dni" class="form-control"
                                value="<?= $documento ?>" required>
                        </div>

                        <!-- Nombre -->
                        <div class="col-md-4">
                            <label>Nombre<span class="required-asterisk">*</span></label>
                            <input type="text" name="nombre" id="nombre" class="form-control"
                                value="<?= $nombre ?>" required>
                        </div>

                        <!-- Apellido -->
                        <div class="col-md-4">
                            <label>Apellido<span class="required-asterisk">*</span></label>
                            <input type="text" name="apellido" id="apellido" class="form-control"
                                value="<?= $apellido ?>" required>
                        </div>

                        <!-- Especialidad -->
                        <div class="col-md-4">
                            <label>Especialidad<span class="required-asterisk">*</span></label>
                            <select name="especialidad" class="form-control select2" required>
                                <option value="">Seleccionar</option>
                                <?php foreach ($especialidades as $esp): ?>
                                    <option value="<?= $esp ?>" <?= ($especialidad == $esp ? 'selected' : '') ?>>
                                        <?= $esp ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Derivación -->
                        <div class="col-md-4">
                            <label>Derivación</label>
                            <input type="text" name="derivacion" class="form-control" value="<?= $derivacion ?>">
                        </div>

                        <!-- Horario -->
                        <div class="col-md-4">
                            <label>Preferencia horario<span class="required-asterisk">*</span></label>
                            <input type="text" name="horario" class="form-control" value="<?= $horario ?>" required>
                        </div>

                        <!-- Edad -->
                        <div class="col-md-4">
                            <label>Edad<span class="required-asterisk">*</span></label>
                            <input type="number" name="edad" class="form-control" value="<?= $edad ?>" required>
                        </div>

                        <!-- Celular -->
                        <div class="col-md-4">
                            <label>Celular<span class="required-asterisk">*</span></label>
                            <input type="text" name="celular" id="celular" class="form-control"
                                value="<?= $celular ?>" required>
                        </div>

                        <!-- Profesional -->
                        <div class="col-md-4">
                            <label>Profesional</label>
                            <input type="text" name="profesional" class="form-control"
                                value="<?= $profesional ?>">
                        </div>

                        <!-- Estado -->
                        <div class="col-md-6">
                            <label>Estado</label>
                            <select name="asignado" class="form-control">
                                <option value="">Seleccionar</option>
                                <option value="Confirmo" <?= $asignado == 'Confirmo' ? 'selected' : '' ?>>Confirmo</option>
                                <option value="No Confirmo" <?= $asignado == 'No Confirmo' ? 'selected' : '' ?>>No Confirmo</option>
                                <option value="Pendiente Confirmacion" <?= $asignado == 'Pendiente Confirmacion' ? 'selected' : '' ?>>
                                    Pendiente Confirmacion
                                </option>
                                <option value="Agendado" <?= $asignado == 'Agendado' ? 'selected' : '' ?>>Agendado</option>
                            </select>
                        </div>

                        <!-- Notas -->
                        <div class="col-md-6">
                            <label>Notas</label>
                            <input type="text" name="nota" class="form-control" value="<?= $nota ?>">
                        </div>

                    </div>

                    <button type="submit" class="btn btn-success mt-3 float-right">
                        <i class="fa fa-save"></i> Guardar
                    </button>

                </form>

            </div>
        </div>
    </div>

</div>
<script>
    $('#dni').on('blur', function() {

        let dni = $(this).val();

        if (dni.length < 6) return;

        $.post('ajax/lista_buscar_paciente.php', {
            dni: dni
        }, function(resp) {

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
    $('#formPaciente').submit(function(e) {
        e.preventDefault();

        let formData = $(this).serialize();

        $.post('', formData, function(resp) {

            Swal.fire({
                icon: 'success',
                title: 'Paciente agregado correctamente',
                timer: 1500,
                showConfirmButton: false
            }).then(() => {
                window.location.href = './?seccion=turnos';
            });

        }).fail(function() {
            Swal.fire('Error', 'Ocurrió un error al guardar', 'error');
        });
    });
    $(function() {
        // Inicializar Select2
        $('.select2').select2({
            placeholder: "Buscar especialidad...",
            allowClear: true,
            width: '100%' // Para que ocupe todo el ancho de la columna
        });

    });
</script>