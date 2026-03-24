<?php
require_once 'inc/db.php';

$id = $_GET['id'] ?? 0;
$rand = random_int(1000, 9999);

// Array de meses en español
$meses = [
    'Enero','Febrero','Marzo','Abril','Mayo','Junio',
    'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'
];

// // === Consulta con PDO ===
// $sql = "SELECT 
//             p.Id,
//             p.paciente_id,
//             pa.nombre,
//             pa.apellido,
//             p.fecha_pago,
//             p.monto,
//             p.fecha_correspondiente
//         FROM pagos_afiliados p
//         LEFT JOIN pacientes pa ON pa.Id = p.paciente_id
//         WHERE p.afiliado_id = :id
//         ORDER BY p.fecha_correspondiente ASC";

// $stmt = $conexion->prepare($sql);
// $stmt->execute(['id' => $id]);
// $pagos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Main Wrapper -->
<div id="wrapper">
    <div class="normalheader transition animated fadeIn small-header">
        <div class="hpanel">
            <div class="panel-body">
                <h2>Pagos de Socios</h2>
            </div>
        </div>
    </div>

    <div class="content animate-panel">
        <div class="row">
            <div class="col-md-12">
                <div class="hpanel">
                    <div class="panel-heading hbuilt">
                        <h3>Pagos realizados por los socios</h3>
                    </div>
                    <div class="panel-body">
                        <table class="table table-striped table-bordered table-hover dataTables-example">
                            <thead>
                                <tr>
                                    <th>Fecha correspondiente</th>
                                    <th>Pago correspondiente a</th>
                                    <th>Paciente</th>
                                    <th>Fecha de pago</th>
                                    <th>Monto</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($pagos as $pago): 
                                    $fecha_corr = new DateTime($pago['fecha_correspondiente']);
                                    $mes = $meses[(int)$fecha_corr->format('m') - 1];
                                    $anio = $fecha_corr->format('Y');
                                    $fecha_pago = !empty($pago['fecha_pago']) ? date('d/m/Y', strtotime($pago['fecha_pago'])) : '';
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($pago['fecha_correspondiente']) ?></td>
                                    <td><?= $mes ?> de <?= $anio ?></td>
                                    <td><?= htmlspecialchars($pago['apellido'] . ' ' . $pago['nombre']) ?></td>
                                    <td><?= $fecha_pago ?></td>
                                    <td>$<?= number_format($pago['monto'],2) ?></td>
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
        columnDefs: [
            { type: 'date-eu', targets: 0 },
            { targets: 0, visible: false }
        ],
        aaSorting: [[0, "asc"]],
        iDisplayLength: 100,
        aLengthMenu: [[10,25,50,100,1000],[10,25,50,100,1000]],
        dom: '<"html5buttons"B>lTfgitp',
        buttons: [
            { extend: 'excel', title: 'pagos' },
            { extend: 'pdf', title: 'pagos' },
            { extend: 'print', text: 'IMPRIMIR', customize: function(win){
                $(win.document.body).addClass('white-bg');
                $(win.document.body).css('font-size','10px');
                $(win.document.body).find('table').addClass('compact').css('font-size','inherit');
            }}
        ],
        language: {
            sProcessing: "Procesando...",
            sLengthMenu: "Mostrar _MENU_ registros",
            sZeroRecords: "No se encontraron resultados",
            sEmptyTable: "Ningún dato disponible en esta tabla",
            sInfo: "Mostrando registros del _START_ al _END_ de un total de _TOTAL_ registros",
            sInfoEmpty: "Mostrando registros del 0 al 0 de un total de 0 registros",
            sInfoFiltered: "(filtrado de un total de _MAX_ registros)",
            sSearch: "Buscar:",
            sLoadingRecords: "Cargando...",
            oPaginate: { sFirst: "Primero", sLast: "Último", sNext: "Siguiente", sPrevious: "Anterior" },
            oAria: { sSortAscending: ": Activar para ordenar la columna de manera ascendente", sSortDescending: ": Activar para ordenar la columna de manera descendente" }
        }
    });

    // Configuración de ordenamiento de fechas europeas
    jQuery.extend(jQuery.fn.dataTableExt.oSort, {
        "date-eu-pre": function(date) {
            if (!date) return 0;
            var eu_date = date.replace(/\s+/g,'').split(/[\.\-\/]/);
            var day = (eu_date[0].length==1?'0'+eu_date[0]:eu_date[0]);
            var month = (eu_date[1].length==1?'0'+eu_date[1]:eu_date[1]);
            var year = eu_date[2] || 0;
            return (year+month+day)*1;
        },
        "date-eu-asc": function(a,b){return ((a<b)?-1:((a>b)?1:0));},
        "date-eu-desc": function(a,b){return ((a<b)?1:((a>b)?-1:0));}
    });
});
</script>