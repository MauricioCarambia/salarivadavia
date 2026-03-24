<?php
require_once "inc/db.php";
$mensaje = '';
$nombre = '';
$rand = rand();
$accesos_disponibles = $conexion->query("SELECT * FROM accesos")->fetchAll(PDO::FETCH_ASSOC);

if (isset($_POST['guardar'])) {
    $nombre = trim($_POST['nombre']);
    $accesos = isset($_POST['accesos']) ? $_POST['accesos'] : [];

    if ($nombre != '') {
        $stmt = $conexion->prepare("INSERT INTO roles (nombre) VALUES (:nombre)");
        $stmt->execute([':nombre' => $nombre]);
        $rol_id = $conexion->lastInsertId();

        foreach ($accesos as $acceso_id) {
            $stmt = $conexion->prepare("INSERT INTO roles_accesos (rol_id, acceso_id) VALUES (:rol_id, :acceso_id)");
            $stmt->execute([':rol_id' => $rol_id, ':acceso_id' => $acceso_id]);
        }
        $nombre = '';
        echo '<script>
            window.location.href = "./?seccion=empleado&v=ok&nc=' . rand() . '";
        </script>';
        exit;

    } else {
        $mensaje = '<div class="alert alert-danger">Debe ingresar un nombre para el rol.</div>';
    }
}
$deshabilitar = strpos($mensaje, 'Rol creado correctamente') !== false ? 'disabled' : '';
?>
<div id="wrapper">
    <div class="normalheader transition animated fadeIn small-header">
        <div class="hpanel">
            <div class="panel-body">
                <h2>Crear nuevo rol con accesos</h2>
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
                                                    <?= $deshabilitar ?>>
                                                <?= htmlspecialchars($acceso['nombre']) ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="form-group text-right">
                                <div class="col-sm-offset-2 col-sm-10">
                                    <a href="./?seccion=empleado&nc=<?= $rand ?>" class="btn btn-info">Volver</a>
                                    <input type="submit" name="guardar" class="btn btn-success" value="Guardar rol">

                                </div>
                            </div>

                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>