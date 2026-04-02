<?php
require_once "inc/db.php";

$id = $_POST['id'] ?? null;
$nombre = trim($_POST['nombre'] ?? '');
$tipo = $_POST['tipo'] ?? 'consulta';
$activo = $_POST['activo'] ?? 1;


$stmt = $pdo->query("SELECT * FROM practicas ORDER BY nombre");
$practicas = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="card card-outline card-info shadow-sm">

    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title mb-0">
            <i class="fas fa-stethoscope mr-2"></i> Prácticas
            <button class="btn btn-success btn-sm" data-toggle="modal" data-target="#modalCrear">
            <i class="fas fa-plus"></i> Nueva
        </button>
        </h3>

        
    </div>

    <div class="card-body">

        <table id="tablaPracticas" class="table  table-hover">
            <thead class="thead-light">
                <tr>
                    <th>Nombre</th>
                    <th>Tipo</th>
                    <th>Estado</th>
                    <th width="120">Acciones</th>
                </tr>
            </thead>
            <tbody>

                <?php foreach ($practicas as $p): ?>
                    <tr id="fila_<?= $p['id'] ?>">

                        <td>
                            <?= htmlspecialchars($p['nombre']) ?>
                        </td>

                        <td>
                            <span class="badge badge-info">
                                <?= ucfirst($p['tipo']) ?>
                            </span>
                        </td>

                        <td>
                            <?= $p['activo']
                                ? '<span class="badge badge-success">Activo</span>'
                                : '<span class="badge badge-secondary">Inactivo</span>' ?>
                        </td>

                        <td class="text-center d-flex justify-content-center gap-2">

                            <button class="btn btn-success btn-sm editar rounded-circle" data-id="<?= $p['id'] ?>"
                                data-nombre="<?= htmlspecialchars($p['nombre']) ?>" data-tipo="<?= $p['tipo'] ?>"
                                data-activo="<?= $p['activo'] ?>">
                                <i class="fas fa-edit"></i>
                            </button>

                            <button class="btn btn-danger btn-sm btnEliminar rounded-circle" data-id="<?= $p['id'] ?>">
                                <i class="fas fa-trash"></i>
                            </button>

                        </td>

                    </tr>
                <?php endforeach; ?>

            </tbody>
        </table>

    </div>
</div>
<div class="modal fade" id="modalCrear">
    <div class="modal-dialog">
        <form id="formCrear">

            <div class="modal-content">

                <div class="modal-header bg-success">
                    <h5 class="modal-title">Nueva práctica</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>

                <div class="modal-body">

                    <div class="form-group">
                        <label>Nombre</label>
                        <input type="text" name="nombre" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label>Tipo</label>
                        <select name="tipo" class="form-control">
                            <option value="consulta">Consulta</option>
                            <option value="estudio">Estudio</option>
                            <option value="otro">Otro</option>
                        </select>
                    </div>

                </div>

                <div class="modal-footer">
                    <button class="btn btn-success">
                        <i class="fas fa-save"></i> Guardar
                    </button>
                </div>

            </div>

        </form>
    </div>
</div>
<div class="modal fade" id="modalEditar">
    <div class="modal-dialog">
        <form id="formEditar">

            <input type="hidden" name="id" id="edit_id">

            <div class="modal-content">

                <div class="modal-header bg-primary">
                    <h5 class="modal-title">Editar práctica</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>

                <div class="modal-body">

                    <div class="form-group">
                        <label>Nombre</label>
                        <input type="text" name="nombre" id="edit_nombre" class="form-control">
                    </div>

                    <div class="form-group">
                        <label>Tipo</label>
                        <select name="tipo" id="edit_tipo" class="form-control">
                            <option value="consulta">Consulta</option>
                            <option value="estudio">Estudio</option>
                            <option value="otro">Otro</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Estado</label>
                        <select name="activo" id="edit_activo" class="form-control">
                            <option value="1">Activo</option>
                            <option value="0">Inactivo</option>
                        </select>
                    </div>

                </div>

                <div class="modal-footer">
                    <button class="btn btn-primary">
                        <i class="fas fa-save"></i> Guardar cambios
                    </button>
                </div>

            </div>

        </form>
    </div>
