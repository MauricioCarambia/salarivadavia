<?php
session_name("turnos");
session_start();

require_once "inc/db.php";

/* =============================
   VALIDAR LOGIN
=============================*/
if (!isset($_SESSION["login"]) || $_SESSION["login"] !== 'si') {

    // Guardar a dónde quiso entrar (opcional PRO)
    $_SESSION["redir"] = $_SERVER['REQUEST_URI'];

    header("Location: login.php");
    exit;
}
require_once "inc/db.php";

$seccion = $_GET['seccion'] ?? "home";
$rand = rand(1, 9999);

$dias = ["Domingo", "Lunes", "Martes", "Miércoles", "Jueves", "Viernes", "Sábado"];

$meses = [
    "Enero",
    "Febrero",
    "Marzo",
    "Abril",
    "Mayo",
    "Junio",
    "Julio",
    "Agosto",
    "Septiembre",
    "Octubre",
    "Noviembre",
    "Diciembre"
];
?>
<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/x-icon" href="images/sala.ico">

    <title>Sistema de Turnos</title>

    <link rel="stylesheet" href="adminlte/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="adminlte/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="vendor/sweetalert/sweetalert2.min.css">
    <script src="adminlte/plugins/jquery/jquery.min.js"></script>
    <link rel="stylesheet" href="vendor/datatables/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="vendor/datatables/buttons.bootstrap4.min.css">
    <link rel="stylesheet" href="vendor/datatables/jquery.dataTables.min.css">
    <link rel="stylesheet" href="vendor/datatables/buttons.dataTables.min.css">
    <link rel="stylesheet" href="styles/style.css">
    
</head>

