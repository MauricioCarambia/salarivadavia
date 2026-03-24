<!-- Main Wrapper -->
<div id="wrapper">
    <div class="normalheader transition animated fadeIn small-header">
        <div class="hpanel">
            <div class="panel-body">
                <h2>
                    Eliminar registro
                </h2>
            </div>
        </div>
    </div>
    <div class="content animate-panel">
        <div class="row">
            <div class="col-lg-4">
                <div class="hpanel">
                    <div class="panel-body">
                        <?php
                        $confirmar = $_GET['confirmar'];
                        $id = $_GET['id'];

                        if($confirmar == 'si') {
                            $sEliminar = "DELETE FROM historias_clinicas WHERE Id='$id'";
                            $rEliminar = mysql_query($sEliminar, $conexion);

                            echo '
                            <div class="alert alert-info">Se elimin&oacute; el registro.</div>
                            <div class="pull-right">
                            <a href="?seccion=historia_pacientes&nc='.$rand.'" class="btn btn-info">Aceptar</a>
                            </div>
                            ';
                        } else {
                            echo '
                            <div class="alert alert-danger">&iquest;Confirma eliminar el registro?.<br>
                            Esta acci&oacute;n no puede deshacerse.<br>
                            </div>
                            <div class="pull-right">
                            <a href="?seccion=historia_clinica_delete&id='.$id.'&confirmar=si&nc='.$rand.'" class="btn btn-info">Eliminar</a>
                            <a href="?seccion=historia_pacientes&nc='.$rand.'" class="btn btn-info">Cancelar</a>
                            </div>';
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>