</div>
<script>
    let tablaPracticas;

    $(document).ready(function () {

        tablaPracticas = $('#tablaPracticas').DataTable({
            responsive: true,
            autoWidth: false,
            pageLength: 10,
            dom:
                "<'row mb-2'<'col-md-6'l><'col-md-6'f>>" +
                "<'row mb-2'<'col-md-6'i><'col-md-6 d-flex justify-content-end'B>>" +
                "<'row'<'col-md-12'tr>>" +
                "<'row mt-2'<'col-md-12 d-flex justify-content-end'p>>",

            buttons: [
                { extend: 'excel', className: 'btn btn-success btn-sm' },
                { extend: 'pdf', className: 'btn btn-danger btn-sm' },
                { extend: 'print', className: 'btn btn-info btn-sm' }
            ],

            language: {
                search: "Buscar:",
                lengthMenu: "Mostrar _MENU_",
                info: "Mostrando _START_ a _END_ de _TOTAL_",
                paginate: {
                    next: "→",
                    previous: "←"
                }
            }
        });

    });
    // CREAR
    $('#formCrear').submit(function (e) {
        e.preventDefault();

        $.post('ajax/practicas_guardar.php', $(this).serialize(), function (res) {

            if (res.success) {

                let fila = `
                <tr id="fila_${res.id}">
                    <td>${res.nombre}</td>
                    <td><span class="badge badge-info">${res.tipo}</span></td>
                    <td><span class="badge badge-success">Activo</span></td>
                    <td class="text-center">
                        <button class="btn btn-primary btn-sm editar"
                            data-id="${res.id}"
                            data-nombre="${res.nombre}"
                            data-tipo="${res.tipo}"
                            data-activo="1">
                            <i class="fas fa-edit"></i>
                        </button>
                      <button class="btn btn-danger btn-sm btnEliminar" data-id="${res.id}">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `;

                tablaPracticas.row.add($(fila)).draw();

                $('#modalCrear').modal('hide');
                $('#formCrear')[0].reset();

                Swal.fire('Guardado', '', 'success');

            } else {
                Swal.fire('Error', res.message, 'error');
            }

        }, 'json');
    });
    // EDITAR
    $(document).on('click', '.editar', function () {

        $('#edit_id').val($(this).data('id'));
        $('#edit_nombre').val($(this).data('nombre'));
        $('#edit_tipo').val($(this).data('tipo'));
        $('#edit_activo').val($(this).data('activo'));

        $('#modalEditar').modal('show');
    });

    $('#formEditar').submit(function (e) {
        e.preventDefault();

        $.post('ajax/practicas_guardar.php', $(this).serialize(), function (res) {

            if (res.success) {

                let fila = $('#fila_' + res.id);

                fila.find('td:eq(0)').text(res.nombre);
                fila.find('td:eq(1)').html(`<span class="badge badge-info">${res.tipo}</span>`);
                fila.find('td:eq(2)').html(res.activo == 1
                    ? '<span class="badge badge-success">Activo</span>'
                    : '<span class="badge badge-secondary">Inactivo</span>'
                );

                $('#modalEditar').modal('hide');

                Swal.fire('Actualizado', '', 'success');

            } else {
                Swal.fire('Error', res.message, 'error');
            }

        }, 'json');
    });
    // ELIMINAR
    $(document).on('click', '.btnEliminar', function () {

        let id = $(this).data('id');

        Swal.fire({
            title: '¿Eliminar práctica?',
            text: "Esta acción no se puede deshacer",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {

            if (result.isConfirmed) {

                $.ajax({
                    url: 'ajax/practicas_eliminar.php',
                    type: 'POST',
                    data: { id: id },
                    dataType: 'json',
                    success: function (res) {

                        if (res.success) {

                            Swal.fire({
                                icon: 'success',
                                title: 'Eliminado',
                                timer: 1500,
                                showConfirmButton: false
                            });

                            tablaPracticas.row($('#fila_' + id)).remove().draw();

                        } else {

                            Swal.fire('Error', res.message, 'error');

                        }
                    }
                });

            }

        });

    });
</script>