<?php
require_once __DIR__ . '/../inc/db.php';

$busqueda = trim($_GET['busqueda'] ?? '');
$rand = rand(1000, 9999);

$pacientes = [];

/* =============================
   BUSCAR SOLO SI HAY TEXTO
============================= */
if ($busqueda !== '') {

    $sql = $pdo->prepare("
    SELECT 
        Id,
        apellido,
        nombre,
        documento,
        nacimiento,
        celular,
        historia_clinica
    FROM pacientes
    WHERE
          apellido      LIKE :b1
       OR nombre        LIKE :b2
       OR documento     LIKE :b3
       OR nro_afiliado  LIKE :b4
    ORDER BY apellido ASC
    LIMIT 300
");

    $buscar = "%{$busqueda}%";

    $sql->execute([
        ':b1' => $buscar,
        ':b2' => $buscar,
        ':b3' => $buscar,
        ':b4' => $buscar
    ]);

    $pacientes = $sql->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!-- Main Wrapper -->
<div id="wrapper">
    <div class="content animate-panel">
        <div class="row">

            <!-- ================= BUSCADOR ================= -->
            <div class="col-md-6 col-md-offset-3 m-b">

                <form action="./" method="get">
                    <input type="hidden" name="seccion" value="historia_pacientes">
                    <input type="hidden" name="nc" value="<?= $rand ?>">

                    <div class="input-group input-group-lg">

                        <input type="text" class="form-control" name="busqueda"
                            value="<?= htmlspecialchars($busqueda) ?>"
                            placeholder="Buscar por apellido, nombre o DNI..." autofocus required>

                        <span class="input-group-btn">
                            <button type="submit" class="btn btn-success">
                                <i class="fa fa-search"></i> BUSCAR
                            </button>
                        </span>

                    </div>
                </form>
            </div>

            <?php if ($busqueda !== ''): ?>

                <div class="col-lg-12">
                    <div class="hpanel">
                        <div class="panel-body">

                            <div class="table-responsive">

                                <table class="table table-striped table-bordered table-hover dataTables-example">
                                    <thead>
                                        <tr>
                                            <th></th>
                                            <th>Paciente</th>
                                            <th>Documento</th>
                                            <th>Fecha Nac.</th>
                                            <th>Celular</th>
                                            <th>N° Historia Clínica</th>
                                        </tr>
                                    </thead>

                                    <tbody>

                                        <?php if (count($pacientes) > 0): ?>

                                            <?php foreach ($pacientes as $p): ?>

                                                <tr>
                                                    <td width="60">
                                                        <a href="./?seccion=historia_clinica&id=<?= $p['Id'] ?>&nc=<?= $rand ?>"
                                                            class="btn btn-info btn-sm">
                                                            <i class="fa fa-search"></i>
                                                        </a>
                                                    </td>

                                                    <td><?= htmlspecialchars($p['apellido'] . ' ' . $p['nombre']) ?></td>
                                                    <td><?= htmlspecialchars($p['documento']) ?></td>
                                                    <td><?= htmlspecialchars($p['nacimiento']) ?></td>
                                                    <td><?= htmlspecialchars($p['celular']) ?></td>
                                                    <td><?= htmlspecialchars($p['historia_clinica']) ?></td>

                                                </tr>

                                            <?php endforeach; ?>

                                        <?php else: ?>

                                            <tr>
                                                <td colspan="6" class="text-center">
                                                    No se encontraron pacientes
                                                </td>
                                            </tr>

                                        <?php endif; ?>

                                    </tbody>
                                </table>

                            </div>
                        </div>
                    </div>
                </div>

            <?php endif; ?>

        </div>
    </div>
</div>

<!-- DataTables -->
<script>
    $(document).ready(function () {
        $('.dataTables-example').DataTable({
            "iDisplayLength": 50,
            "aLengthMenu": [[10, 25, 50, 100, 1000], [10, 25, 50, 100, 1000]],
            "bSort": false,
            dom: '<"html5buttons"B>lTfgitp',
            buttons: [
                { extend: 'excel', title: 'pacientes' },
                { extend: 'pdf', title: 'pacientes' },
                {
                    extend: 'print', text: 'IMPRIMIR', customize: function (win) {
                        $(win.document.body).addClass('white-bg').css('font-size', '10px');
                        $(win.document.body).find('table').addClass('compact').css('font-size', 'inherit');
                    }
                }
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
                "oPaginate": { "sFirst": "Primero", "sLast": "Último", "sNext": "Siguiente", "sPrevious": "Anterior" }
            }
        });
    });
</script>