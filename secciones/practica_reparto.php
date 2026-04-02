<?php
require_once "inc/db.php";

$practicas = $pdo->query("SELECT id,nombre FROM practicas WHERE activo=1")->fetchAll(PDO::FETCH_ASSOC);
$profesionales = $pdo->query("SELECT id,nombre,apellido FROM profesionales")->fetchAll(PDO::FETCH_ASSOC);

$repartos = $pdo->query("
    SELECT r.*, p.nombre AS practica, pr.nombre AS profesional, pr.apellido AS profesionalApellido
    FROM practicas_reparto r
    INNER JOIN practicas p ON p.id = r.practica_id
    LEFT JOIN profesionales pr ON pr.id = r.profesional_id
")->fetchAll(PDO::FETCH_ASSOC);
$destinos = $pdo->query("SELECT nombre FROM destinos_reparto")->fetchAll(PDO::FETCH_COLUMN);
$tipos = $pdo->query("SELECT nombre FROM tipos_reparto")->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="card card-outline card-info shadow-sm">
    <div class="card-header d-flex justify-content-between">
        <h3><i class="fas fa-percentage mr-2"></i> Repartos


            <button class="btn btn-success btn-sm" data-toggle="modal" data-target="#modalReparto">
                <i class="fas fa-plus"></i> Nuevo
            </button>
            <button type="button" class="btn btn-sm btn-secondary" data-toggle="modal" data-target="#modalTipo">
                + Tipo
            </button>
            <button type="button" class="btn btn-sm btn-secondary" data-toggle="modal" data-target="#modalDestino">
                + Destino
            </button>
        </h3>
    </div>

    <div class="card-body">

        <table id="tablaReparto" class="table table-hover">
            <thead>
                <tr>
                    <th>Práctica</th>
                    <th>Tipo Paciente</th> <!-- nueva columna -->
                    <th>Profesional</th>
                    <th>Reglas</th>
                    <th width="120">Acciones</th>
                </tr>
            </thead>
            <tbody>

                <?php foreach ($repartos as $r): ?>
                    <tr id="fila_<?= $r['id'] ?>">

                        <td><?= $r['practica'] ?></td>
                        <td><?= ucfirst($r['tipo_paciente']) ?></td> <!-- tipo paciente -->
                        <td>
                            <?= $r['profesional'] ? $r['profesional'] . ' ' . ($r['profesionalApellido'] ?? '') : 'General' ?>
                        </td>

                        <td>
                            <button class="btn btn-info btn-sm verReglas" data-id="<?= $r['id'] ?>">
                                Ver reglas
                            </button>
                        </td>
                        <td class="text-center d-flex justify-content-center gap-2">
                            <button class="btn btn-success btn-sm rounded-circle editar" data-id="<?= $r['id'] ?>">
                                <i class="fas fa-edit"></i>
                            </button>

                            <button class="btn btn-danger btn-sm rounded-circle eliminar" data-id="<?= $r['id'] ?>">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>

                    </tr>
                <?php endforeach; ?>

            </tbody>
        </table>

    </div>
</div>
<div class="modal fade" id="modalReparto">
    <div class="modal-dialog modal-lg">
        <form id="formReparto">

            <input type="hidden" name="id" id="reparto_id">

            <div class="modal-content">

                <div class="modal-header bg-info">
                    <h5>Configurar reparto</h5>
                    <button class="close" data-dismiss="modal">&times;</button>
                </div>

                <div class="modal-body">

                    <select name="practica_id" id="practica_id" class="form-control mb-2" required>
                        <option value="">Práctica</option>
                        <?php foreach ($practicas as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= $p['nombre'] ?></option>
                        <?php endforeach; ?>
                    </select>

                    <select name="profesional_id" id="profesional_id" class="form-control mb-3">
                        <option value="">General</option>
                        <?php foreach ($profesionales as $pr): ?>
                            <option value="<?= $pr['id'] ?>"><?= $pr['apellido'] . ' ' . $pr['nombre'] ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="tipo_paciente" id="tipo_paciente" class="form-control mb-2" required>
                        <option value="particular">Particular</option>
                        <option value="socio">Socio</option>
                    </select>
                    <table class="table table-sm" id="tablaReglas">
                        <thead>
                            <tr>
                                <th>Tipo</th>
                                <th>Destino</th>
                                <th>Valor</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>

                    <button type="button" class="btn btn-success btn-sm" id="addRegla">
                        <i class="fas fa-plus"></i> Agregar regla
                    </button>

                </div>

                <div class="modal-footer">
                    <button class="btn btn-danger">Guardar</button>
                </div>

            </div>

        </form>
    </div>
</div>
<div class="modal fade" id="modalTipo">
    <div class="modal-dialog">
        <form id="formTipo">
            <div class="modal-content">
                <div class="modal-header bg-info">
                    <h5>Agregar Tipo</h5>
                    <button class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <input type="text" class="form-control" name="tipo" placeholder="Nombre del tipo" required>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-success">Guardar</button>
                </div>
            </div>
        </form>
    </div>
</div>
<div class="modal fade" id="modalDestino">
    <div class="modal-dialog">
        <form id="formDestino">
            <div class="modal-content">
                <div class="modal-header bg-info">
                    <h5>Agregar Destino</h5>
                    <button class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <input type="text" class="form-control" name="destino" placeholder="Nombre del destino" required>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-success">Guardar</button>
                </div>
            </div>
        </form>
    </div>
</div>
<script>
    let tabla;
    let tipos = <?= json_encode($tipos) ?>;
    let destinos = <?= json_encode($destinos) ?>;
    $(document).ready(function () {

        tabla = $('#tablaReparto').DataTable({
            responsive: true,
            autoWidth: false
        });

    });
    function generarSelectTipos(seleccionado = '') {
        let opciones = tipos.map(t => `<option value="${t}" ${t === seleccionado ? 'selected' : ''}>${t}</option>`).join('');
        return `<select class="form-control tipo">${opciones}</select>`;
    }

    function generarSelectDestinos(seleccionado = '') {
        let opciones = destinos.map(d => `<option value="${d}" ${d === seleccionado ? 'selected' : ''}>${d}</option>`).join('');
        return `<select class="form-control destino">${opciones}</select>`;
    }
    /* AGREGAR REGLA */
    $('#addRegla').click(function () {
        let fila = `
    <tr>
        <td>${generarSelectTipos()}</td>
        <td>${generarSelectDestinos()}</td>
        <td><input type="number" class="form-control valor"></td>
        <td><button type="button" class="btn btn-danger btn-sm eliminarRegla"><i class="fas fa-trash"></i></button></td>
    </tr>`;
        $('#tablaReglas tbody').append(fila);
    });
    /* EDITAR */
    $(document).on('click', '.editar', function () {

        let id = $(this).data('id');

        // limpiar antes de cargar
        $('#tablaReglas tbody').html('');
        $('#formReparto')[0].reset();

        $.get('ajax/practicas_reparto_get_full.php', { id }, function (res) {

            if (res.success) {

                let r = res.reparto;

                // cargar cabecera
                $('#reparto_id').val(r.id);
                $('#practica_id').val(r.practica_id);
                $('#profesional_id').val(r.profesional_id);
                $('#tipo_paciente').val(r.tipo_paciente);

                // cargar reglas
                res.reglas.forEach(regla => {

                    let fila = `
<tr>
    <td>${generarSelectTipos(regla.tipo)}</td>
    <td>${generarSelectDestinos(regla.destino)}</td>
    <td><input type="number" class="form-control valor" value="${regla.valor}"></td>
    <td><button type="button" class="btn btn-danger btn-sm eliminarRegla"><i class="fas fa-trash"></i></button></td>
</tr>`;

                    $('#tablaReglas tbody').append(fila);

                });

                // abrir modal
                $('#modalReparto').modal('show');

            }

        }, 'json');

    });
    $('#modalReparto').on('show.bs.modal', function () {
        if (!$('#reparto_id').val()) {
            $('#tablaReglas tbody').html('');
        }
    });
    /* ELIMINAR REGLA */
    $(document).on('click', '.eliminarRegla', function () {
        $(this).closest('tr').remove();
    });

    /* GUARDAR */
    $('#formReparto').submit(function (e) {
        e.preventDefault();

        let reglas = [];

        $('#tablaReglas tbody tr').each(function () {

            reglas.push({
                tipo: $(this).find('.tipo').val(),
                destino: $(this).find('.destino').val(),
                valor: $(this).find('.valor').val()
            });

        });

        let data = {
            id: $('#reparto_id').val(),
            practica_id: $('#practica_id').val(),
            profesional_id: $('#profesional_id').val(),
            tipo_paciente: $('#tipo_paciente').val(),
            reglas: reglas
        };

        $.ajax({
            url: 'ajax/practicas_reparto_guardar.php',
            method: 'POST',
            data: JSON.stringify(data),
            contentType: 'application/json',
            success: function (res) {

                if (res.success) {
                    Swal.fire('Guardado', '', 'success').then(() => location.reload());
                }

            }
        });

    });

    /* ELIMINAR */
    $(document).on('click', '.eliminar', function () {

        let id = $(this).data('id');

        Swal.fire({
            title: '¿Eliminar?',
            text: 'Esta acción no se puede deshacer',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Sí, eliminar'
        }).then(r => {

            if (r.isConfirmed) {

                $.ajax({
                    url: 'ajax/practicas_reparto_eliminar.php',
                    type: 'POST',
                    data: { id: id },
                    dataType: 'json',

                    success: function (res) {

                        if (res.success) {

                            tabla.row($('#fila_' + id)).remove().draw();

                            Swal.fire({
                                icon: 'success',
                                title: 'Eliminado',
                                timer: 1500,
                                showConfirmButton: false
                            });

                        } else {
                            Swal.fire('Error', res.message, 'error');
                        }

                    },

                    error: function (xhr) {
                        console.log(xhr.responseText);
                        Swal.fire('Error', 'Fallo AJAX', 'error');
                    }

                });

            }

        });

    });
    /* VER REGLAS */
    $(document).on('click', '.verReglas', function () {

        let id = $(this).data('id');

        $.get('ajax/practicas_reparto_get.php', { id }, function (res) {

            if (res.success) {

                let html = '';

                res.reglas.forEach(r => {

                    html += `
                    <tr>
                        <td>${r.tipo == 'porcentaje' ? '%' : '$'}</td>
                        <td>${r.destino}</td>
                        <td>${r.valor}</td>
                    </tr>
                `;

                });

                Swal.fire({
                    title: 'Reglas de reparto',
                    html: `
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Tipo</th>
                                <th>Destino</th>
                                <th>Valor</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${html}
                        </tbody>
                    </table>
                `,
                    width: 600
                });

            }

        }, 'json');

    });
    $('#formTipo').submit(function (e) {
        e.preventDefault();
        let tipo = $(this).find('[name="tipo"]').val();
        $.post('ajax/agregar_tipo.php', { tipo }, function (res) {
            if (res.success) {
                tipos.push(tipo); // actualizar array JS
                $('#practica_id, #tablaReglas select.tipo').append(`<option value="${tipo}">${tipo}</option>`);
                $('#modalTipo').modal('hide');
                Swal.fire('Agregado', '', 'success');
            } else {
                Swal.fire('Error', res.message, 'error');
            }
        }, 'json');
    });

    $('#formDestino').submit(function (e) {
        e.preventDefault();
        let destino = $(this).find('[name="destino"]').val();
        $.post('ajax/agregar_destino.php', { destino }, function (res) {
            if (res.success) {
                destinos.push(destino); // actualizar array JS
                $('#tablaReglas select.destino').append(`<option value="${destino}">${destino}</option>`);
                $('#modalDestino').modal('hide');
                Swal.fire('Agregado', '', 'success');
            } else {
                Swal.fire('Error', res.message, 'error');
            }
        }, 'json');
    });
</script>