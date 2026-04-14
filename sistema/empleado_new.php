<?php
session_start();
$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once "inc/db.php";

    $usuario = trim($_POST['usuario']);
    $nombre = trim($_POST['nombre']);
    $contrasenia = trim($_POST['contrasenia']);
    $repite = trim($_POST['repite']);

    if (strlen($usuario) < 3 || strlen($contrasenia) < 3) {
        $mensaje = '<div class="alert alert-danger">Usuario y contraseña deben tener al menos 3 caracteres.</div>';
    } elseif ($contrasenia !== $repite) {
        $mensaje = '<div class="alert alert-danger">Las contraseñas no coinciden.</div>';
    } else {
        $stmt = $conexion->prepare("SELECT COUNT(*) FROM empleados WHERE usuario = ?");
        $stmt->execute([$usuario]);
        if ($stmt->fetchColumn() > 0) {
            $mensaje = '<div class="alert alert-danger">Ese nombre de usuario ya está en uso.</div>';
        } else {
            $hash = password_hash($contrasenia, PASSWORD_DEFAULT);
            $stmt = $conexion->prepare("INSERT INTO empleados (usuario, contrasenia, nombre) VALUES (?, ?, ?)");
            $stmt->execute([$usuario, $hash, $nombre]);

            $mensaje = '<div class="alert alert-success">Cuenta creada correctamente. <a href="login.php">Ingresar</a></div>';
        }
    }
}

$deshabilitar = strpos($mensaje, 'Cuenta creada correctamente') !== false ? 'disabled' : '';
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Crear cuenta empleado | Sistema de Turnos</title>
  <link rel="icon" href="images/sala.ico">

  <!-- AdminLTE & Plugins -->
  <link rel="stylesheet" href="adminlte/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="adminlte/plugins/icheck-bootstrap/icheck-bootstrap.min.css">
  <link rel="stylesheet" href="adminlte/dist/css/adminlte.min.css">

</head>
<body class="hold-transition login-page">

<div class="login-box">
  <div class="login-logo">
    <b>Sistema de Turnos</b>
  </div>

  <div class="card">
    <div class="card-body login-card-body">
      <p class="login-box-msg">Crear cuenta de empleado</p>

      <?= $mensaje ?>

      <form method="post">

        <div class="input-group mb-4">
          <input type="text" name="nombre" class="form-control" placeholder="Nombre completo" <?= $deshabilitar ?> required>
          <div class="input-group-append">
            <div class="input-group-text"><span class="fas fa-user"></span></div>
          </div>
        </div>

        <div class="input-group mb-4">
          <input type="text" name="usuario" class="form-control" placeholder="Usuario" <?= $deshabilitar ?> required>
          <div class="input-group-append">
            <div class="input-group-text"><span class="fas fa-user-circle"></span></div>
          </div>
        </div>

        <div class="input-group mb-4">
          <input type="password" name="contrasenia" class="form-control" placeholder="Contraseña" <?= $deshabilitar ?> required>
          <div class="input-group-append">
            <div class="input-group-text"><span class="fas fa-lock"></span></div>
          </div>
        </div>

        <div class="input-group mb-4">
          <input type="password" name="repite" class="form-control" placeholder="Repetir contraseña" <?= $deshabilitar ?> required>
          <div class="input-group-append">
            <div class="input-group-text"><span class="fas fa-lock"></span></div>
          </div>
        </div>

        <?php if (!$deshabilitar): ?>
        <div class="row mb-3">
          <div class="col-12">
            <button type="submit" class="btn btn-success btn-block">
              <i class="fas fa-user-plus me-1"></i> Crear Cuenta
            </button>
          </div>
        </div>
        <?php endif; ?>

        <div class="row">
          <div class="col-12">
            <a href="login.php" class="btn btn-secondary btn-block">Volver al Login</a>
          </div>
        </div>

      </form>

    </div>
  </div>

  <p class="text-center mt-3"><small>Sistema desarrollado por Carambia Mauricio</small></p>
</div>

<!-- JS -->
<script src="adminlte/plugins/jquery/jquery.min.js"></script>
<script src="adminlte/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="adminlte/dist/js/adminlte.min.js"></script>

</body>
</html>