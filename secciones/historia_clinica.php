<?php
require_once __DIR__ . '/../inc/db.php'; // $pdo
session_start();

// Inicializar variables
$mensaje = '';
$profesionalId = $_SESSION['user_id'] ?? 0;
$tipoUsuario   = $_SESSION['tipo'] ?? '';
$rand          = rand(1000,9999); // Para links únicos
$id            = (int)($_GET['id'] ?? 0);

if (!$id) die('<div class="alert alert-danger">Paciente inválido</div>');

// Obtener paciente
$stmt = $pdo->prepare("SELECT * FROM pacientes WHERE Id = :id");
$stmt->execute([':id' => $id]);
$rArray = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$rArray) die('<div class="alert alert-danger">Paciente no encontrado</div>');

// Datos del paciente
$paciente         = $rArray['apellido'].' '.$rArray['nombre'];
$pacienteId       = $rArray['Id'];
$celular          = $rArray['celular'];
$documento        = $rArray['documento'];
$fecha            = $rArray['nacimiento'];
$historia_clinica = $rArray['historia_clinica'];

// Obtener historias clínicas del paciente
$stmtHC = $pdo->prepare("
    SELECT hc.*, 
           p.firma AS profesionalfirma, 
           p.apellido AS profesionalapellido, 
           p.nombre AS profesionalnombre,
           p.matricula_nacional, 
           p.matricula_provincial, 
           e.especialidad
    FROM historias_clinicas hc
    LEFT JOIN profesionales p ON hc.profesional_id = p.Id
    LEFT JOIN especialidades e ON p.especialidad_id = e.Id
    WHERE hc.paciente_id = :paciente_id
    ORDER BY hc.fecha DESC, hc.Id DESC
");
$stmtHC->execute([':paciente_id' => $id]);
$historias = $stmtHC->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Main Wrapper -->
<div id="wrapper">
    <div class="normalheader transition animated fadeIn small-header">
        <div class="hpanel">
            <div class="panel-body">
                <h2>Historia clínica</h2>
            </div>
        </div>
    </div>

    <?php echo $mensaje; ?>

    <div class="content animate-panel">
        <!-- Detalle del paciente -->
        <div class="row">
            <div class="col-lg-12">
                <div class="hpanel">
                    <div class="panel-heading hbuilt">
                        Detalle del paciente:
                        <div class="clearfix"></div>
                    </div>
                    <div class="panel-body">
                        <label>Paciente:</label> <?= htmlspecialchars($paciente) ?><br>
                        <label>Documento:</label> <?= htmlspecialchars($documento) ?><br>
                        <label>Fecha Nac.:</label> <?= htmlspecialchars($fecha) ?><br>
                        <label>Celular:</label> <?= htmlspecialchars($celular) ?><br>
                        <label>Nro HC:</label> <?= htmlspecialchars($historia_clinica) ?><br>

                        <?php if ($tipoUsuario === 'profesional'): ?>
                            <label>Agregar consulta 
                                <a href="./?seccion=historia_clinica_new&id=<?= $pacienteId ?>&nc=<?= $rand ?>" class="btn btn-info">
                                    <i class="fa fa-plus"></i>
                                </a>
                            </label><br>
                        <?php else: ?>
                            <div class="alert alert-danger">Solo los profesionales pueden agregar un nuevo registro en la historia clínica.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabla de historias clínicas -->
        <div class="row">
            <div class="col-lg-12">
                <div class="hpanel">
                    <div class="panel-body">
                        <button class="btn btn-warning pull-right mb-3" onclick="imprimirTabla()">
                            <i class="fa fa-print"></i> Imprimir HC
                        </button>

                        <div class="table-responsive">
                            <table class="table table-striped table-bordered table-hover dataTables-example" id="DataTables_HC">
                                <thead>
                                    <tr>
                                        <th style="width:60%">Consultas</th>
                                        <th style="width:30%">Información Profesional</th>
                                        <th style="width:10%">Firma</th>
                                        <?php if ($tipoUsuario === 'profesional') echo '<th>Acciones</th>'; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($historias as $hc): ?>
                                        <tr>
                                            <td>
                                                <span style="font-weight:bold;">• Motivo de consulta:</span><br>
                                                <span style="padding-left:40px;"><?= htmlspecialchars($hc['motivo'] ?? '---') ?></span><br>

                                                <span style="font-weight:bold;">• Sintomas:</span><br>
                                                <span style="padding-left:40px;"><?= htmlspecialchars($hc['sintomas'] ?? '---') ?></span><br>

                                                <span style="font-weight:bold;">• Signos vitales:</span><br>
                                                <span style="padding-left:40px;"><?= htmlspecialchars($hc['vitales'] ?? '---') ?></span><br>

                                                <span style="font-weight:bold;">• Exámenes solicitados:</span><br>
                                                <span style="padding-left:40px;"><?= htmlspecialchars($hc['examenes'] ?? '---') ?></span><br>

                                                <span style="font-weight:bold;">• Diagnóstico:</span><br>
                                                <span style="padding-left:40px;"><?= htmlspecialchars($hc['diagnostico'] ?? '---') ?></span><br>

                                                <span style="font-weight:bold;">• Medicación prescripta:</span><br>
                                                <span style="padding-left:40px;"><?= htmlspecialchars($hc['medicamento'] ?? '---') ?></span><br>

                                                <span style="font-weight:bold;">• Observaciones:</span><br>
                                                <span style="padding-left:40px;"><?= htmlspecialchars($hc['texto'] ?? '---') ?></span>
                                            </td>
                                            <td>
                                                <label><?= date('d-m-Y', strtotime($hc['fecha'])) ?></label><br>
                                                <label><?= htmlspecialchars($hc['profesionalapellido'].' '.$hc['profesionalnombre']) ?> - <?= htmlspecialchars($hc['especialidad']) ?></label><br>
                                                <label>Matricula nacional: N° <?= htmlspecialchars($hc['matricula_nacional']) ?></label><br>
                                                <label>Matricula provincial: N° <?= htmlspecialchars($hc['matricula_provincial']) ?></label>
                                            </td>
                                            <td style="text-align:center;">
                                                <?php if (!empty($hc['profesionalfirma'])): ?>
                                                    <img src="<?= htmlspecialchars($hc['profesionalfirma']) ?>" alt="Firma" style="width:100px; height:100px;">
                                                <?php endif; ?>
                                            </td>
                                            <?php if ($tipoUsuario === 'profesional'): ?>
                                                <td>
                                                    <?php if ($hc['profesional_id'] == $profesionalId): ?>
                                                        <a href="./?seccion=historia_clinica_edit&id=<?= $hc['Id'] ?>" class="btn btn-success">
                                                            <i class="fa fa-pencil"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endif; ?>
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
</div>

<script>
var nombrePaciente = "<?= htmlspecialchars($paciente, ENT_QUOTES, 'UTF-8') ?>";
var dniPaciente = "<?= htmlspecialchars($documento, ENT_QUOTES, 'UTF-8') ?>";

function imprimirTabla() {
    var tablaOriginal = document.getElementById('DataTables_HC').cloneNode(true);
    var indiceAcciones = 3;

    var encabezados = tablaOriginal.getElementsByTagName('th');
    if (encabezados.length > indiceAcciones) encabezados[indiceAcciones].remove();

    var filas = tablaOriginal.getElementsByTagName('tr');
    for (var i=0;i<filas.length;i++){
        var celdas = filas[i].getElementsByTagName('td');
        if(celdas.length>indiceAcciones) celdas[indiceAcciones].remove();
    }

    var tablaHTML = tablaOriginal.outerHTML;
    var ventana = window.open('', '_blank');
    ventana.document.write(`
        <html>
        <head>
        <title>Imprimir Historia Clínica</title>
        <style>
            table { width:100%; border-collapse: collapse; }
            th, td { border:1px solid black; padding:10px; text-align:left; }
            th { background:#f2f2f2; }
            img { width:100px; height:100px; }
        </style>
        </head>
        <body>
            <h2>Historia Clínica de ${nombrePaciente} (DNI: ${dniPaciente})</h2>
            ${tablaHTML}
            <script>
                window.onload=function(){window.print(); window.close();}
            <\/script>
        </body>
        </html>
    `);
    ventana.document.close();
    ventana.focus();
}

$(document).ready(function () {
    $('.dataTables-example').DataTable({
        "iDisplayLength": 10,
        "aLengthMenu": [[10,25,50,100,1000],[10,25,50,100,1000]],
        "bSort": false,
        dom: '<"html5buttons"B>lTfgitp',
        buttons: [],
        "language": {
            "sProcessing": "Procesando...",
            "sLengthMenu": "Mostrar _MENU_ registros",
            "sZeroRecords": "No se encontraron resultados",
            "sEmptyTable": "Ningún dato disponible en esta tabla",
            "sInfo": "Mostrando registros del _START_ al _END_ de un total de _TOTAL_ registros",
            "sInfoEmpty": "Mostrando registros del 0 al 0 de un total de 0 registros",
            "sInfoFiltered": "(filtrado de un total de _MAX_ registros)",
            "sSearch": "Buscar:",
            "oPaginate": {"sFirst":"Primero","sLast":"Último","sNext":"Siguiente","sPrevious":"Anterior"}
        }
    });
});
</script>