<?php
date_default_timezone_set('America/Argentina/Buenos_Aires');
session_set_cookie_params(0, '/');
session_name("turnos");
session_cache_limiter("private");
session_start();
require_once "inc/db.php";

$rand = mt_rand();
$mensaje = '';

if (!empty($_POST['usuario']) && !empty($_POST['contrasenia'])) {
    $usuario = trim($_POST['usuario']);
    $contrasenia = trim($_POST['contrasenia']);

    if (strlen($usuario) < 3 || strlen($contrasenia) < 3) {
        $mensaje = '<div class="alert alert-warning">El usuario y la contraseña deben tener al menos 3 caracteres.</div>';
    } else {

        // ----------------------------
        // USUARIOS FIJOS (ADMIN)
        // ----------------------------
        $usuarios_fijos = [
            'administrador' => ['pass' => 'turnoSs', 'tipo' => 'admin', 'nombre' => 'Administrador']
        ];

        if (isset($usuarios_fijos[$usuario]) && $contrasenia === $usuarios_fijos[$usuario]['pass']) {
            // Login admin
            $_SESSION["login"] = 'si';
            $_SESSION["tipo"] = $usuarios_fijos[$usuario]['tipo'];
            $_SESSION["user_nombre"] = $usuarios_fijos[$usuario]['nombre'];

        } else {
            // ----------------------------
            // EMPLEADOS
            // ----------------------------
            $stmt = $conexion->prepare("SELECT Id, usuario, contrasenia, rol_id FROM empleado WHERE usuario = ?");
            $stmt->execute([$usuario]);
            $empleado = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($empleado && password_verify($contrasenia, $empleado['contrasenia'])) {
                $_SESSION["login"] = 'si';
                $_SESSION["tipo"] = 'empleado';
                $_SESSION["user_nombre"] = $empleado['usuario'];
                $_SESSION["user_id"] = $empleado['Id'];
                $_SESSION["rol_id"] = $empleado['rol_id'];

                // Cargar accesos
                if ($_SESSION["rol_id"]) {
                    $stmtAccesos = $conexion->prepare("SELECT acceso_id FROM roles_accesos WHERE rol_id = ?");
                    $stmtAccesos->execute([$_SESSION["rol_id"]]);
                    $_SESSION["accesos"] = array_column($stmtAccesos->fetchAll(PDO::FETCH_ASSOC), 'acceso_id');
                } else {
                    $_SESSION["accesos"] = [];
                }

            } else {
                // ----------------------------
                // PROFESIONALES
                // ----------------------------
                $stmt = $conexion->prepare("SELECT Id, nombre, apellido, contrasenia FROM profesionales WHERE usuario = ?");
                $stmt->execute([$usuario]);
                $profesional = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($profesional && $contrasenia === $profesional['contrasenia']) {
                    $_SESSION["login"] = 'si';
                    $_SESSION["tipo"] = 'profesional';
                    $_SESSION["user_nombre"] = $profesional['nombre'] . ' ' . $profesional['apellido'];
                    $_SESSION["user_id"] = $profesional['Id'];
                } else {
                    // Usuario o contraseña incorrectos
                    $mensaje = '<div class="alert alert-danger">Usuario o contraseña incorrectos.</div>';
                }
            }
        }

        // Redirección si logueó
        if (isset($_SESSION["login"]) && $_SESSION["login"] === 'si') {
            $redir = !empty($_SESSION["redir"]) ? $_SESSION["redir"] : './';
            $_SESSION["redir"] = '';
            header("Location: " . $redir . '?nc=' . $rand);
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <title>Login | Sistema de Turnos</title>
  <link rel="icon" href="images/sala.ico">

  <!-- AdminLTE -->
  <link rel="stylesheet" href="adminlte/plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="adminlte/plugins/icheck-bootstrap/icheck-bootstrap.min.css">
  <link rel="stylesheet" href="adminlte/dist/css/adminlte.min.css">

 

</head>

<body class="hold-transition login-page">

  <div class="login-box">

    <!-- LOGO -->
    <div class="login-logo">
      <b>Sistema de Turnos</b>
    </div>

    <!-- CARD -->
    <div class="card shadow-lg">
      <div class="card-body login-card-body">

        <p class="login-box-msg">Ingresar al sistema</p>

        <?= $mensaje ?>

        <form method="post">

          <div class="input-group mb-3">
            <input type="text" name="usuario" class="form-control" placeholder="Usuario" required>
            <div class="input-group-append">
              <div class="input-group-text">
                <span class="fas fa-user"></span>
              </div>
            </div>
          </div>

          <div class="input-group mb-3">
            <input type="password" name="contrasenia" class="form-control" placeholder="Contraseña" required>
            <div class="input-group-append">
              <div class="input-group-text">
                <span class="fas fa-lock"></span>
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-12">
              <button type="submit" class="btn btn-success btn-block">
                <i class="fas fa-sign-in-alt me-2"></i> Ingresar
              </button>
            </div>
          </div>

        </form>

        <hr>

        <a href="empleado_new.php" class="btn btn-primary btn-block">
          <i class="fas fa-user-plus"></i> Crear cuenta
        </a>

      </div>
    </div>

    <p class="text-center mt-3">
      <small>Sistema desarrollado por Carambia Mauricio</small>
    </p>

  </div>

  <!-- JS -->
  <script src="adminlte/plugins/jquery/jquery.min.js"></script>
  <script src="adminlte/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="adminlte/dist/js/adminlte.min.js"></script>

</body>

</html>