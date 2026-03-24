<?php
require_once "inc/db.php";

$mensaje = '';
$empleado_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Verificar si el empleado existe
$stmt = $conexion->prepare("SELECT * FROM empleado WHERE id = :id");
$stmt->execute([':id' => $empleado_id]);
$empleado = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$empleado) {
    $mensaje = '<div class="alert alert-danger">Empleado no encontrado.</div>';
} else {
    $usuario = $empleado['usuario'];

    // Si se envió el formulario
    if (isset($_POST['guardar'])) {
        $nueva = trim($_POST['nueva']);

        if ($nueva === '') {
            $mensaje = '<div class="alert alert-danger">Debe ingresar una nueva contraseña.</div>';
        } else {
            $hash = password_hash($nueva, PASSWORD_DEFAULT);
            $stmt = $conexion->prepare("UPDATE empleado SET contrasenia = :hash WHERE id = :id");
            $stmt->execute([':hash' => $hash, ':id' => $empleado_id]);
            echo '<script>
            window.location.href = "./?seccion=empleado&v=ok&nc=' . rand() . '";
        </script>';
            exit;
        }
    }
}
?>

<div id="wrapper">
    <div class="normalheader transition animated fadeIn small-header">
        <div class="hpanel">
            <div class="panel-body">
                <h2>Cambiar contraseña</h2>
            </div>
        </div>
    </div>

    <div class="content animate-panel">
        <div class="row">
            <div class="col-lg-6 col-lg-offset-3">
                <?= $mensaje ?>
                <?php if ($empleado): ?>
                    <div class="hpanel">
                        <div class="panel-body">
                            <form method="POST" class="form-horizontal">
                                <div class="form-group">
                                    <label class="col-sm-4 control-label">Usuario</label>
                                    <div class="col-sm-8">
                                        <input type="text" class="form-control" value="<?= htmlspecialchars($usuario) ?>"
                                            disabled>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="col-sm-4 control-label">Nueva contraseña</label>
                                    <div class="col-sm-8">
                                        <input type="password" name="nueva" id="nueva" class="form-control"
                                            placeholder="Ingrese nueva contraseña" required>
                                    </div>
                                </div>

                                <div class="form-group text-right">
                                    <div class="col-sm-offset-4 col-sm-8">

                                        <a href="./?seccion=empleado&nc=<?= rand(1, 9999) ?>"
                                            class="btn btn-info">Volver</a>
                                        <input type="submit" name="guardar" class="btn btn-success" value="Guardar"
                                            id="btnCambiar" disabled>
                                    </div>
                                </div>
                            </form>

                            <script>
                                // Habilitar el botón solo si hay algo escrito
                                document.getElementById('nueva').addEventListener('input', function () {
                                    document.getElementById('btnCambiar').disabled = this.value.trim() === '';
                                });
                            </script>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>