<body class="hold-transition sidebar-mini layout-fixed layout-navbar-fixed">

    <div class="wrapper">

        <!-- NAVBAR -->

        <nav class="main-header navbar navbar-expand navbar-white navbar-light" id="topNavbar">

            <ul class="navbar-nav">

                <li class="nav-item">
                    <a class="nav-link" data-widget="pushmenu" href="#">
                        <i class="fas fa-bars"></i>
                    </a>
                </li>

            </ul>
            <ul class="navbar-nav ml-auto ">
                <li class="nav-item">
                    <?= $dias[date('w')] . " " . date('d') . " de " . $meses[date('n') - 1] . " de " . date('Y'); ?>
                </li>
            </ul>
            <ul class="navbar-nav ml-auto align-items-center">

                <li class="nav-item">
                    <span id="userNombre" class="nav-link mb-0">
                        <i class="fas fa-user mr-1"></i>
                        <?php echo $_SESSION['user_nombre'] ?? 'Usuario'; ?>
                    </span>
                </li>
                <li class="nav-item">
                    <a href="javascript:void(0)" class="nav-link" onclick="toggleTheme()">
                        <i id="themeIcon" class="fas fa-moon"></i>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="./secciones/logout.php">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </li>



            </ul>

        </nav>

        <!-- SIDEBAR -->

        <aside class="main-sidebar sidebar-dark-info elevation-4">

            <!-- TITULO -->
            <div class="text-center py-3" style="border-bottom: 1px solid rgba(255,255,255,0.1);">
                <span id="tituloSidebar" style="font-size:16px; font-weight:bold;">
                    Sala Bernardino Rivadavia
                </span>
            </div>

            <!-- LOGO -->
            <a href="./" class="brand-link d-flex justify-content-center align-items-center"
                style="background: transparent; height:110px; position:relative;">

                <img src="images/logo_negro.png" id="logoDark"
                    style="max-width:70%; max-height:100%; object-fit:contain; position:absolute; opacity:1;">

                <img src="images/logo_blanco.png" id="logoLight"
                    style="max-width:70%; max-height:100%; object-fit:contain; position:absolute; opacity:0;">

            </a>

            <div class="sidebar">

                <nav class="mt-2">
                    <?php include "secciones/menu.php"; ?>
                </nav>

            </div>

        </aside>

        <!-- CONTENIDO -->

        <div class="content-wrapper">

            <section class="content pt-3">

                <div class="container-fluid">

                    <?php

                    $archivo = "secciones/" . basename($seccion) . ".php";

                    if (file_exists($archivo)) {
                        include $archivo;
                    } else {
                        include "secciones/home.php";
                    }

                    ?>

                </div>

            </section>

        </div>

        <!-- FOOTER -->

        <footer class="main-footer text-center">
            <strong>Sistema de Turnos - Desarrollado por Carambia Mauricio</strong>
        </footer>

    </div>

    <!-- JS -->
    <script src="adminlte/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="adminlte/dist/js/adminlte.min.js"></script>
    <script src="fullcalendar/index.global.min.js"></script>
    <script src="vendor/sweetalert/sweetalert2.min.js"></script>
    <!-- DataTables -->
    <script src="vendor/datatables/jquery.dataTables.min.js"></script>
    <script src="vendor/datatables/dataTables.bootstrap4.min.js"></script>

    <!-- Buttons -->
    <script src="vendor/datatables/dataTables.buttons.min.js"></script>
    <script src="vendor/datatables/buttons.bootstrap4.min.js"></script>

    <!-- Dependencias -->
    <script src="vendor/datatables/jszip.min.js"></script>
    <script src="vendor/datatables/pdfmake.min.js"></script>
    <script src="vendor/datatables/vfs_fonts.js"></script>

    <!-- Botones -->
    <script src="vendor/datatables/buttons.html5.min.js"></script>
    <script src="vendor/datatables/buttons.print.min.js"></script>
    <script>
        function applyTheme(theme) {

            const sidebar = document.querySelector('.main-sidebar');
            const body = document.body;
            const icon = document.getElementById('themeIcon');

            if (!sidebar) return;

            if (theme === "dark") {

                // DARK MODE
                sidebar.classList.remove('sidebar-light-info');
                sidebar.classList.add('sidebar-dark-info');

                body.classList.add('dark-mode');

                icon.classList.remove('fa-moon');
                icon.classList.add('fa-sun');

            } else {

                // LIGHT MODE
                sidebar.classList.remove('sidebar-dark-info');
                sidebar.classList.add('sidebar-light-info');

                body.classList.remove('dark-mode');

                icon.classList.remove('fa-sun');
                icon.classList.add('fa-moon');
            }

            updateLogo();
            updateUserColor();
            updateNavbar();

            localStorage.setItem("theme", theme);
        }

        function toggleTheme() {

            const current = localStorage.getItem("theme") || "dark";
            const newTheme = current === "dark" ? "light" : "dark";

            applyTheme(newTheme);
        }

        function updateLogo() {

            const sidebar = document.querySelector('.main-sidebar');
            const logoDark = document.getElementById('logoDark');
            const logoLight = document.getElementById('logoLight');

            if (!sidebar || !logoDark || !logoLight) return;

            const isDark = sidebar.classList.contains('sidebar-dark-info');

            logoDark.style.opacity = isDark ? '1' : '0';
            logoLight.style.opacity = isDark ? '0' : '1';
        }

        function updateUserColor() {

            const sidebar = document.querySelector('.main-sidebar');
            const user = document.getElementById('userNombre');

            if (!sidebar || !user) return;

            user.style.color = sidebar.classList.contains('sidebar-dark-info')
                ? '#ffffff'
                : '#000000';
        }

        function updateNavbar() {

            const navbar = document.getElementById('topNavbar');

            if (!navbar) return;

            if (document.body.classList.contains('dark-mode')) {

                navbar.classList.remove('navbar-white', 'navbar-light');
                navbar.classList.add('navbar-dark', 'bg-dark');

            } else {

                navbar.classList.remove('navbar-dark', 'bg-dark');
                navbar.classList.add('navbar-white', 'navbar-light');
            }
        }
        
        function initDataTable(selector, options = {}) {

            // 🔥 Si ya existe, la destruye
            if ($.fn.DataTable.isDataTable(selector)) {
                $(selector).DataTable().destroy();
            }

            const defaultConfig = {
                responsive: true,
                autoWidth: false,
                pageLength: 25,
                lengthMenu: [5, 10, 25, 50, 100],

                language: {
                    lengthMenu: "Mostrar _MENU_ registros",
                    zeroRecords: "No se encontraron resultados",
                    info: "Mostrando _START_ a _END_ de _TOTAL_ registros",
                    infoEmpty: "Mostrando 0 a 0 de 0 registros",
                    infoFiltered: "(filtrado de _MAX_ registros totales)",
                    search: "Buscar:",
                    paginate: {
                        first: "Primero",
                        last: "Último",
                        next: "→",
                        previous: "←"
                    }
                },
                dom:
                    "<'row mb-2'<'col-md-6 d-flex align-items-center'l><'col-md-6 d-flex justify-content-end'f>>" + // cantidad + buscador misma línea
                    "<'row mb-2'<'col-md-6 d-flex align-items-center'i><'col-md-6 d-flex justify-content-end'B>>" + // info + botones
                    "<'row'<'col-md-12'tr>>" +                                                                       // tabla
                    "<'row mt-2'<'col-md-12 d-flex justify-content-end'p>>",                                          // paginación derecha

                buttons: [
                    {
                        extend: 'excelHtml5',
                        text: '<i class="fas fa-file-excel"></i> Excel',
                        className: 'btn btn-success btn-sm mr-1 rounded'
                    },
                    {
                        extend: 'pdfHtml5',
                        text: '<i class="fas fa-file-pdf"></i> PDF',
                        className: 'btn btn-danger btn-sm mr-1 rounded'
                    },
                    {
                        extend: 'print',
                        text: '<i class="fas fa-print"></i> Imprimir',
                        className: 'btn btn-info btn-sm rounded'
                    }
                ]
            };

            const config = $.extend(true, {}, defaultConfig, options);

            return $(selector).DataTable(config);
        }
        document.addEventListener("DOMContentLoaded", function () {

            const savedTheme = localStorage.getItem("theme") || "dark";

            applyTheme(savedTheme);
        });
    </script>

</body>

</html>