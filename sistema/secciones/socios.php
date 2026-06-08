<?php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/services/afiliados.php';
$rand = uniqid();
$busqueda = trim($_GET['busqueda'] ?? '');
$pacientes = [];
$user_tipo = $_SESSION['user_tipo'] ?? '';

if ($busqueda !== '') {

    $params = [];
    $where = "
        WHERE p.nombre LIKE :nombre 
        OR p.apellido LIKE :apellido 
        OR p.documento LIKE :documento
        OR p.nro_afiliado LIKE :afiliado
    ";

    $likeBusqueda = "%$busqueda%"; // El % permite coincidencia parcial
    $params = [
        ':nombre' => $likeBusqueda,
        ':apellido' => $likeBusqueda,
        ':documento' => $likeBusqueda,
        ':afiliado' => $likeBusqueda
    ];

    $sql = "
     SELECT 
    p.*,
    p.tipo_socio,
    p.fecha_alta,

    pa.ultimo_pago,

    CASE 
    WHEN pa.ultimo_pago IS NULL THEN 999
    ELSE GREATEST(0, PERIOD_DIFF(
        DATE_FORMAT(CURDATE(), '%Y%m'),
        DATE_FORMAT(pa.ultimo_pago, '%Y%m')
    ))
END AS meses_adeudados,

CASE 
    WHEN pa.ultimo_pago IS NULL THEN 'no'
    WHEN GREATEST(0, PERIOD_DIFF(
        DATE_FORMAT(CURDATE(), '%Y%m'),
        DATE_FORMAT(pa.ultimo_pago, '%Y%m')
    )) <= 1
    THEN 'si'
    ELSE 'no'
END AS aldia

FROM pacientes p

LEFT JOIN (
    SELECT paciente_id, MAX(fecha_correspondiente) AS ultimo_pago
    FROM pagos_afiliados
    GROUP BY paciente_id
) pa ON pa.paciente_id = p.Id

WHERE 
    p.nombre LIKE :nombre 
    OR p.apellido LIKE :apellido 
    OR p.documento LIKE :documento
    OR p.nro_afiliado LIKE :afiliado

ORDER BY p.apellido ASC
LIMIT 25
    ";

    $stmt = $conexion->prepare($sql);
    $stmt->execute($params);
    $pacientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$stmt = $pdo->query("
    SELECT COUNT(*) total
    FROM pacientes
    WHERE tipo_socio = 'vitalicio'
");

$totalVitalicios = (int)$stmt->fetchColumn();
$stmt = $pdo->query("
    SELECT COUNT(*) total
    FROM pacientes p

    LEFT JOIN (
        SELECT
            paciente_id,
            MAX(fecha_correspondiente) ultimo_pago
        FROM pagos_afiliados
        GROUP BY paciente_id
    ) pa ON pa.paciente_id = p.Id

    WHERE
        p.tipo_socio <> 'vitalicio'

        AND pa.ultimo_pago IS NOT NULL

        AND PERIOD_DIFF(
            DATE_FORMAT(
                DATE_SUB(CURDATE(), INTERVAL 1 MONTH),
                '%Y%m'
            ),
            DATE_FORMAT(
                pa.ultimo_pago,
                '%Y%m'
            )
        ) <= 2
");

$totalActivos = (int)$stmt->fetchColumn();
?>
<div class="mb-3">

    <span class="badge badge-success p-2 mr-2">
        Socios activos:
        <?= number_format($totalActivos, 0, ',', '.') ?>
    </span>

    <span class="badge badge-info p-2">
        Vitalicios:
        <?= number_format($totalVitalicios, 0, ',', '.') ?>
    </span>

</div>
<!-- Buscador -->
<div class="row mb-3">
    <div class="col-12">
        <div class="card card-info card-outline">
            <div class="card-header">
                <h3 class="card-title">
                    Socios
                    <a href="./?seccion=pacientes_new&nc=<?= $rand ?>" class="btn btn-info btn-sm ml-2 rounded">
                        <i class="fa fa-plus"></i>
                    </a>
                </h3>
            </div>
            <div class="card-body">
                <form action="./" method="get">
                    <input type="hidden" name="seccion" value="socios">
                    <input type="hidden" name="nc" value="<?= $rand ?>">
                    <div class="input-group input-group-lg">
                        <input type="text" class="form-control" name="busqueda"
                            value="<?= htmlspecialchars($busqueda) ?>" placeholder="Buscar socios..." required>
                        <div class="input-group-append">
                            <button type="submit" class="btn btn-success">
                                <i class="fa fa-search"></i> Buscar
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($pacientes)): ?>
    <div class="row">
        <div class="col-12">
            <div class="card card-info card-outline">
                <div class="card-header">
                    <h3 class="card-title">Resultados</h3>
                </div>
                <div class="card-body table-responsive">
                    <table class="table table-striped  datatable" style="width:100%">
                        <thead class="thead-dark">

                            <tr>
                                <th>Apellido</th>
                                <th>Nombre</th>
                                <th>Documento</th>
                                <th>Celular</th>
                                <th>Socio N°</th>
                                <th>Tipo Cobro</th>
                                <th>Al día</th>
                                <th>Meses adeudados</th>

                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pacientes as $r): ?>

                                <?php
                                $tipoPaciente = obtenerEstadoAfiliado($pdo, (int)$r['Id']);
                                $meses = (int)$tipoPaciente['meses_adeudados'];
                                ?>

                                <tr>

                                    <td><?= htmlspecialchars($r['apellido']) ?></td>
                                    <td><?= htmlspecialchars($r['nombre']) ?></td>

                                    <td>
                                        <?= htmlspecialchars($r['tipo_documento']) ?>:
                                        <?= htmlspecialchars($r['documento']) ?>
                                    </td>

                                    <td><?= htmlspecialchars($r['celular']) ?></td>

                                    <td>
                                        <?= htmlspecialchars($r['nro_afiliado']) ?>
                                    </td>

                                    <!-- TIPO COBRO -->
                                    <td>

                                        <?php if ($tipoPaciente['cobra_como'] === 'socio'): ?>

                                            <span class="text-primary font-weight-bold">
                                                SOCIO
                                            </span>

                                        <?php else: ?>

                                            <span class="text-dark font-weight-bold">
                                                PARTICULAR
                                            </span>

                                        <?php endif; ?>

                                    </td>

                                    <!-- AL DIA -->
                                    <td>

                                        <?php

                                        switch ($tipoPaciente['tipo']) {

                                            case 'vitalicio':
                                                echo '<span class="text-info font-weight-bold">Vitalicio</span>';
                                                break;

                                            case 'nuevo':
                                                echo '<span class="text-primary font-weight-bold">En gracia</span>';
                                                break;

                                            case 'socio':
                                                echo '<span class="text-success font-weight-bold">Sí</span>';
                                                break;

                                            default:
                                                echo '<span class="text-danger font-weight-bold">No</span>';
                                                break;
                                        }

                                        ?>

                                    </td>

                                    <!-- MESES ADEUDADOS -->
                                    <td>

                                        <?php

                                        if ($meses === 999) {

                                            echo '<span class="text-danger">Nunca pagó</span>';
                                        } elseif ($meses <= 2) {

                                            echo '<span class="text-success">' . $meses . '</span>';
                                        } elseif ($meses == 3) {

                                            echo '<span class="text-warning">' . $meses . '</span>';
                                        } else {

                                            echo '<span class="text-danger">' . $meses . '</span>';
                                        }

                                        ?>

                                    </td>

                                    <td class="text-center">

                                        <div class="btn-group">

                                            <a href="./?seccion=socios_historial&id=<?= $r['Id'] ?>&nc=<?= $rand ?>"
                                                class="btn btn-info btn-sm rounded-circle"
                                                title="Historial">
                                                <i class="fa fa-eye"></i>
                                            </a>

                                            <a href="./?seccion=afiliados_new&id=<?= $r['Id'] ?>&nc=<?= $rand ?>"
                                                class="btn btn-success btn-sm rounded-circle"
                                                title="Nuevo pago">
                                                <i class="fa fa-plus"></i>
                                            </a>

                                            <a href="./?seccion=pacientes_edit&id=<?= $r['Id'] ?>&nc=<?= $rand ?>"
                                                class="btn btn-warning btn-sm rounded-circle"
                                                title="Editar">
                                                <i class="fas fa-pencil-alt"></i>
                                            </a>

                                        </div>

                                    </td>

                                </tr>

                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
<?php elseif ($busqueda !== ''): ?>
    <p>No se encontraron resultados para "<strong><?= htmlspecialchars($busqueda) ?></strong>".</p>
<?php endif; ?>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        $('.datatable').each(function() {
            initDataTable($(this));
        });
    });
</script>