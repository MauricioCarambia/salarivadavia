<?php
$mensaje = '';

$nombre = '';
$apellido = '';
$documento = '';
$especialidad = '';
$disponibilidad = '';
$horario = '';
$celular = '';
$edad = '';
$estudio = '';
$profesional = '';
$asignado = '';

$v = $_GET['v'] ?? '';

if (isset($_POST['guardar'])) {

    $nombre          = $_POST['nombre'];
    $apellido        = $_POST['apellido'];
    $documento       = $_POST['documento'];
    $especialidad    = $_POST['especialidad'];
    $disponibilidad  = $_POST['disponibilidad'];
    $horario         = $_POST['horario'];
    $celular         = $_POST['celular'];
    $edad            = $_POST['edad'];
    $estudio         = $_POST['estudio'];
    $profesional     = $_POST['profesional'];
    $asignado        = $_POST['asignado'];

    $sql = "
        INSERT INTO lista_espera
        (
            nombre,
            apellido,
            documento,
            especialidad,
            disponibilidad,
            horario,
            celular,
            edad,
            estudio,
            profesional,
            asignado
        )
        VALUES
        (
            :nombre,
            :apellido,
            :documento,
            :especialidad,
            :disponibilidad,
            :horario,
            :celular,
            :edad,
            :estudio,
            :profesional,
            :asignado
        )
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':nombre'         => $nombre,
        ':apellido'       => $apellido,
        ':documento'      => $documento,
        ':especialidad'   => $especialidad,
        ':disponibilidad' => $disponibilidad,
        ':horario'        => $horario,
        ':celular'        => $celular,
        ':edad'           => $edad,
        ':estudio'        => $estudio,
        ':profesional'    => $profesional,
        ':asignado'       => $asignado
    ]);

    $last_id = $pdo->lastInsertId();

    $mensaje = '<div class="alert alert-info">Paciente agregado satisfactoriamente.</div>';

    // limpiar formulario
    $documento = '';
    $nombre = '';
    $apellido = '';
    $especialidad = '';
    $disponibilidad = '';
    $horario = '';
    $celular = '';
    $edad = '';
    $estudio = '';
    $profesional = '';
    $asignado = '';
}
?>
<!-- Main Wrapper -->
<div id="wrapper">
    <div class="normalheader transition animated fadeIn small-header">
        <div class="hpanel">
            <div class="panel-body">
                <h2>
                    Alta de paciente en lista de espera
                </h2>
            </div>
        </div>
    </div>
    <?php echo $mensaje; ?>
    <div class="content animate-panel">
        <div class="row">
            <div class="col-lg-12">
                <div class="hpanel">
                    <div class="panel-body">
                        <form class="form-horizontal" action="./<?php if ($v != '') {
                            echo 'index_clean.php';
                        } ?>?seccion=lista_espera_new&v=<?php echo $v; ?>&nc=<?php echo $rand; ?>"
                            method="POST">
                            <div class="form-group">
                                <label class="col-sm-2 control-label">Nombre</label>
                                <div class="col-sm-10">
                                    <input type="text" class="form-control" name="nombre" value="<?= htmlspecialchars($nombre) ?>"
                                        placeholder="Requerido" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-2 control-label">Apellido</label>
                                <div class="col-sm-10">
                                    <input type="text" class="form-control" name="apellido"
                                        value="<?= htmlspecialchars($apellido) ?>" placeholder="Requerido" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-2 control-label">Documento</label>
                                <div class="col-sm-10">
                                    <div class="input-group">
                                        <span class="input-group-addon">S&oacute;lo n&uacute;meros:</span>
                                        <input type="number" min="0" step="1" class="form-control" name="documento"
                                            value="<?= htmlspecialchars($documento) ?>" placeholder="Requerido" required>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-2 control-label">Especialidad</label>
                                <div class="col-sm-10">
                                    <select class="form-control" name="especialidad">
                                        <option value="">Seleccionar</option>
                                        <option <?php if ($especialidad == 'Psicologia adulto') {
                                            echo 'selected';
                                        } ?> value="Psicologia Adulto">Psicologia Adulto</option>
                                        <option <?php if ($especialidad == 'Psicologia Menores') {
                                            echo 'selected';
                                        } ?> value="Psicologia Menores">Psicologia Menores</option>
                                        <option <?php if ($especialidad == 'Psicopedagogia') {
                                            echo 'selected';
                                        } ?> value="Psicopedagogia">Psicopedagogia</option>
                                        <option <?php if ($especialidad == 'Fonoaudiologia') {
                                            echo 'selected';
                                        } ?> value="Fonoaudiologia">Fonoaudiologia</option>
                                        <option <?php if ($especialidad == 'Kinesiologia') {
                                            echo 'selected';
                                        } ?> value="Kinesiologia">Kinesiologia</option>

                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-2 control-label">Disponibilidad Horaria</label>
                                <div class="col-sm-10">
                                    <select class="form-control" name="disponibilidad">
                                        <option value="">Seleccionar</option>
                                        <option <?php if ($disponibilidad == 'Mañana') {
                                            echo 'selected';
                                        } ?> value="Mañana">Mañana</option>
                                        <option <?php if ($disponibilidad == 'Tarde') {
                                            echo 'selected';
                                        } ?> value="Tarde">Tarde</option>

                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-2 control-label">Horario</label>
                                <div class="col-sm-10">
                                    <input type="text" class="form-control" name="horario"
                                        value="<?= htmlspecialchars($horario) ?>">
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-2 control-label">Celular</label>
                                <div class="col-sm-10">
                                    <input type="text" class="form-control" name="celular"
                                        value="<?= htmlspecialchars($celular) ?>">
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-2 control-label">Edad</label>
                                <div class="col-sm-10">
                                    <input type="text" class="form-control" name="edad" value="<?= htmlspecialchars($edad) ?>">
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-2 control-label">Observaciones</label>
                                <div class="col-sm-10">
                                    <input type="text" class="form-control" name="estudio"
                                       value="<?= htmlspecialchars($estudio) ?>">
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-2 control-label">Profesional</label>
                                <div class="col-sm-10">
                                    <input type="text" class="form-control" name="profesional"
                                        value="<?= htmlspecialchars($profesional) ?>">
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-2 control-label">Asignado</label>
                                <div class="col-sm-10">
                                    <select class="form-control" name="asignado">
                                        <option value="">Seleccionar</option>
                                        <option <?php if($asignado=='Confirmo'){echo 'selected';}?>>Confirmo</option>
                                        <option <?php if($asignado=='No Confirmo'){echo 'selected';}?>>No Confirmo</option>
                                        <option <?php if($asignado=='Pendiente Confirmacion'){echo 'selected';}?>>Pendiente Confirmacion</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <div class="col-sm-12">
                                    <div class="pull-right">
                                        <?php
                                        if ($v != '') {
                                            echo '<a href="./' . $_SESSION["volver"] . '&nc=' . $rand . '" class="btn btn-info">Volver al turno</a>';
                                        } else {
                                            echo '<a href="./?seccion=lista_espera&nc=' . $rand . '" class="btn btn-info">Volver</a>';
                                        }
                                        ?>
                                        <input type="submit" class="btn btn-info" name="guardar" value="Guardar">
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>