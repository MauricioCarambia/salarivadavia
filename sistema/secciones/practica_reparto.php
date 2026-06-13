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

/* =========================================
    🔎 VALIDACIÓN DE REPARTOS VS PRECIOS

    Para cada reparto, calcula el total que
    resultaría de aplicar sus reglas (montos
    fijos + porcentajes sobre el resto) al
    precio configurado para esa práctica y
    tipo de paciente, y lo compara con el
    precio real.
========================================= */
$stmtPrecio = $pdo->prepare("
    SELECT precio
    FROM practicas_precios
    WHERE practica_id = ? AND tipo_paciente = ?
    LIMIT 1
");

$stmtReglas = $pdo->prepare("
    SELECT d.valor, t.nombre AS tipo
    FROM practicas_reparto_detalle d
    INNER JOIN tipos_reparto t ON t.id = d.tipo_id
    WHERE d.reparto_id = ?
");

foreach ($repartos as &$r) {

    $stmtPrecio->execute([$r['practica_id'], $r['tipo_paciente']]);
    $precio = $stmtPrecio->fetchColumn();

    $r['precio'] = $precio !== false ? (float)$precio : null;
    $r['diferencia'] = null;

    if ($r['precio'] === null) {
        continue;
    }

    $stmtReglas->execute([$r['id']]);
    $reglas = $stmtReglas->fetchAll(PDO::FETCH_ASSOC);

    $totalFijos = 0;
    $sumPorcentajes = 0;

    foreach ($reglas as $regla) {
        if ($regla['tipo'] === 'monto fijo') {
            $totalFijos += (float)$regla['valor'];
        } else {
            $sumPorcentajes += (float)$regla['valor'];
        }
    }

    $base = $r['precio'] - $totalFijos;
    $totalReparto = $totalFijos + ($base * $sumPorcentajes / 100);

    $r['diferencia'] = round($r['precio'] - $totalReparto, 2);
}
unset($r);
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
                            <th>Diferencia</th>
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
                                <td>
                                    <?php if ($r['precio'] === null): ?>
                                        <span class="badge badge-secondary" title="No hay precio configurado para esta práctica/tipo de paciente">
                                            Sin precio
                                        </span>
                                    <?php elseif (abs($r['diferencia']) < 0.01): ?>
                                        <span class="badge badge-success">OK</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger"
                                            title="Precio: $<?= number_format($r['precio'], 2) ?> - Reparto no suma el total">
                                            Diff: $<?= number_format($r['diferencia'], 2) ?>
                                        </span>
                                    <?php endif; ?>
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
                                <td><?= ucfirst(strtolower(trim($d['nombre']))) ?></td>
                                <td><?= ucfirst($d['tipo']) ?></td>
                                <td>
                                    <?php if ($d['categoria'] == 'caja'): ?>
                                        <span class="badge badge-secondary">Caja</span>
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

                    <select name="profesional_id" id="profesional_id" class="form-control mb-3 select2" multiple required>
                        <?php foreach ($profesionales as $pr): ?>
                            <option value="<?= $pr['id'] ?>">
                                <?= $pr['apellido'] . ' ' . $pr['nombre'] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select name="tipo_paciente" id="tipo_paciente" class="form-control mt-2 mb-2" required>
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
                        <option value="caja">Caja</option>
                        <option value="profesional">Profesional</option>
                        <option value="fondo">Fondo</option>
                    </select>
                </div>

                <div class="modal-footer">
                    <button type="submit" class="btn btn-success">
                        Guardar
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    let tablaReparto;

    // =====================
    // DATOS PHP (SAFE)
    // =====================
    let tipos = <?= json_encode($tipos ?? []) ?>;
    let destinos = <?= json_encode($destinos ?? []) ?>;

    // =====================
    // INIT
    // =====================
    $(document).ready(function() {


        if ($.fn.DataTable) {

            tablaReparto = $('#tablaReparto').DataTable({
                responsive: true,
                autoWidth: false
            });

            if ($('#tablaDestinos').length) {
                $('#tablaDestinos').DataTable({
                    responsive: true,
                    autoWidth: false
                });
            }
        }
        $('#profesional_id').select2({
            width: '100%',
            placeholder: 'Buscar profesionales',
            allowClear: true
        });
    });


    // ===============================
    // VER REGLAS
    // ===============================
    $(document).on('click', '.verReglas', function() {

        let id = $(this).data('id');
        if (!id) return Swal.fire('Error', 'ID inválido', 'error');

        $('#tablaReglas tbody').empty();

        $.ajax({
            url: 'ajax/practicas_reparto_get_full.php',
            method: 'GET',
            data: {
                id
            },
            dataType: 'json',

            success: function(res) {

                if (!res.success) {
                    return Swal.fire('Error', res.message || 'Error al cargar reglas', 'error');
                }

                $('#addRegla, #btnGuardarReparto').hide();
                $('#formReparto select, #formReparto input').hide();

                (res.reglas || []).forEach(regla => {

                    let tipoObj = tipos.find(t => t.id == regla.tipo_id);
                    let destObj = destinos.find(d => d.id == regla.destino_id);

                    let tipo = tipoObj ? tipoObj.nombre : 'N/A';
                    let destino = destObj ? destObj.nombre : 'N/A';

                    $('#tablaReglas tbody').append(`
                    <tr>
                        <td>${tipo}</td>
                        <td>${destino}</td>
                        <td><strong>${regla.valor}</strong></td>
                        <td></td>
                    </tr>
                `);
                });

                $('#modalReparto h5').text('Detalle de reglas');
                $('#modalReparto').modal('show');
            }
        });
    });


    // ===============================
    // RESET MODAL REPARTO
    // ===============================
    $('#modalReparto').on('hidden.bs.modal', function() {

        $('#formReparto')[0].reset();
        $('#tablaReglas tbody').empty();

        $('#addRegla, #btnGuardarReparto').show();
        $('#formReparto select, #formReparto input').show();

        $('#reparto_id').val('');
        $('#modalReparto h5').text('Configurar reparto');
    });

    $('#modalReparto').on('shown.bs.modal', function() {
        $('#profesional_id').select2({
            width: '100%',
            placeholder: 'Buscar profesional',
            allowClear: true,
            dropdownParent: $('#modalReparto')
        });
    });

    // ===============================
    // HELPERS
    // ===============================
    function generarSelectTipos(sel = '') {
        return `<select class="form-control tipo">
        ${tipos.map(t => `<option value="${t.id}" ${t.id == sel ? 'selected' : ''}>${t.nombre}</option>`).join('')}
    </select>`;
    }

    function generarSelectDestinos(sel = '') {
        return `<select class="form-control destino">
        ${destinos.map(d => `<option value="${d.id}" ${d.id == sel ? 'selected' : ''}>${d.nombre}</option>`).join('')}
    </select>`;
    }


    // ===============================
    // GUARDAR DESTINO (CREATE / EDIT)
    // ===============================
    $('#formDestino').submit(function(e) {
        e.preventDefault();

        let id = $('#modalDestino').data('edit-id') || null;

        let data = {
            id: id,
            nombre: $('#formDestino [name="destino"]').val(),
            tipo: $('#formDestino [name="tipo"]').val(),
            categoria: $('#formDestino [name="categoria"]').val()
        };

        $.ajax({
            url: 'ajax/agregar_destino.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(data),
            dataType: 'json',

            success: function(res) {

                if (!res.success) {
                    return Swal.fire('Error', res.message, 'error');
                }

                Swal.fire({
                    icon: 'success',
                    title: 'Guardado',
                    timer: 800,
                    showConfirmButton: false
                });

                $('#modalDestino').modal('hide');

                // 🔥 RECARGA
                setTimeout(() => location.reload(), 600);
            }
        });
    });


    // ===============================
    // EDITAR DESTINO
    // ===============================
    $(document).on('click', '.editarDestino', function() {

        let id = $(this).data('id');
        if (!id) return;

        $.ajax({
            url: 'ajax/obtener_destino.php',
            method: 'GET',
            data: {
                id
            },
            dataType: 'json',

            success: function(res) {

                if (!res.success) {
                    return Swal.fire('Error', res.message, 'error');
                }

                $('#formDestino [name="destino"]').val(res.destino.nombre);
                $('#formDestino [name="tipo"]').val(res.destino.tipo);
                $('#formDestino [name="categoria"]').val(res.destino.categoria);

                $('#modalDestino').data('edit-id', id);
                $('#modalDestino h5').text('Editar Destino');

                $('#modalDestino').modal('show');
            }
        });
    });


    // ===============================
    // ELIMINAR DESTINO
    // ===============================
    $(document).on('click', '.eliminarDestino', function() {

        let id = $(this).data('id');
        if (!id) return;

        Swal.fire({
            title: '¿Eliminar?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí eliminar'
        }).then(r => {

            if (!r.isConfirmed) return;

            $.post('ajax/eliminar_destino.php', {
                id
            }, function(res) {

                if (!res.success) {
                    return Swal.fire('Error', res.message, 'error');
                }

                // 🔥 RECARGA
                location.reload();
            }, 'json');

        });
    });


    // ===============================
    // AGREGAR REGLA
    // ===============================
    $(document).on('click', '#addRegla', function() {
        $('#tablaReglas tbody').append(`
        <tr>
            <td>${generarSelectTipos()}</td>
            <td>${generarSelectDestinos()}</td>
            <td><input type="number" step="0.01" class="form-control valor"></td>
            <td><button type="button" class="btn btn-danger btn-sm eliminarRegla">X</button></td>
        </tr>
    `);
    });


    // ===============================
    // ELIMINAR REGLA
    // ===============================
    $(document).on('click', '.eliminarRegla', function() {
        $(this).closest('tr').remove();
    });


    // ===============================
    // EDITAR REPARTO
    // ===============================
    $(document).on('click', '.editar', function() {

        let id = $(this).data('id');
        if (!id) return;

        $('#tablaReglas tbody').empty();

        $.get('ajax/practicas_reparto_get_full.php', {
            id
        }, function(res) {

            if (!res.success) return;

            let r = res.reparto;

            $('#reparto_id').val(r.id);
            $('#practica_id').val(r.practica_id);
            $('#profesional_id').val(r.profesional_id);
            $('#tipo_paciente').val(r.tipo_paciente);

            (res.reglas || []).forEach(regla => {

                $('#tablaReglas tbody').append(`
                <tr>
                    <td>${generarSelectTipos(regla.tipo_id)}</td>
                    <td>${generarSelectDestinos(regla.destino_id)}</td>
                    <td><input type="number" step="0.01" class="form-control valor" value="${regla.valor}"></td>
                    <td><button type="button" class="btn btn-danger btn-sm eliminarRegla">X</button></td>
                </tr>
            `);
            });

            $('#modalReparto').modal('show');
        }, 'json');
    });


    // ===============================
    // ELIMINAR REPARTO
    // ===============================
    $(document).on('click', '.eliminar', function() {

        let id = $(this).data('id');
        if (!id) return;

        Swal.fire({
            title: '¿Eliminar?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí'
        }).then(r => {

            if (!r.isConfirmed) return;

            $.post('ajax/practicas_reparto_eliminar.php', {
                id
            }, function(res) {

                if (!res.success) {
                    return Swal.fire('Error', res.message, 'error');
                }

                // 🔥 RECARGA
                location.reload();

            }, 'json');
        });
    });

// ===============================
// GUARDAR REPARTO
// ===============================
$('#formReparto').submit(function (e) {

    e.preventDefault();

    let reglas = [];

    $('#tablaReglas tbody tr').each(function () {
        reglas.push({
            tipo_id: $(this).find('.tipo').val(),
            destino_id: $(this).find('.destino').val(),
            valor: $(this).find('.valor').val()
        });
    });

    // ===========================
    // PROFESIONALES (MÚLTIPLES)
    // ===========================
    let profesional_ids = $('#profesional_id').val() || [];

    // si no es array lo forzamos
    if (!Array.isArray(profesional_ids)) {
        profesional_ids = [profesional_ids];
    }

    // limpiar valores vacíos
    profesional_ids = profesional_ids.filter(id => id && id !== "");

    let data = {
        id: $('#reparto_id').val(),
        practica_id: $('#practica_id').val(),
        profesional_ids: profesional_ids,
        tipo_paciente: $('#tipo_paciente').val(),
        reglas: reglas
    };

    $.ajax({
        url: 'ajax/practicas_reparto_guardar.php',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(data),
        dataType: 'json',

        success: function (res) {

            if (res.success) {
                location.reload();
            } else {
                alert(res.message || 'Error al guardar');
            }
        },

        error: function (xhr) {
            console.error(xhr.responseText);
            alert('Error de servidor');
        }
    });
});
</script>