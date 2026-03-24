<?php
require_once 'inc/db.php';
$rand = random_int(1000, 9999);

// === Consulta usando PDO ===
$stmt = $conexion->query("SELECT * FROM obras_sociales");
$obras = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Main Wrapper -->
<div id="wrapper">
    <div class="normalheader transition animated fadeIn small-header">
        <div class="hpanel">
            <div class="panel-body">
                <h2>
                    Obras sociales 
                    <a href="./?seccion=obras_new&nc=<?= $rand ?>" class="btn btn-info">
                        <i class="fa fa-plus"></i>
                    </a>
                </h2>
            </div>
        </div>
    </div>

    <div class="content animate-panel">
        <div class="row">
            <div class="col-lg-12">
                <div class="hpanel">
                    <div class="panel-body">
                        <table class="table table-striped table-bordered table-hover dataTables-example">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($obras as $obra): ?>
                                <tr>
                                    <td><?= htmlspecialchars($obra['obra_social']) ?></td>
                                    <td>
                                        <a href="./?seccion=obras_edit&id=<?= $obra['Id'] ?>&nc=<?= $rand ?>" class="btn btn-success">
                                            <i class="fa fa-pencil"></i>
                                        </a>
                                        <a href="./?seccion=obras_delete&id=<?= $obra['Id'] ?>&nc=<?= $rand ?>" class="btn btn-danger" onclick="return confirm('¿Seguro que desea eliminar esta obra social?');">
                                            <i class="fa fa-trash-o"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- DataTables JS -->
<script>
$(document).ready(function() {
    $('.dataTables-example').DataTable({
        "iDisplayLength": 100,
        "aLengthMenu": [[10,25,50,100,1000],[10,25,50,100,1000]],
        dom: '<"html5buttons"B>lTfgitp',
        buttons: [
            { extend: 'excel', title: 'obras sociales' },
            { extend: 'pdf', title: 'obras sociales' },
            { extend: 'print', text: 'IMPRIMIR', customize: function(win){
                $(win.document.body).addClass('white-bg');
                $(win.document.body).css('font-size','10px');
                $(win.document.body).find('table').addClass('compact').css('font-size','inherit');
            }}
        ],
        "language": {
            "sProcessing": "Procesando...",
            "sLengthMenu": "Mostrar _MENU_ registros",
            "sZeroRecords": "No se encontraron resultados",
            "sEmptyTable": "Ningún dato disponible en esta tabla",
            "sInfo": "Mostrando registros del _START_ al _END_ de un total de _TOTAL_ registros",
            "sInfoEmpty": "Mostrando registros del 0 al 0 de un total de 0 registros",
            "sInfoFiltered": "(filtrado de un total de _MAX_ registros)",
            "sSearch": "Buscar:",
            "sLoadingRecords": "Cargando...",
            "oPaginate": {
                "sFirst": "Primero",
                "sLast": "Último",
                "sNext": "Siguiente",
                "sPrevious": "Anterior"
            },
            "oAria": {
                "sSortAscending": ": Activar para ordenar la columna de manera ascendente",
                "sSortDescending": ": Activar para ordenar la columna de manera descendente"
            }
        }
    });
});
</script>