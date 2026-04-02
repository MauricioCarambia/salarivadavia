<?php
require_once "inc/db.php";

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Obtener datos del empleado
$stmt = $conexion->prepare("SELECT * FROM empleados WHERE id = :id");
$stmt->execute([':id' => $id]);
$empleado = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$empleado) {
    die("Empleado no encontrado.");
}

// Obtener todos los roles
$roles = $conexion->query("SELECT id, nombre FROM roles")->fetchAll(PDO::FETCH_ASSOC);

$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nuevo_rol = intval($_POST['rol_id'] ?? 0);

    $stmt = $conexion->prepare("UPDATE empleados SET rol_id = :rol_id WHERE id = :id");
    $stmt->execute([':rol_id' => $nuevo_rol, ':id' => $id]);
    $empleado['rol_id'] = $nuevo_rol; // actualizar variable en memoria
    $nombre = $nuevo_nombre;
    $accesos_actuales = $accesos;
    echo '<script>
            window.location.href = "./?seccion=empleado&v=ok&nc=' . rand() . '";
        </script>';
    exit;
}
?>

<div id="wrapper">
    <div class="normalheader transition animated fadeIn small-header">
        <div class="hpanel">
            <div class="panel-body">
                <h2>Editar rol de empleado</h2>
            </div>
        </div>
    </div>

    <div class="content animate-panel">
        <div class="row">
            <div class="col-lg-6 col-lg-offset-3">
                <?= $mensaje ?>
                <div class="hpanel">
                    <div class="panel-body">
                        <form method="POST" class="form-horizontal">

                            <div class="form-group">
                                <label class="col-sm-3 control-label">Usuario</label>
                                <div class="col-sm-9">
                                    <input type="text" class="form-control"
                                        value="<?= htmlspecialchars($empleado['usuario']) ?>" disabled>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="col-sm-3 control-label">Rol</label>
                                <div class="col-sm-9">
                                    <select name="rol_id" class="form-control" required>
                                        <option value="">Seleccione un rol...</option>
                                        <?php foreach ($roles as $rol): ?>
                                            <option value="<?= $rol['id'] ?>" <?= ($rol['id'] == $empleado['rol_id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($rol['nombre']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group text-right">
                                <div class="col-sm-offset-3 col-sm-9">
                                    <button type="submit" class="btn btn-primary">Guardar</button>
                                    <a href="./?seccion=empleado" class="btn btn-default">Volver</a>
                                </div>
                            </div>

                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>