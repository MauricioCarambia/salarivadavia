<?php
// require_once 'inc/db.php';

// date_default_timezone_set('America/Argentina/Buenos_Aires');

// $inicioHoy = date('Y-m-d 00:00:00');
// $finHoy = date('Y-m-d 23:59:59');

// /* =============================
//    FUNCION REUTILIZABLE
// =============================*/
// function obtenerMovimientos($conexion, $tipo, $inicio, $fin)
// {
//     $sql = "SELECT id, descripcion, monto, fecha 
//             FROM caja 
//             WHERE tipo = :tipo 
//             AND fecha BETWEEN :inicio AND :fin";

//     $stmt = $conexion->prepare($sql);
//     $stmt->execute([
//         ':tipo' => $tipo,
//         ':inicio' => $inicio,
//         ':fin' => $fin
//     ]);

//     return $stmt->fetchAll(PDO::FETCH_ASSOC);
// }

// /* =============================
//    INGRESOS Y EGRESOS
// =============================*/
// $ingresos = obtenerMovimientos($conexion, 'Ingreso', $inicioHoy, $finHoy);
// $egresos = obtenerMovimientos($conexion, 'Egreso', $inicioHoy, $finHoy);

// $totalIngresos = array_sum(array_column($ingresos, 'monto'));
// $totalEgresos = array_sum(array_column($egresos, 'monto'));

// /* =============================
//    PROFESIONALES
// =============================*/
// $sqlProf = "
//     SELECT p.id_profesional, pr.nombre, pr.apellido, 
//            SUM(p.monto) AS total_pago, 
//            SUM(p.comision) AS total_comision, 
//            MAX(p.fecha) AS fecha
//     FROM pagos_profesionales p
//     JOIN profesionales pr ON pr.id = p.id_profesional
//     WHERE p.fecha BETWEEN :inicio AND :fin
//     GROUP BY p.id_profesional
// ";

// $stmtProf = $conexion->prepare($sqlProf);
// $stmtProf->execute([
//     ':inicio' => $inicioHoy,
//     ':fin' => $finHoy
// ]);

// $pagosProf = $stmtProf->fetchAll(PDO::FETCH_ASSOC);

// $totalPagos = array_sum(array_column($pagosProf, 'total_pago'));
// $totalComision = array_sum(array_column($pagosProf, 'total_comision'));
// $totalGanancia = $totalPagos - $totalComision;

// $totalIngresosFinal = $totalIngresos + $totalPagos;
// $totalEgresosFinal = $totalEgresos + $totalComision;
// $totalNeto = $totalIngresosFinal - $totalEgresosFinal;
?>

<div class="row mb-3">
    <div class="col-12">
        <div class="card card-primary card-outline">
            <h1>Control de Caja</h1>
            <a href="?seccion=movimiento_new" class="btn btn-primary btn-sm">
                <i class="fas fa-plus"></i> Nuevo movimiento
            </a>


            <!-- RESUMEN -->
            <div class="row">

                <div class="col-lg-4 col-12">
                    <div class="small-box bg-success">
                        <div class="inner">
                            <h3>$<?= number_format($totalIngresosFinal, 2) ?></h3>
                            <p>Ingresos Totales</p>
                        </div>
                        <div class="icon"><i class="fas fa-arrow-down"></i></div>
                    </div>
                </div>

                <div class="col-lg-4 col-12">
                    <div class="small-box bg-danger">
                        <div class="inner">
                            <h3>$<?= number_format($totalEgresosFinal, 2) ?></h3>
                            <p>Egresos Totales</p>
                        </div>
                        <div class="icon"><i class="fas fa-arrow-up"></i></div>
                    </div>
                </div>

                <div class="col-lg-4 col-12">
                    <div class="small-box bg-info">
                        <div class="inner">
                            <h3>$<?= number_format($totalNeto, 2) ?></h3>
                            <p>Balance Neto</p>
                        </div>
                        <div class="icon"><i class="fas fa-dollar-sign"></i></div>
                    </div>
                </div>

            </div>

            <!-- TABLAS -->
            <div class="row">

                <!-- INGRESOS -->
                <div class="col-md-6">
                    <div class="card card-success">
                        <div class="card-header">
                            <h3 class="card-title">Ingresos</h3>
                        </div>
                        <div class="card-body table-responsive">
                            <table class="table table-bordered table-striped tabla">
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Descripción</th>
                                        <th>Monto</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($ingresos as $ing): ?>
                                        <tr>
                                            <td><?= $ing['fecha'] ?></td>
                                            <td><?= htmlspecialchars($ing['descripcion']) ?></td>
                                            <td>$<?= number_format($ing['monto'], 2) ?></td>
                                            <td>
                                                <button class="btn btn-danger btn-sm btnDelete" data-id="<?= $ing['id'] ?>">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- EGRESOS -->
                <div class="col-md-6">
                    <div class="card card-danger">
                        <div class="card-header">
                            <h3 class="card-title">Egresos</h3>
                        </div>
                        <div class="card-body table-responsive">
                            <table class="table table-bordered table-striped tabla">
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Descripción</th>
                                        <th>Monto</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($egresos as $eg): ?>
                                        <tr>
                                            <td><?= $eg['fecha'] ?></td>
                                            <td><?= htmlspecialchars($eg['descripcion']) ?></td>
                                            <td>$<?= number_format($eg['monto'], 2) ?></td>
                                            <td>
                                                <button class="btn btn-danger btn-sm btnDelete" data-id="<?= $eg['id'] ?>">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>

            <!-- PROFESIONALES -->
            <div class="card">
                <div class="card-header bg-info">
                    <h3 class="card-title">Pagos a Profesionales</h3>
                </div>
                <div class="card-body table-responsive">
                    <table class="table table-bordered table-striped tabla">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Profesional</th>
                                <th>Total</th>
                                <th>Comisión</th>
                                <th>Ganancia</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pagosProf as $p): ?>
                                <tr>
                                    <td><?= $p['fecha'] ?></td>
                                    <td><?= $p['apellido'] . ' ' . $p['nombre'] ?></td>
                                    <td>$<?= number_format($p['total_pago'], 2) ?></td>
                                    <td>$<?= number_format($p['total_comision'], 2) ?></td>
                                    <td class="text-success">
                                        $<?= number_format($p['total_pago'] - $p['total_comision'], 2) ?>
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
<script>
    $(function () {

        $('.tabla').DataTable({
            responsive: true,
            autoWidth: false,
            pageLength: 25,
            language: {
                url: "//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json"
            }
        });

        // ELIMINAR CON SWEETALERT
        $(document).on('click', '.btnDelete', function () {
            let id = $(this).data('id');

            Swal.fire({
                title: '¿Eliminar?',
                text: "No se puede deshacer",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location = '?seccion=movimiento_delete&id=' + id;
                }
            });
        });

    });
</script>