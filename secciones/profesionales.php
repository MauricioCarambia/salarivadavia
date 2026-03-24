<?php
require_once __DIR__ . '/../inc/db.php'; // $pdo = instancia PDO
$rand = rand(1000, 9999);
?>

<div class="row mb-3">
    <div class="col-12">
        <div class="card card-info card-outline">
            <div class="card-header">
                <h3 class="card-title">
                    Profesionales
                    <a href="./?seccion=profesionales_new&nc=<?= $rand ?>" class="btn btn-info btn-sm ml-2 rounded">
                        <i class="fa fa-plus"></i>
                    </a>
                </h3>
            </div>
            <div class="card-body table-responsive">
                <table class="table table-striped table-bordered table-hover datatable w-100">
                    <thead class="thead-dark">
                        <tr>
                            <th>Apellido</th>
                            <th>Nombre</th>
                            <th>Especialidad</th>
                            <th>Celular</th>
                            <th>Firma</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $stmt = $pdo->query("
                                SELECT p.*, e.especialidad
                                FROM profesionales p
                                LEFT JOIN especialidades e ON e.Id = p.especialidad_id
                            ");
                        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)):
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($r['apellido']) ?></td>
                                <td><?= htmlspecialchars($r['nombre']) ?></td>
                                <td><?= htmlspecialchars($r['especialidad']) ?></td>
                                <td><?= htmlspecialchars($r['celular']) ?></td>
                                <td>
                                    <?php if (!empty($r['firma'])): ?>
                                        <img src="<?= htmlspecialchars($r['firma']) ?>" alt="Firma"
                                            style="width:80px; height:80px; object-fit:contain; border:1px solid #ddd; border-radius:5px;">
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group">
                                        <a href="./?seccion=profesionales_edit&id=<?= intval($r['Id']) ?>&nc=<?= $rand ?>"
                                            class="btn btn-success btn-sm rounded-circle" title="Editar profesional">
                                            <i class="fas fa-pencil-alt"></i>
                                        </a>
                                        <a href="./?seccion=profesionales_delete&id=<?= intval($r['Id']) ?>&nc=<?= $rand ?>"
                                            class="btn btn-danger btn-sm btn-eliminar rounded-circle" title="Eliminar profesional">
                                             <i class="fas fa-trash"></i>
                                        </a>
                                       
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener("DOMContentLoaded", function () {

    // Inicializar DataTable usando la función global definida en index.php
    $('.datatable').each(function () {
        initDataTable($(this));
    });

    // SweetAlert eliminar
    $('.btn-eliminar').click(function () {
        const id = $(this).data('id');
        const nc = <?= $rand ?>;
        Swal.fire({
            title: '¿Eliminar profesional?',
            text: "Esta acción no se puede deshacer.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = `./?seccion=profesionales_delete&id=${id}&confirmar=si&nc=${nc}`;
            }
        });
    });

});
</script>