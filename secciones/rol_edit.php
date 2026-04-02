<?php
require_once "inc/db.php";

$mensaje = '';
$rand = rand();
$rol_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Obtener rol
$stmt = $conexion->prepare("SELECT * FROM roles WHERE id = :id");
$stmt->execute([':id' => $rol_id]);
$rol = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$rol) {
    die("Rol no encontrado.");
}

$nombre = $rol['nombre'];

// Accesos disponibles
$stmt = $conexion->prepare("SELECT * FROM accesos ORDER BY nombre ASC");
$stmt->execute();
$accesos_disponibles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Accesos actuales
$stmt = $conexion->prepare("SELECT acceso_id FROM roles_accesos WHERE rol_id = :rol_id");
$stmt->execute([':rol_id' => $rol_id]);
$accesos_actuales = $stmt->fetchAll(PDO::FETCH_COLUMN);

// GUARDAR
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nuevo_nombre = trim($_POST['nombre'] ?? '');
    $accesos = $_POST['accesos'] ?? [];

    if ($nuevo_nombre === '') {
        $mensaje = '<div class="alert alert-danger">Debe ingresar un nombre.</div>';
    } else {

        $conexion->beginTransaction();

        try {
            // Actualizar nombre
            $stmt = $conexion->prepare("UPDATE roles SET nombre = :nombre WHERE id = :id");
            $stmt->execute([
                ':nombre' => $nuevo_nombre,
                ':id' => $rol_id
            ]);

            // Borrar accesos
            $stmt = $conexion->prepare("DELETE FROM roles_accesos WHERE rol_id = :rol_id");
            $stmt->execute([':rol_id' => $rol_id]);

            // Insertar nuevos accesos
            if (!empty($accesos)) {
                $stmt = $conexion->prepare("
                    INSERT INTO roles_accesos (rol_id, acceso_id)
                    VALUES (:rol_id, :acceso_id)
                ");

                foreach ($accesos as $acceso_id) {
                    $stmt->execute([
                        ':rol_id' => $rol_id,
                        ':acceso_id' => $acceso_id
                    ]);
                }
            }

            $conexion->commit();

            echo '<script>
                window.location.href = "./?seccion=empleado&v=ok&nc=' . rand() . '";
            </script>';
            exit;

        } catch (Exception $e) {
            $conexion->rollBack();
            $mensaje = '<div class="alert alert-danger">Error al actualizar el rol.</div>';
        }
    }

    $nombre = $nuevo_nombre;
    $accesos_actuales = $accesos;
}
?>

<div class="container-fluid">

    <!-- HEADER -->
    <div class="row mb-3">
        <div class="col-12">
            <h3>Editar rol</h3>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8 offset-md-2">

            <?= $mensaje ?>

            <div class="card card-outline card-warning">
                <div class="card-header">
                    <h3 class="card-title">Datos del rol</h3>
                </div>

                <form method="POST">

                    <div class="card-body">

                        <!-- NOMBRE -->
                        <div class="form-group">
                            <label>Nombre del rol</label>
                            <input type="text"
                                   name="nombre"
                                   class="form-control"
                                   value="<?= htmlspecialchars($nombre) ?>"
                                   required>
                        </div>

                        <!-- ACCESOS -->
                        <div class="form-group">
                            <label>Permisos</label>

                            <div class="row">
                                <?php foreach ($accesos_disponibles as $acceso): ?>
                                    <div class="col-md-4">
                                        <div class="custom-control custom-checkbox">
                                            <input class="custom-control-input"
                                                   type="checkbox"
                                                   id="acc<?= $acceso['Id'] ?>"
                                                   name="accesos[]"
                                                   value="<?= $acceso['Id'] ?>"
                                                   <?= in_array($acceso['Id'], $accesos_actuales) ? 'checked' : '' ?>>

                                            <label class="custom-control-label"
                                                   for="acc<?= $acceso['Id'] ?>">
                                                <?= htmlspecialchars($acceso['nombre']) ?>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                        </div>

                    </div>

                    <div class="card-footer text-right">
                        <a href="./?seccion=empleado&nc=<?= $rand ?>" class="btn btn-secondary">
                            Volver
                        </a>

                        <button type="submit" class="btn btn-success">
                            <i class="fa fa-save"></i> Guardar cambios
                        </button>
                    </div>

                </form>

            </div>

        </div>
    </div>

</div>