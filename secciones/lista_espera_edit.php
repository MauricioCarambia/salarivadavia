<?php
require_once __DIR__ . '/../inc/db.php';

$id = $_GET['id'] ?? 0;
$v = $_GET['v'] ?? null;
$rand = rand();
$mensaje = '';

/* ===============================
   ACTUALIZAR PACIENTE
================================*/
if (isset($_POST['guardar'])) {

    $sql = "UPDATE lista_espera SET
                nombre = :nombre,
                apellido = :apellido,
                documento = :documento,
                especialidad = :especialidad,
                disponibilidad = :disponibilidad,
                horario = :horario,
                celular = :celular,
                edad = :edad,
                estudio = :estudio,
                profesional = :profesional,
                asignado = :asignado
            WHERE Id = :id";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':nombre' => $_POST['nombre'],
        ':apellido' => $_POST['apellido'],
        ':documento' => $_POST['documento'],
        ':especialidad' => $_POST['especialidad'],
        ':disponibilidad' => $_POST['disponibilidad'],
        ':horario' => $_POST['horario'],
        ':celular' => $_POST['celular'],
        ':edad' => $_POST['edad'],
        ':estudio' => $_POST['estudio'],
        ':profesional' => $_POST['profesional'],
        ':asignado' => $_POST['asignado'],
        ':id' => $id
    ]);

    $mensaje = '<div class="alert alert-info">Paciente editado satisfactoriamente.</div>';
}


/* ===============================
   OBTENER PACIENTE
================================*/
$stmt = $pdo->prepare("SELECT * FROM lista_espera WHERE Id = :id");
$stmt->execute([':id' => $id]);

$rArray = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!-- Main Wrapper -->
<div id="wrapper">
    <div class="normalheader transition animated fadeIn small-header">
        <div class="hpanel">
            <div class="panel-body">
                <h2>
                    Editar paciente de lista de espera
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
                        <form class="form-horizontal"
                                    action="./?seccion=lista_espera_edit&id=<?= $id ?>&v=<?= htmlspecialchars($v) ?>&nc=<?= $rand ?>"
                                    method="POST">
                            <div class="form-group">
                                <label class="col-sm-2 control-label">Nombre</label>
                                <div class="col-sm-10">
                                    <input type="text" class="form-control" name="nombre"
                                        value="<?php echo $rArray['nombre']; ?>" placeholder="Requerido" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-2 control-label">Apellido</label>
                                <div class="col-sm-10">
                                    <input type="text" class="form-control" name="apellido"
                                        value="<?php echo $rArray['apellido']; ?>" placeholder="Requerido" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-2 control-label">Documento</label>
                                <div class="col-sm-10">
                                    <div class="input-group">
                                        <span class="input-group-addon">S&oacute;lo n&uacute;meros:</span>
                                        <input type="number" min="0" step="1" class="form-control" name="documento"
                                            value="<?php echo $rArray['documento']; ?>" placeholder="Requerido"
                                            required>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-2 control-label">Especialidad</label>
                                <div class="col-sm-10">
                                   <select class="form-control" name="especialidad">

<option value="Psicologia Adulto"
<?= ($rArray['especialidad']=='Psicologia Adulto')?'selected':'' ?>>
Psicologia Adulto
</option>

<option value="Psicologia Menores"
<?= ($rArray['especialidad']=='Psicologia Menores')?'selected':'' ?>>
Psicologia Menores
</option>

<option value="Psicopedagogia"
<?= ($rArray['especialidad']=='Psicopedagogia')?'selected':'' ?>>
Psicopedagogia
</option>

<option value="Fonoaudiologia"
<?= ($rArray['especialidad']=='Fonoaudiologia')?'selected':'' ?>>
Fonoaudiologia
</option>

<option value="Kinesiologia"
<?= ($rArray['especialidad']=='Kinesiologia')?'selected':'' ?>>
Kinesiologia
</option>

</select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-2 control-label">Disponibilidad Horaria</label>
                                <div class="col-sm-10">
                                   <select class="form-control" name="disponibilidad">

<option value="Mañana"
<?= ($rArray['disponibilidad']=='Mañana')?'selected':'' ?>>
Mañana
</option>

<option value="Tarde"
<?= ($rArray['disponibilidad']=='Tarde')?'selected':'' ?>>
Tarde
</option>

</select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-2 control-label">Horario</label>
                                <div class="col-sm-10">
                                    <input type="text" class="form-control" name="horario"
                                        value="<?php echo $rArray['horario']; ?>">
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-2 control-label">Celular</label>
                                <div class="col-sm-10">
                                    <input type="text" class="form-control" name="celular"
                                        value="<?php echo $rArray['celular']; ?>">
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-2 control-label">Edad</label>
                                <div class="col-sm-10">
                                    <input type="text" class="form-control" name="edad"
                                        value="<?php echo $rArray['edad']; ?>">
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-2 control-label">Estudio</label>
                                <div class="col-sm-10">
                                    <input type="text" class="form-control" name="estudio"
                                        value="<?php echo $rArray['estudio']; ?>">
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-2 control-label">Profesional</label>
                                <div class="col-sm-10">
                                    <input type="text" class="form-control" name="profesional"
                                        value="<?php echo $rArray['profesional']; ?>">
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-2 control-label">Asignado</label>
                                <div class="col-sm-10">
                                   <select class="form-control" name="asignado">
                                    <option value=""
<?= empty($rArray['asignado']) ? 'selected' : '' ?>>
-- Seleccionar estado --
</option>

<option value="Confirmo"
<?= ($rArray['asignado']=='Confirmo')?'selected':'' ?>>
Confirmo
</option>

<option value="No Confirmo"
<?= ($rArray['asignado']=='No Confirmo')?'selected':'' ?>>
No Confirmo
</option>

<option value="Pendiente Confirmacion"
<?= ($rArray['asignado']=='Pendiente Confirmacion')?'selected':'' ?>>
Pendiente Confirmacion
</option>

</select>
                                </div>
                            </div>
                            <div class="form-group">
                                <div class="col-sm-12">
                                    <div class="pull-right">
                                      <?php
if ($v != '') {
    echo '<a href="./?seccion=lista_espera&id='.$v.'&nc='.$rand.'" class="btn btn-info">Volver</a>';
} else {
    echo '<a href="./?seccion=lista_espera&nc='.$rand.'" class="btn btn-info">Volver</a>';
}
?>
                                        <input type="submit" class="btn btn-info" name="guardar" value="Guardar">
                        </form>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
</div>
</div>