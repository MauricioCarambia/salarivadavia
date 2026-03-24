<!-- Main Wrapper -->
<div id="wrapper">
    <div class="normalheader transition animated fadeIn small-header">
        <div class="hpanel">
            <div class="panel-body">
                <h2>Eliminar rol</h2>
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
                        $rol_id = intval($id);

                        // Validar ID
                        if (!is_numeric($id)) {
                            ?>
                            <div class="alert alert-danger">ID inválido.</div>
                            <?php
                            return;
                        }

                        // Verificar si hay empleados con ese rol
                        $stmt = $conexion->prepare("SELECT COUNT(*) FROM empleado WHERE rol_id = :rol_id");
                        $stmt->execute([':rol_id' => $rol_id]);
                        $empleados_con_rol = $stmt->fetchColumn();

                        if ($empleados_con_rol > 0) {
                            ?>
                            <div class="alert alert-warning">
                                No se puede eliminar el rol porque hay empleados asignados.<br>
                                Para eliminar este rol, primero reasigneles otro rol o elimine esos empleados.
                            </div>
                            <div class="pull-right">
                                <a href="?seccion=empleado&nc=<?= $rand ?>" class="btn btn-info">Volver</a>
                            </div>
                            <?php
                            return;
                        }

                        // Confirmar eliminación
                        if ($confirmar === 'si') {
                            try {
                                $conexion->beginTransaction();

                                // Eliminar accesos del rol
                                $stmt = $conexion->prepare("DELETE FROM roles_accesos WHERE rol_id = :rol_id");
                                $stmt->execute([':rol_id' => $rol_id]);

                                // Eliminar rol
                                $stmt = $conexion->prepare("DELETE FROM roles WHERE id = :id");
                                $stmt->execute([':id' => $rol_id]);

                                $conexion->commit();
                                ?>
                                <div class="alert alert-info">
                                    Se eliminó el rol correctamente.
                                </div>
                                <div class="pull-right">
                                    <a href="?seccion=empleado&nc=<?= $rand ?>" class="btn btn-info">Aceptar</a>
                                </div>
                                <?php
                            } catch (Exception $e) {
                                $conexion->rollBack();
                                ?>
                                <div class="alert alert-danger">
                                    Error al eliminar: <?= htmlspecialchars($e->getMessage()) ?>
                                </div>
                                <?php
                            }
                            return;
                        }

                        // Mostrar confirmación
                        ?>
                        <div class="alert alert-danger">
                            ¿Confirma eliminar el rol?<br>
                            Esta acción no puede deshacerse.
                        </div>
                        <div class="pull-right">
                            <a href="?seccion=rol_delete&id=<?= htmlspecialchars($id) ?>&confirmar=si&nc=<?= $rand ?>"
                                class="btn btn-danger">Eliminar</a>
                            <a href="?seccion=empleado&nc=<?= $rand ?>" class="btn btn-info">Cancelar</a>
                        </div>
                        <?php
                        ?>
                    </div> <!-- .panel-body -->
                </div> <!-- .hpanel -->
            </div> <!-- .col-lg-4 -->
        </div> <!-- .row -->
    </div> <!-- .content -->
</div> <!-- #wrapper -->