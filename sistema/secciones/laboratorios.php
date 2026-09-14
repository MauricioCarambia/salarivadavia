<?php
require_once __DIR__ . '/../inc/db.php';

if (empty($_SESSION['login'])) {
    header("Location: login.php");
    exit;
}

if (empty($_SESSION['es_admin']) && !in_array('estudios', $_SESSION['accesos'] ?? [])) {
    die('<div class="alert alert-danger">No tenés permisos para acceder a esta sección</div>');
}

$rand = rand(1, 9999);
?>

<div class="row">
    <div class="col-12">

        <div class="card card-info card-outline">

            <!-- HEADER -->
            <div class="card-header d-flex justify-content-between">
                <h3 class="card-title">
                    <i class="fas fa-hospital"></i> Laboratorios
                </h3>

                <a href="./?seccion=estudios_laboratorio&nc=<?= $rand ?>" class="btn btn-secondary btn-sm">
                    Volver
                </a>
            </div>

            <!-- FORM -->
            <div class="card-body">

                <div class="alert alert-success" id="modo_nuevo">
                    <i class="fa fa-plus-circle"></i> Cargando un <b>laboratorio nuevo</b>
                </div>

                <div class="alert alert-warning" id="modo_edicion" style="display:none;">
                    <i class="fa fa-pencil-alt"></i> Editando el laboratorio <b id="modo_edicion_nombre"></b>
                    (se va a <b>actualizar</b>, no se va a crear uno nuevo)
                </div>

                <form id="formLaboratorio">

                    <input type="hidden" name="id" id="lab_id">

                    <div class="row">

                        <div class="col-md-4 form-group">
                            <label>Nombre <span class="text-danger">*</span></label>
                            <input type="text" name="nombre" id="lab_nombre" class="form-control" required>
                        </div>

                        <div class="col-md-4 form-group">
                            <label>Dirección</label>
                            <input type="text" name="direccion" id="lab_direccion" class="form-control">
                        </div>

                        <div class="col-md-4 form-group">
                            <label>Teléfono</label>
                            <input type="text" name="telefono" id="lab_telefono" class="form-control">
                        </div>

                    </div>

                    <div class="text-right">
                        <button type="button" id="btnCancelarEdit" class="btn btn-secondary" style="display:none;">
                            Cancelar edición / Cargar uno nuevo
                        </button>
                        <button type="submit" class="btn btn-success" id="btnGuardarLab">
                            <i class="fa fa-save"></i> Guardar
                        </button>
                    </div>

                </form>

                <hr>

                <div class="table-responsive">
                    <table id="tablaLaboratorios" class="table table-hover">
                        <thead class="thead-dark">
                            <tr>
                                <th>Nombre</th>
                                <th>Dirección</th>
                                <th>Teléfono</th>
                                <th>Estudios cargados</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function () {

        cargarLaboratorios();

        // =========================
        // MODO NUEVO / EDICIÓN
        // =========================
        function setModoNuevo() {

            $('#lab_id').val('');
            $('#modo_nuevo').show();
            $('#modo_edicion').hide();
            $('#btnCancelarEdit').hide();
            $('#btnGuardarLab').html('<i class="fa fa-save"></i> Guardar');
        }

        function setModoEdicion(nombre) {

            $('#modo_nuevo').hide();
            $('#modo_edicion_nombre').text(nombre);
            $('#modo_edicion').show();
            $('#btnCancelarEdit').show();
            $('#btnGuardarLab').html('<i class="fa fa-save"></i> Guardar cambios');
        }

        function mostrarErrorAjax(jqXHR) {

            let msg = 'No se pudo completar la acción. Probá recargar la página e intentar de nuevo.';

            try {
                let r = JSON.parse(jqXHR.responseText);
                if (r && r.message) msg = r.message;
            } catch (e) {}

            Swal.fire('Error', msg, 'error');
        }

        function cargarLaboratorios() {

            $.get('ajax/laboratorio_listar.php', function (data) {

                let html = '';

                data.forEach(l => {

                    html += `
                    <tr data-id="${l.id}">
                        <td>${l.nombre}</td>
                        <td>${l.direccion ?? ''}</td>
                        <td>${l.telefono ?? ''}</td>
                        <td>${l.total_estudios}</td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-success rounded-circle btnEditarLab"
                                    data-id="${l.id}"
                                    data-nombre="${l.nombre}"
                                    data-direccion="${l.direccion ?? ''}"
                                    data-telefono="${l.telefono ?? ''}"
                                    title="Editar">
                                    <i class="fas fa-pencil-alt"></i>
                                </button>
                                <button class="btn btn-danger rounded-circle btnEliminarLab" data-id="${l.id}" title="Eliminar">
                                    <i class="fa fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    `;

                });

                $('#tablaLaboratorios tbody').html(html);

            }, 'json');
        }

        // =========================
        // GUARDAR / EDITAR
        // =========================
        $('#formLaboratorio').submit(function (e) {

            e.preventDefault();

            let esEdicion = !!$('#lab_id').val();

            $.post('ajax/laboratorio_guardar.php', $(this).serialize())
                .done(function (res) {

                    if (res.success) {

                        $('#formLaboratorio')[0].reset();
                        setModoNuevo();

                        Swal.fire({
                            icon: 'success',
                            title: 'Guardado',
                            text: esEdicion ? 'Laboratorio actualizado correctamente' : 'Laboratorio nuevo creado correctamente'
                        });

                        cargarLaboratorios();

                    } else {
                        Swal.fire('Error', res.message, 'error');
                    }

                })
                .fail(mostrarErrorAjax);

        });

        // =========================
        // EDITAR
        // =========================
        $(document).on('click', '.btnEditarLab', function () {

            $('#lab_id').val($(this).data('id'));
            $('#lab_nombre').val($(this).data('nombre'));
            $('#lab_direccion').val($(this).data('direccion'));
            $('#lab_telefono').val($(this).data('telefono'));

            setModoEdicion($(this).data('nombre'));

            $('html, body').animate({ scrollTop: 0 }, 300);
        });

        $('#btnCancelarEdit').click(function () {
            $('#formLaboratorio')[0].reset();
            setModoNuevo();
        });

        // =========================
        // ELIMINAR
        // =========================
        $(document).on('click', '.btnEliminarLab', function () {

            let id = $(this).data('id');
            let fila = $(this).closest('tr');
            let nombre = fila.find('td').first().text();

            Swal.fire({
                title: '¿Eliminar laboratorio?',
                html: `<b>${nombre}</b><br><small>Esta acción no se puede deshacer</small>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonText: 'Cancelar',
                confirmButtonText: 'Sí, eliminar'
            }).then(result => {

                if (result.isConfirmed) {

                    $.post('ajax/laboratorio_eliminar.php', { id: id })
                        .done(function (res) {

                            if (res.success) {

                                fila.fadeOut(200, function () { fila.remove(); });

                                Swal.fire({ icon: 'success', title: 'Eliminado', text: 'Se eliminó correctamente' });

                            } else {
                                Swal.fire('Error', res.message, 'error');
                            }

                        })
                        .fail(mostrarErrorAjax);

                }

            });

        });

    });
</script>
