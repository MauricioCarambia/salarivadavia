<?php
require_once 'inc/db.php';
$rand = rand(1000, 9999);
$fechaSeleccionada = isset($_POST['fecha']) ? $_POST['fecha'] : date('Y-m-d');
// ---------------------- INGRESOS ----------------------
$sqlIngresos = "SELECT id, descripcion, monto, fecha FROM caja WHERE tipo = 'Ingreso' AND DATE(fecha) = :fecha";
$stmtIngresos = $conexion->prepare($sqlIngresos);
$stmtIngresos->execute(['fecha' => $fechaSeleccionada]);
$ingresos = $stmtIngresos->fetchAll(PDO::FETCH_ASSOC);
$totalIngresos = array_sum(array_column($ingresos, 'monto'));

// ---------------------- EGRESOS ----------------------
$sqlEgresos = "SELECT id, descripcion, monto, fecha FROM caja WHERE tipo = 'Egreso' AND DATE(fecha) = :fecha";
$stmtEgresos = $conexion->prepare($sqlEgresos);
$stmtEgresos->execute(['fecha' => $fechaSeleccionada]);
$egresos = $stmtEgresos->fetchAll(PDO::FETCH_ASSOC);
$totalEgresos = array_sum(array_column($egresos, 'monto'));

// ---------------------- PAGOS PROFESIONALES ----------------------
$sqlProf = "
    SELECT p.id_profesional, pr.nombre, pr.apellido, 
           SUM(p.monto) AS total_pago, 
           SUM(p.comision) AS total_comision, fecha
    FROM pagos_profesionales p
    JOIN profesionales pr ON pr.id = p.id_profesional
    WHERE DATE(p.fecha) = :fecha
    GROUP BY p.id_profesional
";
$stmtProf = $conexion->prepare($sqlProf);
$stmtProf->execute(['fecha' => $fechaSeleccionada]);
$pagosProf = $stmtProf->fetchAll(PDO::FETCH_ASSOC);

// Cálculos adicionales
$totalPagos = array_sum(array_column($pagosProf, 'total_pago'));
$total_comision = array_sum(array_column($pagosProf, 'total_comision'));
$total_ganancia = $totalPagos - $total_comision;

$totalIngresosGanancia = $totalIngresos + $totalPagos;
$totalEgresosComision = $totalEgresos + $total_comision;
$totalNeto = $totalIngresosGanancia - $totalEgresosComision;
?>

