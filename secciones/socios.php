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
            CASE WHEN pa.paciente_id IS NOT NULL THEN 'si' ELSE '' END AS aldia
        FROM pacientes p
        LEFT JOIN (
            SELECT DISTINCT paciente_id
            FROM pagos_afiliados
            WHERE fecha_correspondiente BETWEEN DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 1 MONTH),'%Y-%m-01') AND '2030-01-01'
        ) pa ON pa.paciente_id = p.Id
        $where
        ORDER BY p.apellido ASC
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
        <div class="card card-primary card-outline">
            <div class="card-header">
                <h3 class="card-title">Resultados</h3>
            </div>
            <div class="card-body table-responsive">
                <table class="table table-striped table-bordered datatable" style="width:100%">
                    <thead class="thead-dark">
                        <tr>
                            <th>Apellido</th>
                            <th>Nombre</th>
                            <th>Documento</th>
                            <th>Socio N°</th>
                            <th>Al día</th>
                            <th>Historial</th>
                            <th>Tipo</th>
                            <th>Celular</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pacientes as $r): ?>
                            <tr>
                                <td><?= htmlspecialchars($r['apellido']) ?></td>
                                <td><?= htmlspecialchars($r['nombre']) ?></td>
                                <td><?= htmlspecialchars($r['tipo_documento']) ?>: <?= htmlspecialchars($r['documento']) ?></td>
                                <td>
                                    <?= htmlspecialchars($r['nro_afiliado']) ?>
                                    <?php if ($r['nro_afiliado'] != ''): ?>
                                        <span class="float-right ml-2">
                                            <i class="fa <?= $r['aldia'] === 'si' ? 'fa-check text-success' : 'fa-times text-danger' ?>"></i>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= $r['aldia'] === 'si' ? '<span class="text-success">Si</span>' : '<span class="text-danger">No</span>' ?>
                                </td>
                                <td>
                                    <a href="./?seccion=socios_historial&id=<?= $r['Id'] ?>&nc=<?= $rand ?>" 
                                       class="btn btn-info btn-sm rounded-circle" title="Historial">
                                        <i class="fa fa-eye"></i>
                                    </a>
                                </td>
                                <td><?= htmlspecialchars($r['tipo'] ?? 'No definido') ?></td>
                                <td><?= htmlspecialchars($r['celular']) ?></td>
                                <td class="text-center">
                                    <div class="btn-group">
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
document.addEventListener("DOMContentLoaded", function () {
    $('.datatable').each(function () {
        initDataTable($(this));
    });
});
</script>