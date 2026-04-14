<?php
require_once __DIR__ . '/../inc/db.php';

$practicas = $pdo->query("SELECT id,nombre FROM practicas WHERE activo=1")->fetchAll(PDO::FETCH_ASSOC);
$profesionales = $pdo->query("SELECT id,nombre,apellido FROM profesionales ORDER BY apellido ASC")->fetchAll(PDO::FETCH_ASSOC);

$repartos = $pdo->query("
    SELECT r.*, p.nombre AS practica, pr.nombre AS profesional, pr.apellido AS profesionalApellido
    FROM practicas_reparto r
    INNER JOIN practicas p ON p.id = r.practica_id
    LEFT JOIN profesionales pr ON pr.id = r.profesional_id
")->fetchAll(PDO::FETCH_ASSOC);
$destinos = $pdo->query("
    SELECT id, nombre, tipo, categoria
    FROM destinos_reparto
    ORDER BY nombre
")->fetchAll(PDO::FETCH_ASSOC);
$tipos = $pdo->query("
    SELECT id, nombre 
    FROM tipos_reparto
    ORDER BY nombre
")->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="row ">

    <!-- TABLA REPARTOS -->
    <div class="col-md-8">
        <div class="card card-outline card-info shadow-sm">
            <div class="card-header d-flex justify-content-between">
                <h3><i class="fas fa-percentage mr-2"></i> Repartos
                    <button class="btn btn-success btn-sm" data-toggle="modal" data-target="#modalReparto">
                        <i class="fas fa-plus"></i> Nuevo
                    </button>
                </h3>
            </div>
            <div class="card-body table-responsive">
                <table id="tablaReparto" class="table table-hover ">
                    <thead class="thead-dark">
                        <tr>
                            <th>Práctica</th>
                            <th>Tipo Paciente</th>
                            <th>Profesional</th>
                            <th>Reglas</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($repartos as $r): ?>
                            <tr id="fila_<?= $r['id'] ?>">
                                <td><?= $r['practica'] ?></td>
                                <td><?= ucfirst($r['tipo_paciente']) ?></td>
                                <td>
                                    <?= trim(($r['profesional'] ?? '') . ' ' . ($r['profesionalApellido'] ?? '')) ?>
                                </td>
                                <td>
                                    <button class="btn btn-info btn-sm verReglas" data-id="<?= $r['id'] ?>">Ver reglas</button>
                                </td>
                                <td class="text-center d-flex gap-2">
                                    <button class="btn btn-success btn-sm editar rounded-circle" data-id="<?= $r['id'] ?>"><i class="fas fa-edit"></i></button>
                                    <button class="btn btn-danger btn-sm eliminar rounded-circle" data-id="<?= $r['id'] ?>"><i class="fas fa-trash"></i></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <!-- TABLA DESTINOS -->
    <div class="col-md-4">
        <div class="card card-outline card-info shadow-sm">
            <div class="card-header d-flex justify-content-between">
                <h3><i class="fas fa-map-marker-alt mr-2"></i> Destinos
                    <button class="btn btn-success btn-sm" data-toggle="modal" data-target="#modalDestino">
                        + Nuevo
                    </button>
                </h3>
            </div>
            <div class="card-body table-responsive">
                <table id="tablaDestinos" class="table table-hover">
                    <thead class="thead-dark">
                        <tr>
                            <th>Nombre</th>
                            <th>Tipo</th>
                            <th>Categoría</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($destinos as $d): ?>
                            <tr id="destino_<?= $d['id'] ?>">
                                <td><?= $d['nombre'] ?></td>
                                <td><?= ucfirst($d['tipo']) ?></td>
                                <td>
                                    <?php if ($d['categoria'] == 'normal'): ?>
                                        <span class="badge badge-secondary">Normal</span>
                                    <?php elseif ($d['categoria'] == 'profesional'): ?>
                                        <span class="badge badge-warning">Profesional</span>
                                    <?php elseif ($d['categoria'] == 'fondo'): ?>
                                        <span class="badge badge-primary">Fondo</span>
                                    <?php endif; ?>
                                </td>
                                <td class="d-flex gap-2">
                                    <button class="btn btn-success btn-sm editarDestino rounded-circle" data-id="<?= $d['id'] ?>"><i class="fas fa-edit"></i></button>
                                    <button class="btn btn-danger btn-sm eliminarDestino rounded-circle" data-id="<?= $d['id'] ?>"><i class="fas fa-trash"></i></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <!-- TABLA TIPOS
    <div class="col-md-4">
        <div class="card card-outline card-info shadow-sm">
            <div class="card-header d-flex justify-content-between">
                <h3><i class="fas fa-list mr-2"></i> Tipos pagos
                    <button class="btn btn-success btn-sm" data-toggle="modal" data-target="#modalTipo">
                        + Nuevo
                    </button>
                </h3>
            </div>
            <div class="card-body">
                <table id="tablaTipos" class="table table-hover">
                    <thead class="thead-dark">
                        <tr>
                            <th>Nombre</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tipos as $t): ?>
                            <tr id="tipo_<?= $t['id'] ?>">
                                <td><?= $t['nombre'] ?></td>
                                <td class="d-flex gap-2">
                                    <button class="btn btn-success btn-sm editarTipo rounded-circle" data-id="<?= $t['id'] ?>"><i class="fas fa-edit"></i></button>
                                    <button class="btn btn-danger btn-sm eliminarTipo rounded-circle" data-id="<?= $t['id'] ?>"><i class="fas fa-trash"></i></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div> -->

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

                        <select name="profesional_id" id="profesional_id" class="form-control mb-3" required>
                            <option value="" disabled selected>Seleccione un profesional</option>
                            <?php foreach ($profesionales as $pr): ?>
                                <option value="<?= $pr['id'] ?>">
                                    <?= $pr['apellido'] . ' ' . $pr['nombre'] ?>
                                </option>
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
    <!-- <div class="modal fade" id="modalTipo">
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
</div> -->
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
                        <select name="tipo" class="form-control mb-2">
                            <option value="ingreso">Ingreso</option>
                            <option value="egreso">Egreso</option>
                        </select>

                        <select name="categoria" class="form-control">
                            <option value="normal">Normal</option>
                            <option value="profesional">Profesional</option>
                            <option value="fondo">Fondo</option>
                        </select>
                    </div>

                    <div class="modal-footer">
                        <button class="btn btn-danger" id="btnGuardarReparto">Guardar</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <script>
        let tablaReparto;
        // Cargamos los datos desde PHP
        let tipos = <?= json_encode($tipos) ?>;
        let destinos = <?= json_encode($destinos) ?>;

        $(document).ready(function() {
            // Inicializar Datatables
            tablaReparto = $('#tablaReparto').DataTable({
                responsive: true,
                autoWidth: false
            });
            $('#tablaDestinos').DataTable({
                responsive: true,
                autoWidth: false
            });
            $('#tablaTipos').DataTable({
                responsive: true,
                autoWidth: false
            });
        });
        /* ===============================
           👁 VER REGLAS
        ================================*/
        $(document).on('click', '.verReglas', function() {

            let id = $(this).attr('data-id');

            if (!id) {
                Swal.fire('Error', 'ID inválido', 'error');
                return;
            }

            $('#tablaReglas tbody').empty();

            $.ajax({
                url: 'ajax/practicas_reparto_get_full.php',
                method: 'GET',
                data: {
                    id: id
                },
                dataType: 'json',

                success: function(res) {

                    if (!res.success) {
                        Swal.fire('Error', res.message || 'Error al cargar reglas', 'error');
                        return;
                    }

                    // 👉 Ocultar controles de edición
                    $('#addRegla').hide();
                    $('#btnGuardarReparto').hide();
                    $('#formReparto select, #formReparto input').hide();
                    $('#practica_id').closest('.mb-2').hide();
                    $('#profesional_id').closest('.mb-3').hide();
                    $('#tipo_paciente').closest('.mb-2').hide();



                    // 👉 Pintar reglas SOLO TEXTO
                    res.reglas.forEach(regla => {

                        let tipo = tipos.find(t => t.id == regla.tipo_id)?.nombre || 'N/A';
                        let destino = destinos.find(d => d.id == regla.destino_id)?.nombre || 'N/A';

                        let badgeTipo = tipo.toLowerCase().includes('fijo') ?
                            '<span class="badge badge-primary">Fijo</span>' :
                            '<span class="badge badge-success">%</span>';

                        $('#tablaReglas tbody').append(`
                    <tr>
                        <td>${badgeTipo} ${tipo}</td>
                        <td>${destino}</td>
                        <td><strong>${regla.valor}</strong></td>
                        <td></td>
                    </tr>
                `);
                    });

                    $('#modalReparto h5').text('Detalle de reglas');
                    $('#modalReparto').modal('show');
                },

                error: function(xhr) {
                    console.error(xhr.responseText);
                    Swal.fire('Error', 'Error de servidor', 'error');
                }
            });
        });
        $('#modalReparto').on('hidden.bs.modal', function() {

            $('#formReparto')[0].reset();
            $('#tablaReglas tbody').empty();

            // 🔥 volver a mostrar todo
            $('#btnGuardarReparto').show();
            $('#addRegla').show();
            $('#practica_id, #profesional_id, #tipo_paciente').show();
            $('#formReparto select, #formReparto input').show();

            $('#practica_id').closest('.mb-2').show();
            $('#profesional_id').closest('.mb-3').show();
            $('#tipo_paciente').closest('.mb-2').show();

            $('#modalReparto h5').text('Configurar reparto');
        });
        /* ===============================
           FUNCIONES AUXILIARES
        ================================*/
        function generarSelectTipos(seleccionado = '') {
            let opciones = tipos.map(t => `<option value="${t.id}" ${t.id == seleccionado ? 'selected' : ''}>${t.nombre}</option>`).join('');
            return `<select class="form-control tipo">${opciones}</select>`;
        }

        function generarSelectDestinos(seleccionado = '') {
            let opciones = destinos.map(d => `<option value="${d.id}" ${d.id == seleccionado ? 'selected' : ''}>${d.nombre}${d.tipo ? ' (' + d.tipo + ')' : ''}</option>`).join('');
            return `<select class="form-control destino">${opciones}</select>`;
        }

        /* ===============================
           GESTIÓN DE TIPOS
        ================================*/
        // Limpiar modal al abrir para "Nuevo"
        // $('[data-target="#modalTipo"]').click(function() {
        //     $('#modalTipo').removeData('edit-id');
        //     $('#formTipo')[0].reset();
        //     $('#modalTipo h5').text('Agregar Tipo');
        // });

        // $(document).on('click', '.editarTipo', function() {
        //     let id = $(this).attr('data-id'); // Usar .attr() es vital aquí
        //     $.get('ajax/obtener_tipo.php', {
        //         id: id
        //     }, function(res) {
        //         if (res.success) {
        //             $('#modalTipo [name="tipo"]').val(res.tipo.nombre);
        //             $('#modalTipo').data('edit-id', id).modal('show');
        //             $('#modalTipo h5').text('Editar Tipo');
        //         }
        //     }, 'json');
        // });

        // $('#formTipo').submit(function(e) {
        //     e.preventDefault();
        //     let editId = $('#modalTipo').data('edit-id');
        //     let url = editId ? 'ajax/editar_tipo.php' : 'ajax/agregar_tipo.php';
        //     let data = {
        //         tipo: $(this).find('[name="tipo"]').val()
        //     };
        //     if (editId) data.id = editId;

        //     $.post(url, data, function(res) {
        //         if (res.success) location.reload();
        //         else Swal.fire('Error', res.message, 'error');
        //     }, 'json');
        // });

        // $(document).on('click', '.eliminarTipo', function() {
        //     let id = $(this).attr('data-id');
        //     Swal.fire({
        //         title: '¿Eliminar?',
        //         icon: 'warning',
        //         showCancelButton: true
        //     }).then(r => {
        //         if (r.isConfirmed) {
        //             $.post('ajax/eliminar_tipo.php', {
        //                 id: id
        //             }, function(res) {
        //                 if (res.success) location.reload();
        //                 else Swal.fire('Error', res.message, 'error');
        //             }, 'json');
        //         }
        //     });
        // });

        /* ===============================
           GESTIÓN DE DESTINOS
        ================================*/
        $('[data-target="#modalDestino"]').click(function() {
            $('#modalDestino').removeData('edit-id');
            $('#formDestino')[0].reset();
            $('#modalDestino h5').text('Agregar Destino');
        });

        $(document).on('click', '.editarDestino', function() {

            let id = $(this).attr('data-id');

            if (!id) {
                console.error("No se encontró data-id en el botón");
                return;
            }

            $.ajax({
                url: 'ajax/obtener_destino.php',
                method: 'GET',
                data: {
                    id: parseInt(id)
                },
                dataType: 'json',

                success: function(res) {

                    if (!res.success) {
                        Swal.fire('Error', res.message, 'error');
                        return;
                    }

                    // 🔥 CARGAR DATOS EN EL MODAL
                    $('#formDestino [name="destino"]').val(res.destino.nombre);
                    $('#formDestino [name="tipo"]').val(res.destino.tipo);
                    $('#formDestino [name="categoria"]').val(res.destino.categoria);

                    // 🔥 GUARDAR ID PARA EDITAR
                    $('#modalDestino').data('edit-id', res.destino.id);

                    // 🔥 CAMBIAR TÍTULO
                    $('#modalDestino h5').text('Editar Destino');

                    // 🔥 ABRIR MODAL
                    $('#modalDestino').modal('show');
                },

                error: function(xhr) {
                    console.error("Error AJAX:", xhr.responseText);
                    Swal.fire('Error', 'Error de servidor', 'error');
                }
            });
        });

        $('#formDestino').submit(function(e) {
            e.preventDefault();
            let editId = $('#modalDestino').data('edit-id');
            let url = editId ? 'ajax/editar_destino.php' : 'ajax/agregar_destino.php';
            let data = {
                destino: $(this).find('[name="destino"]').val(),
                tipo: $(this).find('[name="tipo"]').val(),
                categoria: $(this).find('[name="categoria"]').val()
            };
            if (editId) data.id = editId;

            $.post(url, data, function(res) {
                if (res.success) location.reload();
                else Swal.fire('Error', res.message, 'error');
            }, 'json');
        });

        $(document).on('click', '.eliminarDestino', function() {
            let id = $(this).attr('data-id');
            Swal.fire({
                title: '¿Eliminar?',
                icon: 'warning',
                showCancelButton: true
            }).then(r => {
                if (r.isConfirmed) {
                    $.post('ajax/eliminar_destino.php', {
                        id: id
                    }, function(res) {
                        if (res.success) location.reload();
                        else Swal.fire('Error', res.message, 'error');
                    }, 'json');
                }
            });
        });

        /* ===============================
           REPARTOS (LÓGICA EXISTENTE)
        ================================*/
        $('#addRegla').click(function() {
            $('#tablaReglas tbody').append(`<tr>
            <td>${generarSelectTipos()}</td>
            <td>${generarSelectDestinos()}</td>
            <td><input type="number" step="0.01" class="form-control valor"></td>
            <td><button type="button" class="btn btn-danger btn-sm eliminarRegla"><i class="fas fa-trash"></i></button></td>
        </tr>`);
        });

        $(document).on('click', '.eliminarRegla', function() {
            $(this).closest('tr').remove();
        });

        $(document).on('click', '.editar', function() {
            let id = $(this).attr('data-id');
            $('#tablaReglas tbody').empty();
            $.get('ajax/practicas_reparto_get_full.php', {
                id: id
            }, function(res) {
                if (res.success) {
                    let r = res.reparto;
                    $('#reparto_id').val(r.id);
                    $('#practica_id').val(r.practica_id);
                    $('#profesional_id').val(r.profesional_id);
                    $('#tipo_paciente').val(r.tipo_paciente);
                    res.reglas.forEach(regla => {
                        $('#tablaReglas tbody').append(`<tr>
                        <td>${generarSelectTipos(regla.tipo_id)}</td>
                        <td>${generarSelectDestinos(regla.destino_id)}</td>
                        <td><input type="number" step="0.01" class="form-control valor" value="${regla.valor}"></td>
                        <td><button type="button" class="btn btn-danger btn-sm eliminarRegla"><i class="fas fa-trash"></i></button></td>
                    </tr>`);
                    });
                    $('#modalReparto').modal('show');
                }
            }, 'json');
        });

        $('#formReparto').submit(function(e) {
            e.preventDefault();
            let reglas = [];
            $('#tablaReglas tbody tr').each(function() {
                reglas.push({
                    tipo_id: $(this).find('.tipo').val(),
                    destino_id: $(this).find('.destino').val(),
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
                success: function(res) {
                    if (res.success) location.reload();
                }
            });
        });
    </script>