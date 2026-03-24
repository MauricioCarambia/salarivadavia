<!-- Main Wrapper -->
<div id="wrapper">
    <div class="normalheader transition animated fadeIn small-header">
        <div class="hpanel">
            <div class="panel-body">
                <h2>
                    Eliminar movimiento
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
                        require_once "inc/db.php";// Asegurate de tener una conexión PDO en esta variable: $pdo
                        
                        $confirmar = $_GET['confirmar'] ?? '';
                        $id = $_GET['id'] ?? '';
                        $rand = rand(); // Generás un número aleatorio para evitar cacheo en los links
                        
                        if (!is_numeric($id)) {
                            echo '<div class="alert alert-danger">ID inválido.</div>';
                            exit;
                        }

                        if ($confirmar === 'si') {
                            // Usar transacción para asegurar integridad
                            try {
                                $conexion->beginTransaction();



                                // Eliminar paciente
                                $stmtPaciente = $conexion->prepare("DELETE FROM caja WHERE id = ?");
                                $stmtPaciente->execute([$id]);

                                $conexion->commit();

                                echo '
        <div class="alert alert-info">Se eliminó el registro.</div>
        <div class="pull-right">
          <a href="?seccion=caja&nc=' . $rand . '" class="btn btn-info">Aceptar</a>
        </div>';
                            } catch (Exception $e) {
                                $conexion->rollBack();
                                echo '<div class="alert alert-danger">Error al eliminar: ' . $e->getMessage() . '</div>';
                            }
                        } else {
                            echo '
    <div class="alert alert-danger">¿Confirma eliminar el registro?<br>
      Esta acción no puede deshacerse.<br>
    </div>
    <div class="pull-right">
      <a href="?seccion=movimiento_delete&id=' . htmlspecialchars($id) . '&confirmar=si&nc=' . $rand . '" class="btn btn-info">Eliminar</a>
      <a href="?seccion=caja&nc=' . $rand . '" class="btn btn-info">Cancelar</a>
    </div>';
                        }
                        ?>

                    </div>
                </div>
            </div>
        </div>
    </div>