<!-- CONTENIDO -->
<div id="wrapper">
    <div class="normalheader transition animated fadeIn small-header">
        <div class="hpanel">
            <div class="panel-body">
                <div class="row">
                    <div class="col-lg-4">
                        <form method="post" class="form-inline mb-3">
                            <label for="fecha">Seleccionar fecha:</label>
                            <input type="date" id="fecha" name="fecha" class="form-control mx-2"
                                value="<?= $fechaSeleccionada ?>">
                            <button type="submit" class="btn btn-info">Ver movimientos</button>
                            <a href="./?seccion=caja&nc=<?php echo $rand; ?>" class="btn btn-info">Volver</a>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-4">
            <div class="hpanel bg-success text-center p-4 rounded">
                <h3>INGRESOS</h3>
                <h4>Total Profesionales + Ingreso</h4>
                <h2>$<?= number_format($totalIngresosGanancia, 2) ?></h2>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="hpanel bg-danger text-center p-4 rounded">
                <h3>EGRESOS</h3>
                <h4>Total Egresos + Comisiones</h4>
                <h2>$<?= number_format($totalEgresosComision, 2) ?></h2>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="hpanel bg-info text-center p-4 rounded">
                <h3>NETO</h3>
                <h4>Ingresos - Egresos</h4>
                <h2>$<?= number_format($totalNeto, 2) ?></h2>
            </div>
        </div>
    </div>

    <br>

    <div class="content animate-panel">
        <div class="row">
            <!-- INGRESOS -->
            <div class="col-lg-6">
                <div class="hpanel">
                    <div class="panel-body">
                        <h3>📥 Ingresos de Caja</h3>
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered dataTable" id="tablaIngresos">
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Descripción</th>
                                        <th>Monto</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($ingresos as $ing): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($ing['fecha']) ?></td>
                                            <td><?= htmlspecialchars($ing['descripcion']) ?></td>
                                            <td>$<?= number_format($ing['monto'], 2) ?></td>
                                            <td>
                                                <a href="./?seccion=movimiento_edit&id=<?= $ing['id'] ?>&nc=<?= rand(1000, 9999) ?>"
                                                    class="btn btn-success"><i class="fa fa-pencil"></i></a>
                                                <a href="./?seccion=movimiento_delete&id=<?= $ing['id'] ?>&nc=<?= rand(1000, 9999) ?>"
                                                    class="btn btn-danger"><i class="fa fa-trash-o"></i></a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td class="text-center bg-success"><strong>Total Ingresos</strong></td>
                                        <td class="text-center bg-success"></td>
                                        <td class="text-center bg-success">
                                            <strong>$<?= number_format($totalIngresos, 2) ?></strong>
                                        </td>
                                        <td class="text-center bg-success"></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- EGRESOS -->
            <div class="col-lg-6">
                <div class="hpanel">
                    <div class="panel-body">
                        <h3>📤 Egresos de Caja</h3>
                        <div class="table-responsive">

                            <table class="table table-striped table-bordered dataTable" id="tablaEgresos">
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Descripción</th>
                                        <th>Monto</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($egresos as $eg): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($eg['fecha']) ?></td>
                                            <td><?= htmlspecialchars($eg['descripcion']) ?></td>
                                            <td>$<?= number_format($eg['monto'], 2) ?></td>
                                            <td>
                                                <a href="./?seccion=movimiento_edit&id=<?= $eg['id'] ?>&nc=<?= rand(1000, 9999) ?>"
                                                    class="btn btn-success"><i class="fa fa-pencil"></i></a>
                                                <a href="./?seccion=movimiento_delete&id=<?= $eg['id'] ?>&nc=<?= rand(1000, 9999) ?>"
                                                    class="btn btn-danger"><i class="fa fa-trash-o"></i></a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td class="text-center bg-danger"><strong>Total Egresos</strong></td>
                                        <td class="text-center bg-danger"></td>
                                        <td class="text-center bg-danger">
                                            <strong>$<?= number_format($totalEgresos, 2) ?></strong>
                                        </td>
                                        <td class="text-center bg-danger"></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- PAGOS A PROFESIONALES -->
            <div class="col-lg-12">
                <div class="hpanel">
                    <div class="panel-body">
                        <h3>👨‍⚕️ Pagos a Profesionales (Hoy)</h3>
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered dataTable" id="tablaProfesionales">
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Profesional</th>
                                        <th>Total facturado</th>
                                        <th>Pago profesional</th>
                                        <th>Ganancia</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pagosProf as $prof): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($prof['fecha']) ?></td>
                                            <td><?= htmlspecialchars($prof['apellido']) . ' ' . htmlspecialchars($prof['nombre']) ?>
                                            </td>
                                            <td>$<?= number_format($prof['total_pago'], 2) ?></td>
                                            <td>$<?= number_format($prof['total_comision'], 2) ?></td>
                                            <td>$<?= number_format($prof['total_pago'] - $prof['total_comision'], 2) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td class="text-center bg-info"></td>
                                        <td class="text-center bg-info"><strong>Total Pagado</strong></td>
                                        <td class="text-center bg-info">
                                            <strong>$<?= number_format($totalPagos, 2) ?></strong>
                                        </td>
                                        <td class="text-center bg-danger">
                                            <strong>$<?= number_format($total_comision, 2) ?></strong>
                                        </td>
                                        <td class="text-center bg-success">
                                            <strong>$<?= number_format($total_ganancia, 2) ?></strong>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- DATATABLES SCRIPT -->
<script>
    $(document).ready(function () {
        $('#tablaIngresos, #tablaEgresos, #tablaProfesionales').DataTable({
            "iDisplayLength": 50,
            "aLengthMenu": [[10, 25, 50, 100, 1000], [10, 25, 50, 100, 1000]],
            dom: '<"html5buttons"B>lTfgitp',
            buttons: [
                { extend: 'excel', title: 'Caja' },
                { extend: 'pdf', title: 'Caja' },
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
            language: {
                "sProcessing": "Procesando...",
                "sLengthMenu": "Mostrar _MENU_ registros",
                "sZeroRecords": "No se encontraron resultados",
                "sEmptyTable": "Ningún dato disponible en esta tabla",
                "sInfo": "Mostrando registros del _START_ al _END_ de un total de _TOTAL_ registros",
                "sInfoEmpty": "Mostrando registros del 0 al 0 de un total de 0 registros",
                "sInfoFiltered": "(filtrado de un total de _MAX_ registros)",
                "sSearch": "Buscar:"
            }
        });
    });
</script>