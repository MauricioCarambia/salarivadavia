<?php
require_once __DIR__ . '/../inc/db.php'; // $pdo
$rand = rand(1000,9999);

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$cont = 0;
$asistencia = 0;
$asistencia_total = 0;

/* ===============================
   CONSULTA OPTIMIZADA PDO
=================================*/
$stmt = $pdo->prepare("
    SELECT 
        t.*,
        pa.documento,
        pa.Id AS pacienteId,
        pa.nombre AS pacienteNombre,
        pa.apellido AS pacienteApellido,
        pr.Id AS profesionalId,
        pr.nombre AS profesionalNombre,
        pr.apellido AS profesionalApellido
    FROM turnos t
    LEFT JOIN pacientes pa ON pa.Id = t.paciente_id
    LEFT JOIN profesionales pr ON pr.Id = t.profesional_id
    WHERE t.paciente_id = :id
    ORDER BY t.fecha DESC
    LIMIT 25
");

$stmt->execute([':id' => $id]);
$turnos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Main Wrapper -->
<div id="wrapper">
    <div class="normalheader transition animated fadeIn small-header">
        <div class="hpanel">
            <div class="panel-body">
                <h3>Ver turno</h3>
            </div>
        </div>
    </div>

<div class="row">
<div class="col-lg-12">
<div class="hpanel">
<div class="panel-body">
<div class="table-responsive">

<table class="table table-striped table-bordered table-hover dataTables-example dataTable">
<thead>
<tr>
<th>#</th>
<th>Paciente</th>
<th>Documento</th>
<th>Profesional</th>
<th>Horario</th>
<th>Sobreturno</th>
<th>Asistio</th>
</tr>
</thead>

<tbody>

<?php foreach ($turnos as $rArray): 

    $fecha_turno = strtotime($rArray['fecha']);
    $fecha_actual = strtotime(date('Y-m-d'));

    $fondo_color = ($fecha_turno < $fecha_actual)
        ? '#FF9898'
        : '#d1f5be';

    $cont++;
    $asistencia_total++;

?>

<tr style="background-color: <?= $fondo_color ?>;color:black;">
<td><?= $cont ?></td>

<td>
<?= htmlspecialchars($rArray['pacienteApellido'].' '.$rArray['pacienteNombre']) ?>
</td>

<td><?= htmlspecialchars($rArray['documento']) ?></td>

<td>
<?= htmlspecialchars($rArray['profesionalApellido'].' '.$rArray['profesionalNombre']) ?>
</td>

<td>
<?= date('H:i \h\s - d/m/Y', strtotime($rArray['fecha'])) ?>
</td>

<td><?= $rArray['sobreturno'] ? 'Sí' : 'No' ?></td>

<td>
<?php
if ($rArray['asistio']) {
    echo 'Sí';
    $asistencia++;
} else {
    echo 'No';
}
?>
</td>

</tr>

<?php endforeach; ?>

</tbody>
</table>

<a href="./?seccion=pacientes&nc=<?= $rand ?>"
class="btn btn-danger pull-right m-t-md">Volver</a>

<p>
<?= 'Total de turnos que asistio: '.$asistencia ?><br>
<?= 'De un total de: '.$asistencia_total ?>
</p>

</div>
</div>
</div>
</div>
</div>

<!-- Page-Level Scripts -->
<script>
$(document).ready(function () {

$('.dataTables-example').DataTable({
    iDisplayLength: 50,
    aLengthMenu: [[10,25,50,100,1000],[10,25,50,100,1000]],
    bSort:false,
    dom:'<"html5buttons"B>lTfgitp',
    buttons:[
        {extend:'excel',title:'pacientes'},
        {extend:'pdf',title:'pacientes'},
        {
            extend:'print',
            text:'IMPRIMIR',
            customize:function(win){
                $(win.document.body).addClass('white-bg');
                $(win.document.body).css('font-size','10px');
                $(win.document.body).find('table')
                    .addClass('compact')
                    .css('font-size','inherit');
            }
        }
    ],
    language:{
        sProcessing:"Procesando...",
        sLengthMenu:"Mostrar _MENU_ registros",
        sZeroRecords:"No se encontraron resultados",
        sEmptyTable:"Ningún dato disponible",
        sInfo:"Mostrando _START_ a _END_ de _TOTAL_",
        sSearch:"Buscar:",
        oPaginate:{
            sFirst:"Primero",
            sLast:"Último",
            sNext:"Siguiente",
            sPrevious:"Anterior"
        }
    }
});

});
</script>