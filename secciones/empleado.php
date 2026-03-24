<?php
$mensaje = '';

$empleados = $conexion->query("SELECT e.id, e.usuario, r.nombre AS rol 
                                               FROM empleado e 
                                               LEFT JOIN roles r ON e.rol_id = r.id
                                               ORDER BY e.usuario ASC")->fetchAll(PDO::FETCH_ASSOC);

$consulta = "
SELECT r.id, r.nombre AS rol_nombre,
GROUP_CONCAT(a.nombre ORDER BY a.nombre SEPARATOR ', ') AS accesos
FROM roles r
LEFT JOIN roles_accesos ra ON r.id = ra.rol_id
LEFT JOIN accesos a ON ra.acceso_id = a.id
GROUP BY r.id, r.nombre
ORDER BY r.nombre ASC";

$roles_con_accesos = $conexion->query($consulta)->fetchAll(PDO::FETCH_ASSOC);
if (isset($_GET['v']) && $_GET['v'] === 'ok'): ?>
    <script>
        toastr.options = {
            "closeButton": true,
            "debug": false,
            "newestOnTop": false,
            "progressBar": true,
            "positionClass": "toast-top-right",
            "preventDuplicates": true,
            "onclick": null,
            "showDuration": "300",
            "hideDuration": "1000",
            "timeOut": "5000",
            "extendedTimeOut": "1000",
            "showEasing": "swing",
            "hideEasing": "linear",
            "showMethod": "fadeIn",
            "hideMethod": "fadeOut"
        };
        toastr.success("Los cambios se guardaron correctamente.", "¡Éxito!");
    </script>
<?php endif;
?>

<div id="wrapper">
    <div class="normalheader transition animated fadeIn small-header">
        <div class="hpanel">
            <div class="panel-body">
                <h2>
                    Crear rol <a href="./?seccion=rol_new&nc=<?php echo $rand; ?>" class="btn btn-info"><i
                            class="fa fa-plus"></i></a>
                </h2>
            </div>
        </div>
    </div>
    <!-- Fila 2: Administración de Empleados -->
    <div class="content animate-panel">
        <div class="row">
            <div class="col-lg-6">
                <div class="hpanel">
                    <div class="panel-body ">
                        <div class="table-responsive">
                            <h3>Lista de empleados</h3>

                            <table class="table table-striped table-bordered table-hover dataTables-example dataTable"
                                id="DataTables_Table_0" aria-describedby="DataTables_Table_0_info" role="grid">
                                <thead>
                                    <tr>
                                        <th>Usuario</th>
                                        <th>Rol</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($empleados as $emp): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($emp['usuario']) ?></td>
                                            <td><?= htmlspecialchars($emp['rol']) ?: 'Sin rol' ?></td>
                                            <td>
                                                <a href="?seccion=empleado_contrasenia&id=<?= $emp['id'] ?>"
                                                    class="btn btn-warning btn-sm">Contraseña <i
                                                        class="fa fa-pencil"></i></a>
                                                <a href="?seccion=empleado_rol_edit&id=<?= $emp['id'] ?>"
                                                    class="btn btn-info btn-sm">Rol <i class="fa fa-pencil"></i></a>
                                                <a href="?seccion=empleado_delete&id=<?= $emp['id'] ?>"
                                                    class="btn btn-danger btn-sm"><i class="fa fa-trash"></i></a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($empleados)): ?>
                                        <tr>
                                            <td colspan="3" class="text-center">No hay empleados registrados.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="hpanel">
                    <div class="panel-body ">
                        <div class="table-responsive">
                            <h3>Lista de roles</h3>
                            <table class="table table-striped table-bordered table-hover dataTables-example dataTable"
                                id="DataTables_Table_0" aria-describedby="DataTables_Table_0_info" role="grid">
                                <thead>
                                    <tr>
                                        <th>Rol</th>
                                        <th>Accesos</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($roles_con_accesos as $rol): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($rol['rol_nombre']) ?></td>
                                            <td><?= htmlspecialchars($rol['accesos']) ?: 'Sin accesos' ?></td>
                                            <td>
                                                <a href="?seccion=rol_edit&id=<?= $rol['id'] ?>"
                                                    class="btn btn-sm btn-info"><i class="fa fa-pencil"></i></a>
                                                <a href="?seccion=rol_delete&id=<?= $rol['id'] ?>"
                                                    class="btn btn-sm btn-danger"><i class="fa fa-trash"></i></a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($roles_con_accesos)): ?>
                                        <tr>
                                            <td colspan="3" class="text-center">No hay roles registrados.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>



<!-- Page-Level Scripts -->
<script>
    $(document).ready(function () {
        $('.dataTables-example').DataTable({
            "iDisplayLength": 100,
            "aLengthMenu": [
                [10, 25, 50, 100, 1000],
                [10, 25, 50, 100, 1000]
            ],
            dom: '<"html5buttons"B>lTfgitp',
            buttons: [{
                extend: 'excel',
                title: 'profesionales'
            },
            {
                extend: 'pdf',
                title: 'profesionales'
            },
            {
                extend: 'print',
                text: 'IMPRIMIR',
                customize: function (win) {
                    $(win.document.body).addClass('white-bg');
                    $(win.document.body).css('font-size', '10px');

                    $(win.document.body).find('table')
                        .addClass('compact')
                        .css('font-size', 'inherit');
                }
            }
            ],
            "language": {
                "sProcessing": "Procesando...",
                "sLengthMenu": "Mostrar _MENU_ registros",
                "sZeroRecords": "No se encontraron resultados",
                "sEmptyTable": "Ning&uacute;n dato disponible en esta tabla",
                "sInfo": "Mostrando registros del _START_ al _END_ de un total de _TOTAL_ registros",
                "sInfoEmpty": "Mostrando registros del 0 al 0 de un total de 0 registros",
                "sInfoFiltered": "(filtrado de un total de _MAX_ registros)",
                "sInfoPostFix": "",
                "sSearch": "Buscar:",
                "sUrl": "",
                "sInfoThousands": ",",
                "sLoadingRecords": "Cargando...",
                "oPaginate": {
                    "sFirst": "Primero",
                    "sLast": "&Uacute;ltimo",
                    "sNext": "Siguiente",
                    "sPrevious": "Anterior"
                },
                "oAria": {
                    "sSortAscending": ": Activar para ordenar la columna de manera ascendente",
                    "sSortDescending": ": Activar para ordenar la columna de manera descendente"
                }
            }

        });

        /* Init DataTables */
        var oTable = $('#editable').DataTable();

    });
</script>