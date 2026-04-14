<?php
date_default_timezone_set('America/Argentina/Buenos_Aires');

session_name("turnos");
session_start();

require_once("inc/db.php");

// 🔥 sesión simple (sin redirección)
if (!isset($_SESSION["login"]) || $_SESSION["login"] != 'si') {
    exit("Sesión expirada");
}

$seccion = $_GET['seccion'] ?? '';

if ($seccion == '') {
    exit("Sección no definida");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Modal</title>

<!-- ADMIN LTE MINIMO -->
<link rel="stylesheet" href="adminlte/plugins/fontawesome-free/css/all.min.css">
<link rel="stylesheet" href="adminlte/dist/css/adminlte.min.css">

<!-- jQuery -->
<script src="adminlte/plugins/jquery/jquery.min.js"></script>

<!-- Bootstrap -->
<script src="adminlte/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>

<!-- SweetAlert -->
<script src="adminlte/plugins/sweetalert2/sweetalert2.all.min.js"></script>

</head>

<body class="hold-transition" id="body">

<div class="content-wrapper" style="margin-left:0; min-height:100vh;">

    <section class="content p-3">

        <?php
        $archivo = "secciones/" . basename($seccion) . ".php";

        if (file_exists($archivo)) {
            include $archivo;
        } else {
            echo "Sección inexistente";
        }
        ?>

    </section>

</div>
<script>
document.addEventListener("DOMContentLoaded", function () {

    const theme = localStorage.getItem("theme") || "dark";
    const body = document.getElementById("body");

    if (theme === "dark") {
        body.classList.add("dark-mode");
    } else {
        body.classList.remove("dark-mode");
    }

});
window.addEventListener("storage", function(e) {
    if (e.key === "theme") {
        location.reload();
    }
});
</script>
</body>
</html>