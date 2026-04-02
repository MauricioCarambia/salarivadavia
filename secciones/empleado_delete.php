<!-- Main Wrapper -->
<div id="wrapper">
    <div class="normalheader transition animated fadeIn small-header">
        <div class="hpanel">
            <div class="panel-body">
                <h2>Eliminar empleado</h2>
            </div>
        </div>
    </div>

    <div class="content animate-panel">
        <div class="row">
            <div class="col-lg-4">
                <div class="hpanel">
                    <div class="panel-body">
                        <?php
                        require_once "inc/db.php"; // $conexion debe ser un PDO válido
                        
                        $confirmar = $_GET['confirmar'] ?? '';
                        $id = $_GET['id'] ?? '';
                        $rand = rand();

                        if (!is_numeric($id)) {
                            echo '<div class="alert alert-danger">ID inválido.</div>';
                            exit;
                        }

                        if ($confirmar === 'si') {
                            try {
                                $conexion->beginTransaction();

                                $stmtempleado = $conexion->prepare("DELETE FROM empleados WHERE Id = ?");
                                $stmtempleado->execute([$id]);

                                $conexion->commit();

                                echo '
                <div class="alert alert-info">Se eliminó el empleado.</div>
                <div class="pull-right">
                  <a href="?seccion=empleado&nc=' . $rand . '" class="btn btn-info">Aceptar</a>
                </div>';
                            } catch (Exception $e) {
                                $conexion->rollBack();
                                echo '<div class="alert alert-danger">Error al eliminar: ' . htmlspecialchars($e->getMessage()) . '</div>';
                            }
                        } else {
                            echo '
              <div class="alert alert-danger">
                ¿Confirma eliminar el empleado?<br>
                Esta acción no puede deshacerse.<br>
              </div>
              <div class="pull-right">
                <a href="?seccion=empleado_delete&id=' . htmlspecialchars($id) . '&confirmar=si&nc=' . $rand . '" class="btn btn-info">Eliminar</a>
                <a href="?seccion=empleado&nc=' . $rand . '" class="btn btn-info">Cancelar</a>
              </div>';
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>