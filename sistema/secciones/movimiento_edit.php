<?php
require_once 'inc/db.php'; // conexión PDO

date_default_timezone_set('America/Argentina/Buenos_Aires');
$mensaje = '';
$id = $_GET['id'] ?? null;
$rand = rand();
$v = isset($_GET["v"]) ? $_GET["v"] : '';
if (!is_numeric($id) || $id <= 0) {
    die('ID no válido.');
}
if (empty($id)) {
    die('ID no presente en la URL.');
}

$tipo = $descripcion = $monto = '';
$fecha_actual = date('Y-m-d H:i:s');
// Obtener datos existentes
$stmt = $conexion->prepare("SELECT * FROM caja WHERE id = :id");
$stmt->execute([':id' => $id]);
$registro = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$registro) {
    die('Movimiento no encontrado.');
}

$tipo = $registro['tipo'];
$descripcion = $registro['descripcion'];
$monto = $registro['monto'];

// Actualizar si se envió el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo = $_POST['tipo'];
    $descripcion = $_POST['descripcion'];
    $monto = floatval($_POST['monto']);

    $stmt = $conexion->prepare("
    UPDATE caja 
    SET tipo = :tipo, descripcion = :descripcion, monto = :monto, fecha = :fecha 
    WHERE id = :id
");
    $stmt->execute([
        ':tipo' => $tipo,
        ':descripcion' => $descripcion,
        ':monto' => $monto,
        ':fecha' => $fecha_actual,
        ':id' => $id
    ]);

    $mensaje = '<div class="alert alert-success">Movimiento actualizado correctamente.</div>';
}
?>

<div id="wrapper">
    <div class="normalheader transition animated fadeIn small-header">
        <div class="hpanel">
            <div class="panel-body">
                <h2>
                    Editar movimiento
                </h2>
            </div>
        </div>
    </div>
    <?php echo $mensaje; ?>
    <div class="content animate-panel">
        <div class="row">
            <div class="col-lg-6">
                <div class="hpanel">
                    <div class="panel-body">
                        <h3>Editar movimiento</h3>

                        <form class="form-horizontal"
                            action="./?seccion=movimiento_edit&id=<?php echo htmlspecialchars($id); ?>&v=<?php echo htmlspecialchars($v); ?>&nc=<?php echo htmlspecialchars($rand); ?>"
                            method="POST">
                            <div class="form-group">
                                <label class="col-sm-2 control-label">Tipo</label>
                                <div class="col-sm-6">
                                    <select class="form-control" name="tipo" required>
                                        <option value="">Seleccionar</option>
                                        <option value="ingreso" <?= $tipo == 'ingreso' ? 'selected' : '' ?>>Ingreso
                                        </option>
                                        <option value="egreso" <?= $tipo == 'egreso' ? 'selected' : '' ?>>Egreso</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="col-sm-2 control-label">Descripción</label>
                                <div class="col-sm-6">
                                    <input class="form-control" type="text" name="descripcion"
                                        value="<?= htmlspecialchars($descripcion) ?>" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="col-sm-2 control-label">Monto</label>
                                <div class="col-sm-6">
                                    <input class="form-control" type="number" step="0.01" name="monto"
                                        value="<?= $monto ?>" required>
                                </div>
                            </div>


                            <div class="pull-right">
                                <a href="./?seccion=caja&nc=<?php echo $rand; ?>" class="btn btn-info">Volver</a>
                                <input type="submit" class="btn btn-primary" value="Guardar">
                            </div>

                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>