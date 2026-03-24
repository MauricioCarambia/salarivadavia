<?php
require_once "inc/db.php";

$mensaje = '';
$rol_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Obtener datos del rol
$stmt = $conexion->prepare("SELECT * FROM roles WHERE id = :id");
$stmt->execute([':id' => $rol_id]);
$rol = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$rol) {
    die("Rol no encontrado.");
}

$nombre = $rol['nombre'];
$accesos_disponibles = $conexion->query("SELECT * FROM accesos")->fetchAll(PDO::FETCH_ASSOC);

// Obtener accesos actuales
$stmt = $conexion->prepare("SELECT acceso_id FROM roles_accesos WHERE rol_id = :rol_id");
$stmt->execute([':rol_id' => $rol_id]);
$accesos_actuales = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'acceso_id');

// Actualizar si se envió el formulario
if (isset($_POST['guardar'])) {
    $nuevo_nombre = trim($_POST['nombre']);
    $accesos = isset($_POST['accesos']) ? $_POST['accesos'] : [];

    if ($nuevo_nombre != '') {
        // Actualizar nombre
        $stmt = $conexion->prepare("UPDATE roles SET nombre = :nombre WHERE id = :id");
        $stmt->execute([':nombre' => $nuevo_nombre, ':id' => $rol_id]);

        // Actualizar accesos
        $conexion->prepare("DELETE FROM roles_accesos WHERE rol_id = :rol_id")->execute([':rol_id' => $rol_id]);
        foreach ($accesos as $acceso_id) {
            $stmt = $conexion->prepare("INSERT INTO roles_accesos (rol_id, acceso_id) VALUES (:rol_id, :acceso_id)");
            $stmt->execute([':rol_id' => $rol_id, ':acceso_id' => $acceso_id]);
        }
        $nombre = $nuevo_nombre;
        $accesos_actuales = $accesos;
        echo '<script>
            window.location.href = "./?seccion=empleado&v=ok&nc=' . rand() . '";
        </script>';
        exit;

    } else {
        $mensaje = '<div class="alert alert-danger">Debe ingresar un nombre para el rol.</div>';
    }
}
$deshabilitar = strpos($mensaje, 'Rol actualizado correctamente') !== false ? 'disabled' : '';

?>
<div id="wrapper">
    <div class="normalheader transition animated fadeIn small-header">
        <div class="hpanel">
            <div class="panel-body">
                <h2>Editar rol</h2>
            </div>
        </div>
    </div>

    <div class="content animate-panel">
        <div class="row">
            <div class="col-lg-8 col-lg-offset-2">
                <?= $mensaje ?>
                <div class="hpanel">
                    <div class="panel-body">
                        <form method="POST" class="form-horizontal">

                            <div class="form-group">
                                <label class="col-sm-2 control-label">Nombre</label>
                                <div class="col-sm-10">
                                    <input type="text" name="nombre" class="form-control"
                                        value="<?= htmlspecialchars($nombre) ?>" <?= $deshabilitar ?> required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="col-sm-2 control-label">Accesos</label>
                                <div class="col-sm-10">
                                    <?php foreach ($accesos_disponibles as $acceso): ?>
                                        <div class="checkbox">
                                            <label>
                                                <input type="checkbox" name="accesos[]" value="<?= $acceso['Id'] ?>"
                                                    <?= in_array($acceso['Id'], $accesos_actuales) ? 'checked' : '' ?>
                                                    <?= $deshabilitar ?>>
                                                <?= htmlspecialchars($acceso['nombre']) ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="form-group text-right">
                                <div class="col-sm-offset-2 col-sm-10">

                                    <input type="submit" name="guardar" class="btn btn-success" value="Guardar">

                                    <a href="./?seccion=empleado&nc=<?= $rand ?>" class="btn btn-danger">Volver</a>

                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>