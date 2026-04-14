<?php
require_once "inc/db.php";

$mensaje = '';
$nombre = '';
$rand = rand();

// Traer accesos
$stmt = $conexion->prepare("SELECT * FROM accesos ORDER BY nombre ASC");
$stmt->execute();
$accesos_disponibles = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nombre = trim($_POST['nombre'] ?? '');
    $accesos = $_POST['accesos'] ?? [];

    if ($nombre === '') {
        $mensaje = '<div class="alert alert-danger">Debe ingresar un nombre para el rol.</div>';
    } else {

        $conexion->beginTransaction();

        try {
            // Insertar rol
            $stmt = $conexion->prepare("INSERT INTO roles (nombre) VALUES (:nombre)");
            $stmt->execute([':nombre' => $nombre]);

            $rol_id = $conexion->lastInsertId();

            // Insertar accesos
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
            $mensaje = '<div class="alert alert-danger">Error al guardar el rol.</div>';
        }
    }
}
?>

<div class="container-fluid">

    <!-- HEADER -->
    <div class="row mb-3">
        <div class="col-12">
            <h3>Crear nuevo rol</h3>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8 offset-md-2">

            <?= $mensaje ?>

            <div class="card card-outline card-primary">
                <div class="card-header">
                    <h3 class="card-title">Datos del rol</h3>
                </div>

                <form method="POST">

                    <div class="card-body">

                        <!-- NOMBRE -->
                        <div class="form-group">
                            <label>Nombre del rol</label>
                            <input type="text" name="nombre" class="form-control"
                                   value="<?= htmlspecialchars($nombre) ?>" required>
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
                                                   value="<?= $acceso['Id'] ?>">

                                            <label for="acc<?= $acceso['Id'] ?>"
                                                   class="custom-control-label">
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
                            <i class="fa fa-save"></i> Guardar rol
                        </button>
                    </div>

                </form>

            </div>

        </div>
    </div>

</div>