<?php
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

    pa.ultimo_pago,

    CASE 
        WHEN pa.ultimo_pago IS NULL THEN 999
        ELSE TIMESTAMPDIFF(MONTH, pa.ultimo_pago, CURDATE())
    END AS meses_adeudados,

    CASE 
        WHEN pa.ultimo_pago >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 2 MONTH),'%Y-%m-01') 
        THEN 'si' ELSE 'no' 
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
?>

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
                                <th>Al día</th>
                                <th>Meses adeudados</th>
                                
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pacientes as $r): ?>
                                <tr>
                                    <td><?= htmlspecialchars($r['apellido']) ?></td>
                                    <td><?= htmlspecialchars($r['nombre']) ?></td>
                                    <td><?= htmlspecialchars($r['tipo_documento']) ?>: <?= htmlspecialchars($r['documento']) ?>
                                    </td>
                                    
                                    <td><?= htmlspecialchars($r['celular']) ?></td>
                                    <td>
                                        <?= htmlspecialchars($r['nro_afiliado']) ?>
                                       
                                    </td>
                                    <td>
                                        <?= $r['aldia'] === 'si' ? '<span class="text-success">Si</span>' : '<span class="text-danger">No</span>' ?>
                                    </td>
                                    <td>
                                        <?php
                                        $meses = (int) $r['meses_adeudados'];

                                        if ($meses === 999) {
                                            echo '<span class="text-danger">Nunca pagó</span>';
                                        } elseif ($meses <= 2) {
                                            echo '<span class="text-success">' . $meses . '</span>';
                                        } elseif ($meses <= 4) {
                                            echo '<span class="text-warning">' . $meses . '</span>';
                                        } else {
                                            echo '<span class="text-danger">' . $meses . '</span>';
                                        }
                                        ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group">
                                             <a href="./?seccion=socios_historial&id=<?= $r['Id'] ?>&nc=<?= $rand ?>"
                                            class="btn btn-info btn-sm rounded-circle" title="Historial">
                                            <i class="fa fa-eye"></i>
                                        </a>
                                            <a href="./?seccion=afiliados_new&id=<?= $r['Id'] ?>&nc=<?= $rand ?>"
                                                class="btn btn-success btn-sm rounded-circle" title="Nuevo pago">
                                                <i class="fa fa-plus"></i>
                                            </a>
                                            <a href="./?seccion=pacientes_edit&id=<?= $r['Id'] ?>&nc=<?= $rand ?>"
                                                class="btn btn-warning btn-sm rounded-circle" title="Editar">
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
    document.addEventListener("DOMContentLoaded", function () {
        $('.datatable').each(function () {
            initDataTable($(this));
        });
    });
</script>