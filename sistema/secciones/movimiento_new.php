<?php
require_once 'inc/db.php'; // tu conexión PDO
date_default_timezone_set('America/Argentina/Buenos_Aires');
$mensaje = '';
$v = isset($_GET["v"]) ? $_GET["v"] : '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo = $_POST['tipo'];
    $descripcion = $_POST['descripcion'];
    $monto = floatval($_POST['monto']);
    $fecha_actual = date('Y-m-d H:i:s');
    $stmt = $conexion->prepare("
    INSERT INTO caja (tipo, descripcion, monto, fecha) 
    VALUES (:tipo, :descripcion, :monto, :fecha)
");
    $stmt->execute([
        ':tipo' => $tipo,
        ':descripcion' => $descripcion,
        ':monto' => $monto,
        ':fecha' => $fecha_actual
    ]);

    $mensaje = '<div class="alert alert-success">Registro cargado satisfactoriamente.</div>';
}
?>

<div id="wrapper">
    <div class="normalheader transition animated fadeIn small-header">
        <div class="hpanel">
            <div class="panel-body">
                <h2>
                    Agregar movimiento
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
                        <h3>Nuevo movimiento</h3>
                        <?php
                        $redirect = './?seccion=movimiento_new&v=' . $v . '&nc=' . $rand;
                        if ($v == 'ok') {
                            $redirect = './index_clean.php?seccion=movimiento_new&v=' . $v . '&nc=' . $rand;
                        }
                        ?>
                        <form class="form-horizontal" action="<?php echo $redirect; ?>" method="POST">
                            <div class="form-group">
                                <label class="col-sm-2 control-label">Tipo</label>
                                <div class="col-sm-6">
                                    <select class="form-control" name="tipo" required>
                                        <option value="">Seleccionar</option>
                                        <option value="ingreso">Ingreso</option>
                                        <option value="egreso">Egreso</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-2 control-label">Descripcion</label>
                                <div class="col-sm-6">
                                    <input class="form-control" type="text" name="descripcion" placeholder="Descripción"
                                        required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-2 control-label">Monto</label>
                                <div class="col-sm-6">
                                    <input class="form-control" type="number" step="0.01" name="monto"
                                        placeholder="Monto" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <div class="col-sm-12">
                                    <div class="pull-right">
                                        <?php
                                        if (!empty($v)) {
                                            echo '<a href="./' . $_SESSION["volver"] . '&nc=' . $rand . '" class="btn btn-info">Volver al turno</a>';
                                        } else {
                                            echo '<a href="./?seccion=caja&nc=' . $rand . '" class="btn btn-info">Volver</a>';
                                        }
                                        ?>
                                        <input type="submit" class="btn btn-success" name="guardar" value="Guardar">
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>