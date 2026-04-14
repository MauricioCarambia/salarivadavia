<?php
session_start();
require_once "inc/db.php"; // $pdo
$mensaje = '';
$disabled = false;
$rand = mt_rand();

$id = $_GET['id'] ?? null;

// Inicializar variables de formulario
$motivo = $sintomas = $vitales = $examenes = $diagnostico = $medicamento = $texto = '';

// Guardar cambios si se envía el formulario
if (isset($_POST['guardar']) && $id) {
    $motivo = $_POST['motivo'] ?? '';
    $sintomas = $_POST['sintomas'] ?? '';
    $vitales = $_POST['vitales'] ?? '';
    $examenes = $_POST['examenes'] ?? '';
    $diagnostico = $_POST['diagnostico'] ?? '';
    $medicamento = $_POST['medicamento'] ?? '';
    $texto = $_POST['texto'] ?? '';

    $sqlUpdate = "UPDATE historias_clinicas 
                  SET motivo = :motivo,
                      sintomas = :sintomas,
                      vitales = :vitales,
                      examenes = :examenes,
                      diagnostico = :diagnostico,
                      medicamento = :medicamento,
                      texto = :texto
                  WHERE Id = :id";

    $stmt = $pdo->prepare($sqlUpdate);
    $exito = $stmt->execute([
        ':motivo' => $motivo,
        ':sintomas' => $sintomas,
        ':vitales' => $vitales,
        ':examenes' => $examenes,
        ':diagnostico' => $diagnostico,
        ':medicamento' => $medicamento,
        ':texto' => $texto,
        ':id' => $id
    ]);

    if ($exito) {
        $mensaje = '<div class="alert alert-success">Historia clínica editada satisfactoriamente.</div>';
        $disabled = true;
    } else {
        $mensaje = '<div class="alert alert-danger">Error al editar la historia clínica.</div>';
    }
}

// Obtener datos de la historia clínica y paciente
$paciente = '';
if ($id) {
    $sqlSelect = "SELECT hc.*, p.nombre AS pacienteNombre, p.apellido AS pacienteApellido 
                  FROM historias_clinicas hc
                  LEFT JOIN pacientes p ON p.Id = hc.paciente_id
                  WHERE hc.Id = :id
                  LIMIT 1";

    $stmt = $pdo->prepare($sqlSelect);
    $stmt->execute([':id' => $id]);
    $rArray = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($rArray) {
        $paciente = $rArray['pacienteApellido'] . ' ' . $rArray['pacienteNombre'];
        $motivo = $rArray['motivo'];
        $sintomas = $rArray['sintomas'];
        $vitales = $rArray['vitales'];
        $examenes = $rArray['examenes'];
        $diagnostico = $rArray['diagnostico'];
        $medicamento = $rArray['medicamento'];
        $texto = $rArray['texto'];
    }
}
?>

<div id="wrapper">
    <div class="normalheader transition animated fadeIn small-header">
        <div class="hpanel">
            <div class="panel-body">
                <h2>EDITAR REGISTRO DE <?= htmlspecialchars($paciente) ?></h2>
            </div>
        </div>
    </div>

    <?= $mensaje ?>

    <div class="content animate-panel">
        <div class="row">
            <div class="col-lg-10">
                <div class="hpanel">
                    <div class="panel-body">
                        <form class="form-horizontal" method="POST" 
                              action="./?seccion=historia_clinica_edit&id=<?= $id ?>&v=<?= $_GET['v'] ?? '' ?>&nc=<?= $rand ?>">

                            <div class="form-group">
                                <label class="col-sm-2 control-label">Motivo de consulta</label>
                                <div class="col-sm-10">
                                    <input type="text" class="form-control" name="motivo" 
                                           value="<?= htmlspecialchars($motivo) ?>" <?= $disabled ? 'disabled' : '' ?> required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="col-sm-2 control-label">Síntomas</label>
                                <div class="col-sm-10">
                                    <textarea class="form-control" name="sintomas" <?= $disabled ? 'disabled' : '' ?>><?= htmlspecialchars($sintomas) ?></textarea>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="col-sm-2 control-label">Signos Vitales</label>
                                <div class="col-sm-10">
                                    <input type="text" class="form-control" name="vitales" 
                                           value="<?= htmlspecialchars($vitales) ?>" <?= $disabled ? 'disabled' : '' ?>>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="col-sm-2 control-label">Exámenes Solicitados</label>
                                <div class="col-sm-10">
                                    <input type="text" class="form-control" name="examenes" 
                                           value="<?= htmlspecialchars($examenes) ?>" <?= $disabled ? 'disabled' : '' ?>>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="col-sm-2 control-label">Diagnóstico</label>
                                <div class="col-sm-10">
                                    <textarea class="form-control" name="diagnostico" <?= $disabled ? 'disabled' : '' ?>><?= htmlspecialchars($diagnostico) ?></textarea>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="col-sm-2 control-label">Medicamentos Prescriptos</label>
                                <div class="col-sm-10">
                                    <input type="text" class="form-control" name="medicamento" 
                                           value="<?= htmlspecialchars($medicamento) ?>" <?= $disabled ? 'disabled' : '' ?>>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="col-sm-2 control-label">Observaciones</label>
                                <div class="col-sm-10">
                                    <textarea class="form-control" name="texto" <?= $disabled ? 'disabled' : '' ?>><?= htmlspecialchars($texto) ?></textarea>
                                </div>
                            </div>

                            <div class="form-group">
                                <div class="col-sm-12 text-right">
                                    <?php if (!empty($_GET['v'])): ?>
                                        <a href="./?seccion=turnos_ver&id=<?= $_GET['v'] ?>&nc=<?= $rand ?>" class="btn btn-info">Volver</a>
                                    <?php else: ?>
                                        <a href="?seccion=historia_clinica&id=<?= $rArray['paciente_id'] ?>&nc=<?= $rand ?>" class="btn btn-danger">Volver a HC</a>
                                    <?php endif; ?>
                                    <input type="submit" class="btn btn-info" name="guardar" value="Guardar">
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>

        $(document).ready(function() {
            $('.dataTables-example').DataTable({
                "iDisplayLength": 50,
                "aLengthMenu": [
                    [10, 25, 50, 100, 1000],
                    [10, 25, 50, 100, 1000]
                ],
                "bSort": false,
                dom: '<"html5buttons"B>lTfgitp',
                buttons: [{
                        extend: 'excel',
                        title: 'pacientes'
                    },
                    {
                        extend: 'pdf',
                        title: 'pacientes'
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