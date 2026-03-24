<?php

if (isset($_POST['guardar'])) {
    $id = $_POST['id'];
    $texto = $_POST['texto'];
    $profesionalid = $_POST['profesional'];
    $especialidadId = $_POST['especialidad'];
    $pacienteID = $_POST['pacienteID'];

    $sql = "INSERT INTO historias_clinicas (paciente_id, profesional_id, fecha, texto) VALUES ('$id', '$profesionalid', NOW(), '$texto')";
    $rsql = mysql_query($sql, $conexion);
    $last_id = mysql_insert_id();

    $mensaje = '<div class="alert alert-info">Registro agregado satisfactoriamente.</div>';

    $texto = '';
}


?>
<!-- Main Wrapper -->
<div id="wrapper">
    <div class="normalheader transition animated fadeIn small-header">
        <div class="hpanel">
            <div class="panel-body">
                <h2>
                    Registrar nuevo registro
                </h2>
            </div>
        </div>
    </div>
    <?php echo $mensaje; ?>
    <div class="content animate-panel">
        <div class="row">
            <div class="col-lg-10">
                <div class="hpanel">
                    <div class="panel-body">
                        <form class="form-horizontal" action="./<?php if ($_GET['v'] != '') {
                            echo 'index_clean.php';
                        } ?>?seccion=historia_new&v=<?php echo $_GET['v']; ?>&nc=<?php echo $rand; ?>" method="POST">
                            <?php
                            $id = $_GET['id'];
                            $consulta = "SELECT  p.Id AS profesionalId, p.apellido AS apellido_profesional, p.nombre AS nombre_profesional, especialidad_id AS especialidadId
                                                    FROM profesionales p
                                                    ORDER BY  p.apellido";

                            $resultado = mysql_query($consulta, $conexion);

                            echo '<label for="profesional" class="col-sm-2 control-label">Seleccionar</label>';
                            echo '<select id="profesional" name="profesional" required>';
                            echo '<option value="" ' . ($id == '' ? 'selected' : '') . '>Seleccione una opcion</option>';
                            // Recorre los resultados y genera las opciones
                            while ($rArray = mysql_fetch_array($resultado)) {

                                echo '<option value="' . $rArray['profesionalId'] . '">' . $rArray['apellido_profesional'] . ' ' . $rArray['nombre_profesional'] . '</option>';

                            }
                            echo '<input type="hidden" name="especialidad" value="' . $especialidadId . '">';
                            echo '<input type="hidden" name="id" value="' . $id . '">';
                            echo '</select>';
                            ?>
                            <div class="form-group">
                                <label>Registro</label>
                                <div class="col-sm-10">
                                    <textarea rows="10" cols="10" class="form-control" name="texto"
                                        value="<?php echo $texto; ?>" placeholder="Requerido" required></textarea>
                                </div>

                                <div class="form-group">
                                    <div class="col-sm-12">
                                        <div class="pull-right">
                                            <?php
                                            if ($_GET['v'] != '') {
                                                echo '<a href="./' . $_SESSION["volver"] . '&nc=' . $rand . '" class="btn btn-info">Volver al turno</a>';
                                            } else {
                                                echo '<a href="./?seccion=historia_pacientes&nc=' . $rand . '" class="btn btn-info">Volver</a>';


                                            }
                                            ?>
                                            <input type="submit" class="btn btn-info" name="guardar" value="Guardar">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>