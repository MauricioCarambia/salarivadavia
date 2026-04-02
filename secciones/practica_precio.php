<?php
require_once "inc/db.php";

/* 🔎 Obtener prácticas */
$practicas = $pdo->query("SELECT id, nombre FROM practicas WHERE activo = 1 ORDER BY nombre")
    ->fetchAll(PDO::FETCH_ASSOC);

/* 🔎 Obtener precios */
$stmt = $pdo->query("
    SELECT pp.*, p.nombre AS practica
    FROM practicas_precios pp
    INNER JOIN practicas p ON p.id = pp.practica_id
    ORDER BY p.nombre
");
$precios = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="card card-outline card-info shadow-sm">

    <div class="card-header d-flex justify-content-between">
        <h3 class="card-title">
            <i class="fas fa-dollar-sign mr-2"></i> Precios
             <button class="btn btn-success btn-sm" data-toggle="modal" data-target="#modalCrear">
            <i class="fas fa-plus"></i> Nuevo
        </button>
        </h3>

       
    </div>

    <div class="card-body">

        <table id="tablaPrecios" class="table table-hover">
            <thead>
                <tr>
                    <th>Práctica</th>
                    <th>Tipo Paciente</th>
                    <th>Precio</th>
                    <th>Estado</th>
                    <th width="120">Acciones</th>
                </tr>
            </thead>
            <tbody>

                <?php foreach ($precios as $p): ?>
                    <tr id="fila_<?= $p['id'] ?>">

                        <td><?= htmlspecialchars($p['practica']) ?></td>

                        <td>
                            <span class="badge badge-info">
                                <?= ucfirst($p['tipo_paciente']) ?>
                            </span>
                        </td>

                        <td>$<?= number_format($p['precio'], 2) ?></td>

                        <td>
                            <?= $p['activo']
                                ? '<span class="badge badge-success">Activo</span>'
                                : '<span class="badge badge-secondary">Inactivo</span>' ?>
                        </td>

                       <td class="text-center d-flex justify-content-center gap-2">

                            <button class="btn btn-success btn-sm editar rounded-circle" data-id="<?= $p['id'] ?>"
                                data-practica="<?= $p['practica_id'] ?>" data-tipo="<?= $p['tipo_paciente'] ?>"
                                data-precio="<?= $p['precio'] ?>" data-activo="<?= $p['activo'] ?? 1 ?>">
                                <i class="fas fa-edit"></i>
                            </button>

                            <button class="btn btn-danger btn-sm eliminar rounded-circle" data-id="<?= $p['id'] ?>">
                                <i class="fas fa-trash"></i>
                            </button>

                        </td>

                    </tr>
                <?php endforeach; ?>

            </tbody>
        </table>

    </div>
</div>
<!-- CREAR -->
<div class="modal fade" id="modalCrear">
    <div class="modal-dialog">
        <form id="formCrear">
            <div class="modal-content">

                <div class="modal-header bg-success">
                    <h5>Nueva práctica</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>

                <div class="modal-body">

                    <select name="practica_id" class="form-control mb-2" required>
                        <option value="">Seleccionar práctica</option>
                        <?php foreach ($practicas as $pr): ?>
                            <option value="<?= $pr['id'] ?>"><?= $pr['nombre'] ?></option>
                        <?php endforeach; ?>
                    </select>

                    <select name="tipo_paciente" class="form-control mb-2">
                        <option value="particular">Particular</option>
                        <option value="socio">Socio</option>
                    </select>

                    <input type="number" step="0.01" name="precio" class="form-control" placeholder="Precio">

                </div>

                <div class="modal-footer">
                    <button class="btn btn-success">Guardar</button>
                </div>

            </div>
        </form>
    </div>
</div>

<!-- EDITAR -->
<div class="modal fade" id="modalEditar">
    <div class="modal-dialog">
        <form id="formEditar">
            <input type="hidden" name="id" id="edit_id">

            <div class="modal-content">

                <div class="modal-header bg-primary">
                    <h5>Editar precio</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>

                <div class="modal-body">

                    <select name="practica_id" id="edit_practica" class="form-control mb-2">
                        <?php foreach ($practicas as $pr): ?>
                            <option value="<?= $pr['id'] ?>"><?= $pr['nombre'] ?></option>
                        <?php endforeach; ?>
                    </select>

                    <select name="tipo_paciente" id="edit_tipo" class="form-control mb-2">
                        <option value="particular">Particular</option>
                        <option value="socio">Socio</option>
                    </select>

                    <input type="number" step="0.01" name="precio" id="edit_precio" class="form-control">

                    <select name="activo" id="edit_activo" class="form-control mt-2">
                        <option value="1">Activo</option>
                        <option value="0">Inactivo</option>
                    </select>

                </div>

                <div class="modal-footer">
                    <button class="btn btn-primary">Guardar</button>
                </div>

            </div>
        </form>
    </div>
</div>
<script>
    let tabla;

    $(document).ready(function () {
        tabla = $('#tablaPrecios').DataTable({
            responsive: true,
            autoWidth: false
        });
    });

    /* CREAR */
    $('#formCrear').submit(function (e) {
        e.preventDefault();

        $.post('ajax/practicas_precios_guardar.php', $(this).serialize(), function (res) {

            if (res.success) {
                location.reload();
            } else {
                Swal.fire('Error', res.message, 'error');
            }

        }, 'json');
    });

    /* EDITAR */
    $(document).on('click', '.editar', function () {

        $('#edit_id').val($(this).data('id'));
        $('#edit_practica').val($(this).data('practica'));
        $('#edit_tipo').val($(this).data('tipo'));
        $('#edit_precio').val($(this).data('precio'));
        $('#edit_activo').val($(this).data('activo'));

        $('#modalEditar').modal('show');
    });

    $('#formEditar').submit(function (e) {
        e.preventDefault();

        $.post('ajax/practicas_precios_guardar.php', $(this).serialize(), function (res) {

            if (res.success) {
                location.reload();
            } else {
                Swal.fire('Error', res.message, 'error');
            }

        }, 'json');
    });

    /* ELIMINAR */
    $(document).on('click', '.eliminar', function () {

        let id = $(this).data('id');

        Swal.fire({
            title: '¿Eliminar?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33'
        }).then((r) => {

            if (r.isConfirmed) {

                $.post('ajax/practicas_precios_eliminar.php', { id: id }, function (res) {

                    if (res.success) {
                       tabla.row($('#fila_'+id)).remove().draw();
                        Swal.fire('Eliminado', '', 'success');
                    }

                }, 'json');

            }

        });

    });
</script>