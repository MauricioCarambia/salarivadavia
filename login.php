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

    // =============================
// BUSCAR EMPLEADO
// =============================
    $stmt = $conexion->prepare("
    SELECT e.Id, e.usuario, e.contrasenia, e.nombre, e.rol_id, e.activo, r.nombre AS rol_nombre
    FROM empleados e
    LEFT JOIN roles r ON e.rol_id = r.id
    WHERE e.usuario = ?
    LIMIT 1
");
    $stmt->execute([$usuario]);
    $empleado = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($empleado) {

      // 👉 EXISTE COMO EMPLEADO → validar contraseña
      if (!password_verify($contrasenia, $empleado['contrasenia'])) {
        $mensaje = '<div class="alert alert-danger">Contraseña incorrecta.</div>';
      } elseif (!$empleado['activo']) {
        $mensaje = '<div class="alert alert-danger">Usuario inactivo</div>';
      } else {

        session_regenerate_id(true);
        $_SESSION = [];

        $_SESSION["login"] = 'si';
        $_SESSION["tipo"] = 'empleado';
        $_SESSION["user_id"] = $empleado['Id'];
        $_SESSION["user_nombre"] = $empleado['usuario'];
        $_SESSION["nombre_completo"] = $empleado['nombre'];
        $_SESSION["rol_id"] = $empleado['rol_id'];
        $_SESSION["rol_nombre"] = $empleado['rol_nombre'];

        $_SESSION['es_admin'] = (
          strtolower(trim($empleado['rol_nombre'])) === 'administrador'
        );

        if ($_SESSION['es_admin']) {
          $_SESSION['accesos'] = ['*'];
        } else {

          $stmtAccesos = $conexion->prepare("
                SELECT a.nombre
                FROM roles_accesos ra
                INNER JOIN accesos a ON a.id = ra.acceso_id
                WHERE ra.rol_id = ?
            ");
          $stmtAccesos->execute([$empleado['rol_id']]);

          $_SESSION['accesos'] = array_column(
            $stmtAccesos->fetchAll(PDO::FETCH_ASSOC),
            'nombre'
          );
        }

        header("Location: index.php");
        exit;
      }

    } else {

      // =============================
      // BUSCAR PROFESIONAL
      // =============================
      $stmt = $conexion->prepare("
        SELECT Id, nombre, apellido, usuario, contrasenia
        FROM profesionales
        WHERE usuario = ?
        LIMIT 1
    ");
      $stmt->execute([$usuario]);
      $profesional = $stmt->fetch(PDO::FETCH_ASSOC);

      if ($profesional && $contrasenia === $profesional['contrasenia']){

        session_regenerate_id(true);
        $_SESSION = [];

        $_SESSION["login"] = 'si';
        $_SESSION["tipo"] = 'profesional';
        $_SESSION["user_id"] = $profesional['Id'];
        $_SESSION["nombre_completo"] = $profesional['nombre'] . ' ' . $profesional['apellido'];

        $_SESSION["accesos"] = [
          'historia_pacientes',
          'turnos_profesional',
          'salir'
        ];

        $_SESSION["es_admin"] = false;

        header("Location: index.php");
        exit;

      } else {
        $mensaje = '<div class="alert alert-danger">Usuario o contraseña incorrectos.</div>';
      }
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