<?php
require_once "inc/db.php";

$rand = rand();
$id = $_GET['id'] ?? null;
$confirmar = $_GET['confirmar'] ?? '';

if (!is_numeric($id)) {
    die('<div class="alert alert-danger">ID inválido</div>');
}

$rol_id = (int)$id;

// Verificar empleados asociados
$stmt = $conexion->prepare("SELECT COUNT(*) FROM empleado WHERE rol_id = :rol_id");
$stmt->execute([':rol_id' => $rol_id]);
$empleados_con_rol = $stmt->fetchColumn();
?>

<div class="container-fluid">

    <div class="row mb-3">
        <div class="col-12">
            <h3>Eliminar rol</h3>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6 offset-md-3">

            <div class="card card-outline card-danger">

                <div class="card-header">
                    <h3 class="card-title">Confirmación</h3>
                </div>

                <div class="card-body text-center">

                    <?php if ($empleados_con_rol > 0): ?>

                        <div class="alert alert-warning">
                            <i class="fa fa-exclamation-triangle"></i><br><br>
                            No se puede eliminar el rol porque tiene empleados asignados.
                            <br><br>
                            <small>Reasigná los empleados antes de eliminarlo.</small>
                        </div>

                        <a href="?seccion=empleado&nc=<?= $rand ?>" class="btn btn-secondary">
                            Volver
                        </a>

                    <?php else: ?>

                        <?php if ($confirmar === 'si'): ?>

                            <?php
                            try {
                                $conexion->beginTransaction();

                                // Eliminar accesos
                                $stmt = $conexion->prepare("DELETE FROM roles_accesos WHERE rol_id = :rol_id");
                                $stmt->execute([':rol_id' => $rol_id]);

                                // Eliminar rol
                                $stmt = $conexion->prepare("DELETE FROM roles WHERE id = :id");
                                $stmt->execute([':id' => $rol_id]);

                                $conexion->commit();
                                ?>

                                <div class="alert alert-success">
                                    <i class="fa fa-check"></i><br><br>
                                    El rol fue eliminado correctamente.
                                </div>

                                <a href="?seccion=empleado&nc=<?= $rand ?>" class="btn btn-primary">
                                    Aceptar
                                </a>

                            <?php
                            } catch (Exception $e) {
                                $conexion->rollBack();
                                ?>

                                <div class="alert alert-danger">
                                    Error al eliminar el rol.
                                </div>

                                <a href="?seccion=empleado&nc=<?= $rand ?>" class="btn btn-secondary">
                                    Volver
                                </a>

                            <?php } ?>

                        <?php else: ?>

                            <div class="alert alert-danger">
                                <i class="fa fa-trash"></i><br><br>
                                ¿Seguro que querés eliminar este rol?
                                <br>
                                <strong>Esta acción no se puede deshacer.</strong>
                            </div>

                            <a href="?seccion=rol_delete&id=<?= $rol_id ?>&confirmar=si&nc=<?= $rand ?>"
                               class="btn btn-danger">
                                <i class="fa fa-trash"></i> Eliminar
                            </a>

                            <a href="?seccion=empleado&nc=<?= $rand ?>"
                               class="btn btn-secondary">
                                Cancelar
                            </a>

                        <?php endif; ?>

                    <?php endif; ?>

                </div>

            </div>

        </div>
    </div>

</div>