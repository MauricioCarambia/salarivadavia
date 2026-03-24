<?php
require_once __DIR__ . '/../inc/db.php';

$rand = rand(1, 9999);

$estudios = $pdo->query("
    SELECT id, estudio, valor
    FROM estudio_lab
    ORDER BY estudio ASC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="row">
    <div class="col-12">

        <div class="card card-info card-outline">

            <!-- HEADER -->
            <div class="card-header d-flex justify-content-between">
                <h3 class="card-title">
                    <i class="fa fa-flask"></i> Edición Estudios
                    <a href="./?seccion=estudios_laboratorio&nc=<?= $rand ?>" class="btn btn-secondary btn-sm">
                    Volver
                </a>
                </h3>

                
            </div>

            <!-- BODY -->
            <div class="card-body">

                <div class="table-responsive">
                    <table id="tablaEstudios" class="table table-bordered table-hover datatable">

                        <thead class="thead-dark">
                            <tr>
                                <th>Estudio</th>
                                <th class="text-right">Valor</th>
                                <th width="120">Acciones</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($estudios as $r): ?>
                                <tr data-id="<?= $r['id'] ?>">

                                    <td class="align-middle col-estudio">
                                        <?= htmlspecialchars($r['estudio']) ?>
                                    </td>

                                    <td class="text-right col-valor">
                                        $ <?= rtrim(rtrim(number_format($r['valor'], 2, ',', '.'), '0'), ',') ?>
                                    </td>

                                    <td class="text-center align-middle">
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-success rounded-circle btnEditar"
                                                data-id="<?= $r['id'] ?>" title="Editar">
                                                <i class="fas fa-pencil-alt"></i>
                                            </button>

                                            <button class="btn btn-danger rounded-circle btnEliminar"
                                                data-id="<?= $r['id'] ?>" title="Eliminar">
                                                <i class="fa fa-trash"></i>
                                            </button>
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
</div>

<!-- =========================
MODAL EDIT
========================= -->
<div class="modal fade" id="modalEdit">
    <div class="modal-dialog">
        <div class="modal-content">

            <div class="modal-header bg-info">
                <h5 class="modal-title text-white">
                    <i class="fa fa-edit"></i> Editar estudio
                </h5>
                <button class="close text-white" data-dismiss="modal">&times;</button>
            </div>

            <div class="modal-body">

                <input type="hidden" id="edit_id">

                <div class="form-group">
                    <label>Estudio</label>
                    <input type="text" id="edit_estudio" class="form-control">
                </div>

                <div class="form-group">
                    <label>Valor</label>
                    <input type="text" id="edit_valor" class="form-control">
                </div>

            </div>

            <div class="modal-footer">
                <button class="btn btn-success" id="guardarEdit">
                    <i class="fa fa-save"></i> Guardar
                </button>
            </div>

        </div>
    </div>
</div>
<script>
    $(document).ready(function () {

        let table = $('#tablaEstudios').DataTable({
            pageLength: 25,
            ordering: false,
            language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' }
        });

        // =========================
        // ABRIR MODAL
        // =========================
        $(document).on('click', '.btnEditar', function () {

            let fila = $(this).closest('tr');

            $('#edit_id').val(fila.data('id'));
            $('#edit_estudio').val(fila.find('.col-estudio').text().trim());

            let valorTexto = fila.find('.col-valor').text().replace('$', '').trim();
            valorTexto = valorTexto.replace(/\./g, '').replace(',', '.');

            $('#edit_valor').val(valorTexto);

            $('#modalEdit').modal('show');
        });

        // =========================
        // GUARDAR AJAX
        // =========================
        $('#guardarEdit').click(function () {

            let id = $('#edit_id').val();
            let estudio = $('#edit_estudio').val();
            let valor = $('#edit_valor').val().replace(',', '.');

            $.post('ajax/update_estudio.php', {
                id: id,
                estudio: estudio,
                valor: valor
            }, function (res) {

                try {
                    let r = JSON.parse(res);

                    if (r.status === 'ok') {

                        let fila = $('tr[data-id="' + id + '"]');

                        fila.find('.col-estudio').text(estudio);

                        let formato = parseFloat(valor).toLocaleString('es-AR', {
                            minimumFractionDigits: 0,
                            maximumFractionDigits: 2
                        });

                        fila.find('.col-valor').text('$ ' + formato);

                        table.draw(false);

                        Swal.fire({
                            icon: 'success',
                            title: 'Guardado',
                            text: 'Actualizado correctamente'
                        });

                        $('#modalEdit').modal('hide');

                    } else {
                        Swal.fire('Error', r.msg, 'error');
                    }

                } catch (e) {
                    console.error(res);
                    Swal.fire('Error', 'Respuesta inválida del servidor', 'error');
                }

            });

        });

        // =========================
        // ELIMINAR
        // =========================
        $(document).on('click', '.btnEliminar', function () {

            let id = $(this).data('id');
            let fila = $('tr[data-id="' + id + '"]');
            let nombre = fila.find('.col-estudio').text();

            Swal.fire({
                title: '¿Eliminar estudio?',
                html: `<b>${nombre}</b><br><small>Esta acción no se puede deshacer</small>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonText: 'Cancelar',
                confirmButtonText: 'Sí, eliminar'
            }).then(result => {

                if (result.isConfirmed) {

                    $.post('ajax/delete_estudio.php', { id: id }, function (res) {

                        let r = JSON.parse(res);

                        if (r.status === 'ok') {

                            // 🔥 animación + eliminación real
                            fila.fadeOut(200, function () {
                                table.row(fila).remove().draw(false);
                            });

                            Swal.fire({
                                icon: 'success',
                                title: 'Eliminado',
                                text: 'Se eliminó correctamente'
                            });

                        } else {
                            Swal.fire('Error', r.msg, 'error');
                        }

                    });

                }

            });

        });

    });
</script>