<?php
require_once __DIR__ . '/../inc/db.php';
$lista_espera = $_POST['lista_espera'] ?? '';

/* =============================================
   ESPECIALIDADES
=============================================*/
$especialidades = [
    'Psicologia Adulto' => 'LISTA ESPERA PSICOLOGIA MAYORES',
    'Psicologia Menores' => 'LISTA ESPERA PSICOLOGIA MENORES',
    'Psicopedagogia'     => 'LISTA ESPERA PSICOPEDAGOGIA',
    'Fonoaudiologia'     => 'LISTA ESPERA FONOAUDIOLOGIA',
    'Kinesiologia'       => 'LISTA ESPERA KINESIOLOGIA'
];

/* =============================================
   OBTENER LISTA ESPERA
=============================================*/
function obtenerListaEspera(PDO $conexion, string $especialidad): array
{
    $sql = "SELECT *
            FROM lista_espera
            WHERE especialidad = :especialidad
            ORDER BY Id DESC";

    $stmt = $conexion->prepare($sql);
    $stmt->execute([
        ':especialidad' => $especialidad
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/* =============================================
   COLOR SEGÚN ESTADO
=============================================*/
function colorAsignado(?string $estado): string
{
    return match ($estado) {
        'Confirmo' => 'style="background-color:#d1f5be;"',
        'No Confirmo' => 'style="background-color:#f25c54;"',
        'Pendiente Confirmacion' => 'style="background-color:#fff3b0;"',
        default => ''
    };
}
?>

<div id="wrapper">

<!-- ================= SELECT ================= -->
<div class="normalheader small-header">
<div class="hpanel">
<div class="panel-body">

<form method="POST">

<label>LISTAS DE ESPERA</label>

<select class="form-control"
        name="lista_espera"
        onchange="this.form.submit()">

<option value="">Seleccionar</option>

<?php foreach ($especialidades as $key => $titulo): ?>
<option value="<?= $key ?>"
<?= ($lista_espera === $key) ? 'selected' : '' ?>>
<?= htmlspecialchars($key) ?>
</option>
<?php endforeach; ?>

</select>

</form>

</div>
</div>
</div>

<!-- ================= BOTON NUEVO ================= -->
<div class="normalheader small-header">
<div class="hpanel">
<div class="panel-body">

<h2>
Pacientes
<a href="./?seccion=lista_espera_new&nc=<?= $rand ?>"
class="btn btn-info">
<i class="fa fa-plus"></i>
</a>
</h2>

</div>
</div>
</div>

<?php if (!empty($lista_espera) && isset($especialidades[$lista_espera])): ?>

<?php $registros = obtenerListaEspera($conexion, $lista_espera); ?>

<div class="content animate-panel">
<div class="row">
<div class="col-lg-12">

<div class="hpanel">

<div class="panel-heading">
<?= $especialidades[$lista_espera]; ?>
</div>

<div class="panel-body">
<div class="table-responsive">

<table class="table table-striped table-bordered table-hover dataTables-example">

<thead>
<tr>
<th>Paciente</th>
<th>Documento</th>
<th>Disponibilidad</th>
<th>Horario</th>
<th>Celular</th>
<th>Edad</th>
<th>Observaciones</th>
<th>Profesional</th>
<th>Asignado</th>
<th>Acciones</th>
</tr>
</thead>

<tbody>

<?php foreach ($registros as $r): ?>

<tr <?= colorAsignado($r['asignado'] ?? null) ?>>

<td><?= htmlspecialchars($r['apellido'].' '.$r['nombre']) ?></td>
<td><?= htmlspecialchars($r['documento']) ?></td>
<td><?= htmlspecialchars($r['disponibilidad']) ?></td>
<td><?= htmlspecialchars($r['horario']) ?></td>
<td><?= htmlspecialchars($r['celular']) ?></td>
<td><?= htmlspecialchars($r['edad']) ?></td>
<td><?= htmlspecialchars($r['estudio']) ?></td>
<td><?= htmlspecialchars($r['profesional']) ?></td>
<td><?= htmlspecialchars($r['asignado']) ?></td>

<td>

<a href="./?seccion=lista_espera_edit&id=<?= $r['Id'] ?>&nc=<?= $rand ?>"
class="btn btn-success">
<i class="fa fa-pencil"></i>
</a>

<a href="./?seccion=lista_espera_delete&id=<?= $r['Id'] ?>&nc=<?= $rand ?>"
class="btn btn-danger">
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

<?php endif; ?>

</div>

    <!-- Page-Level Scripts -->
    <script>
    $(document).ready(function() {
        $('.dataTables-example').DataTable({
            "iDisplayLength": 25,
            "aLengthMenu": [
                [10, 25, 50, 100, 1000],
                [10, 25, 50, 100, 1000]
            ],
            "bSort": false,
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
                    customize: function(win) {
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