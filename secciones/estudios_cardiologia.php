<?php
require_once __DIR__ . '/../inc/db.php';

$rand = rand(1, 9999);

// Traer todos los registros
$cardiologia = $pdo->query("
    SELECT *
    FROM cardiologia_sur
    ORDER BY Id DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Traer lista de estudios para filtro
$estudios = $pdo->query("SELECT DISTINCT estudio FROM cardiologia_sur ORDER BY estudio ASC")
    ->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="row">
    <div class="col-12">
        <div class="card card-info card-outline">

            <!-- HEADER -->
            <div class="card-header d-flex  align-items-center">
                <h3 class="card-title mb-0">Cardiología del Sur
                    <a href="./?seccion=cardiologia_sur_new&nc=<?= $rand ?>" class="btn btn-info btn-sm ml-2 rounded">
                        <i class="fa fa-plus"></i>
                    </a>
                </h3>

            </div>

            <!-- ACCIONES -->
            <div class="card-header bg-light d-flex justify-content-between align-items-center">

                <div class="d-flex align-items-center">
                    <strong>Total seleccionado:</strong>
                    <span id="total" class="ml-2 text-info font-weight-bold">$0</span>
                </div>



            </div>

            <div class="card-body">

                <!-- FILTROS -->
                <div class="row mb-3">

                    <div class="col-md-3">
                        <label>Filtrar por fecha</label>
                        <input type="date" id="filtro_fecha" class="form-control">
                    </div>

                    <div class="col-md-3 d-flex align-items-end">
                        <div>
                            <input type="checkbox" id="filtro_no_avisados">
                            <label for="filtro_no_avisados" class="mb-0">No avisados</label>
                        </div>
                    </div>
                    <div class="col-md-6 d-flex justify-content-end gap-2">

                        <button class="btn btn-primary btn-sm rounded mr-2" id="btn_imprimir">
                            <i class="fa fa-print"></i> Imprimir seleccionados
                        </button>

                        <button class="btn btn-danger btn-sm rounded mr-2" id="btn_eliminar">
                            <i class="fa fa-trash"></i> Eliminar seleccionados
                        </button>

                        <button class="btn btn-outline-danger btn-sm rounded" id="btn_eliminar_filtrados">
                            <i class="fa fa-filter"></i> Eliminar filtrados
                        </button>

                    </div>
                </div>

                <!-- TABLA -->
                <div class="table-responsive">
                    <table class="table table-bordered table-hover table-striped datatable" style="width:100%">

                        <thead class="thead-dark">
                            <tr>
                                <th style="width:30px">
                                    <input type="checkbox" id="check_all">
                                </th>
                                <th>Fecha</th>
                                <th>Paciente</th>
                                <th>Documento</th>
                                <th>Celular</th>
                                <th>Fecha Nac</th>
                                <th>Dirección</th>
                                <th>Obra Social</th>
                                <th>Estudio</th>
                                <th class="text-right">Pago Médico</th>
                                <th class="text-right">Cobrado</th>
                                <th>Día Turno</th>
                                <th>Aviso</th>
                                <th style="width:120px">Acciones</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($cardiologia as $r): ?>
                                <tr data-id="<?= $r['Id'] ?>" data-fecha="<?= $r['fecha'] ?>"
                                    data-estudio="<?= htmlspecialchars($r['estudio']) ?>"
                                    data-cobrado="<?= $r['cobrado'] ?>" data-aviso="<?= $r['aviso'] ?>"
                                    data-valor="<?= $r['valor'] ?>">

                                    <td>
                                        <input type="checkbox" class="checkbox_cardio">
                                    </td>

                                    <td><?= date('d/m/Y', strtotime($r['fecha'])) ?></td>

                                    <td><?= htmlspecialchars($r['apellido'] . " " . $r['nombre']) ?></td>

                                    <td><?= htmlspecialchars($r['documento']) ?></td>

                                    <td><?= htmlspecialchars($r['celular']) ?></td>
                                    <td>
                                        <?= htmlspecialchars($r['nacimiento']) ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($r['domicilio']) ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($r['obra_social']) ?>
                                    </td>

                                    <td><?= htmlspecialchars($r['estudio']) ?></td>

                                    <td class="text-right">
                                        $ <?= number_format($r['valor'], 0, ',', '.') ?>
                                    </td>

                                    <td class="text-right">
                                        $ <?= number_format($r['cobrado'], 0, ',', '.') ?>
                                    </td>

                                    <td><?= htmlspecialchars($r['turno']) ?></td>

                                    <td>
                                        <?php if ($r['aviso'] === 'Si'): ?>
                                            <span class="badge badge-success">Avisado</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">Pendiente</span>
                                        <?php endif; ?>
                                    </td>

                                    <td class="text-center">
                                        <div class="btn-group">
                                            <!-- <a href="./?seccion=cardiologia_sur_edit&id=<?= $r['Id'] ?>&nc=<?= $rand ?>"
                                                class="btn btn-success btn-sm rounded-circle" title="Editar">
                                                <i class="fa fa-pencil-alt"></i>
                                            </a> -->
                                            <button class="btn btn-success btn-sm rounded-circle btnEditar"
                                                data-id="<?= $r['Id'] ?>" title="Editar">
                                                <i class="fa fa-pencil-alt"></i>
                                            </button>

                                            <a href="./?seccion=cardiologia_sur_delete&id=<?= $r['Id'] ?>&nc=<?= $rand ?>"
                                                class="btn btn-danger btn-sm rounded-circle" title="Eliminar">
                                                <i class="fa fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>

                                </tr>
                            <?php endforeach; ?>
                        </tbody>

                    </table>
                    <div class="modal fade" id="modalEdit">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">

                                <div class="modal-header bg-info">
                                    <h5 class="modal-title text-white">
                                        <i class="fa fa-edit"></i> Editar turno
                                    </h5>
                                    <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
                                </div>

                                <div class="modal-body">

                                    <input type="hidden" id="edit_id">

                                    <div class="row">

                                        <div class="col-md-6 mb-2">
                                            <label>Apellido</label>
                                            <input type="text" id="edit_apellido" class="form-control">
                                        </div>

                                        <div class="col-md-6 mb-2">
                                            <label>Nombre</label>
                                            <input type="text" id="edit_nombre" class="form-control">
                                        </div>

                                        <div class="col-md-4 mb-2">
                                            <label>DNI</label>
                                            <input type="text" id="edit_documento" class="form-control">
                                        </div>

                                        <div class="col-md-4 mb-2">
                                            <label>Celular</label>
                                            <input type="text" id="edit_celular" class="form-control">
                                        </div>

                                        <div class="col-md-4 mb-2">
                                            <label>Fecha Nacimiento</label>
                                            <input type="date" id="edit_nacimiento" class="form-control">
                                        </div>

                                        <div class="col-md-4 mb-2">
                                            <label>Dirección</label>
                                            <input type="text" id="edit_domicilio" class="form-control">
                                        </div>

                                        <div class="col-md-4 mb-2">
                                            <label>Obra Social</label>
                                            <input type="text" id="edit_obra_social" class="form-control">
                                        </div>

                                        <div class="col-md-4 mb-2">
                                            <label>Estudio</label>
                                            <input type="text" id="edit_estudio" class="form-control">
                                        </div>

                                        <div class="col-md-4 mb-2">
                                            <label>Valor</label>
                                            <input type="number" id="edit_valor" class="form-control">
                                        </div>

                                        <div class="col-md-4 mb-2">
                                            <label>Cobrado</label>
                                            <input type="number" id="edit_cobrado" class="form-control">
                                        </div>

                                        <div class="col-md-4 mb-2">
                                            <label>Turno</label>
                                            <input type="text" id="edit_turno" class="form-control">
                                        </div>

                                        <div class="col-md-4 mb-2">
                                            <label>Aviso</label>
                                            <select id="edit_aviso" class="form-control">
                                                <option value="">Seleccionar</option>
                                                <option value="Si">Sí</option>
                                                <option value="No">No</option>
                                            </select>
                                        </div>

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
                </div>

            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function () {

        // =========================
        // INIT DATATABLE
        // =========================
        let table = $('.datatable').DataTable({
            pageLength: 25,
            ordering: false,
            language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' }
        });

        // =========================
        // FILTROS PRO (DataTables)
        // =========================
        $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {

            let fechaFiltro = $('#filtro_fecha').val();
            let noAvisados = $('#filtro_no_avisados').is(':checked');

            let row = table.row(dataIndex).node();

            let fecha = $(row).data('fecha');   // YYYY-MM-DD
            let aviso = $(row).data('aviso');

            // FILTRO FECHA
            if (fechaFiltro && fecha !== fechaFiltro) {
                return false;
            }

            // FILTRO NO AVISADOS
            if (noAvisados && aviso === "Si") {
                return false;
            }

            return true;
        });

        // Ejecutar filtros
        $('#filtro_fecha, #filtro_no_avisados').on('change', function () {
            table.draw();
        });

        // =========================
        // CHECK ALL
        // =========================
        $('#check_all').on('change', function () {
            let checked = $(this).is(':checked');

            $('.checkbox_cardio').prop('checked', checked);
            calcularTotal();
        });

        // =========================
        // TOTAL DINÁMICO
        // =========================
        function calcularTotal() {

            let total = 0;
            let totalCobrado = 0;

            $('.checkbox_cardio:checked').each(function () {
                let fila = $(this).closest('tr');

                let valor = parseFloat(fila.data('valor')) || 0;
                let cobrado = parseFloat(fila.data('cobrado')) || 0;

                total += valor;
                totalCobrado += cobrado;
            });

            $('#total').text(
                'Medico: $ ' + total.toLocaleString('es-AR') +
                ' / Cobrado: $ ' + totalCobrado.toLocaleString('es-AR')
            );
        }

        $(document).on('change', '.checkbox_cardio', calcularTotal);

        // =========================
        // IMPRIMIR SELECCIONADOS
        // =========================
        $('#btn_imprimir').click(function () {

            let filas = [];
            let total = 0;

            $('.checkbox_cardio:checked').each(function () {

                let fila = $(this).closest('tr');

                let data = {
                    fecha: fila.find('td:eq(1)').text(),
                    paciente: fila.find('td:eq(2)').text(),
                    documento: fila.find('td:eq(3)').text(),
                    celular: fila.find('td:eq(4)').text(),
                    estudio: fila.find('td:eq(5)').text(),
                    valor: parseFloat(fila.data('valor')) || 0
                };

                total += data.valor;
                filas.push(data);
            });

            if (!filas.length) {
                Swal.fire('Atención', 'No seleccionaste registros', 'warning');
                return;
            }

            // =========================
            // AGRUPAR POR ESTUDIO
            // =========================
            let agrupado = {};

            filas.forEach(f => {
                if (!agrupado[f.estudio]) agrupado[f.estudio] = [];
                agrupado[f.estudio].push(f);
            });

            let numero = 'LIQ-' + Date.now();

            let contenido = `
    <html>
    <head>
    <title>Liquidación</title>

    <style>
    body { font-family: Arial; margin:40px; color:#333; }

    .header {
        display:flex;
        justify-content:space-between;
        border-bottom:3px solid #007bff;
        margin-bottom:20px;
        padding-bottom:10px;
    }

    .logo { height:60px; }

    .titulo {
        font-size:22px;
        font-weight:bold;
        color:#007bff;
    }

    .box {
        display:flex;
        justify-content:space-between;
        margin-bottom:20px;
        font-size:13px;
    }

    table {
        width:100%;
        border-collapse:collapse;
        margin-top:10px;
    }

    th {
        background:#007bff;
        color:#fff;
        padding:8px;
        font-size:12px;
        text-align: left;
    }

    td {
        padding:6px;
        border-bottom:1px solid #ddd;
        font-size:12px;
        text-align: left;
    }

    .grupo {
        margin-top:20px;
        font-weight:bold;
        background:#f1f1f1;
        padding:6px;
    }

    .total {
        margin-top:25px;
        padding:15px;
        border:2px solid #007bff;
        font-size:18px;
        font-weight:bold;
        display:flex;
        justify-content:space-between;
    }

    .firma {
        margin-top:60px;
        display:flex;
        justify-content:space-between;
    }

    .firma div {
        text-align:center;
        width:40%;
    }
    </style>

    </head>

    <body>

    <div class="header">
        <div>
            <img src="images/logo_blanco.png" class="logo"><br>
            <div class="titulo">Sala Bernardino Rivadavia</div>
            <div>Liquidacion Cardiología del Sur</div>
        </div>
        <div style="text-align:right;">
           
            <strong>Fecha:</strong> ${new Date().toLocaleDateString()}
           
        </div>
    </div>

    <div class="box">
        <div><strong>Registros:</strong>${filas.length}</div>
        <div><strong>Periodo:</strong>__________________</div>
    </div>
    `;

            // =========================
            // TABLAS AGRUPADAS
            // =========================
            for (let estudio in agrupado) {

                contenido += `<div class="grupo">Estudio: ${estudio}</div>`;

                contenido += `
        <table>
        <tr>
        <th>Fecha</th>
        <th>Paciente</th>
        <th>DNI</th>
        <th>Celular</th>
        <th style="text-align:right;">Pago</th>
        </tr>
        `;

                agrupado[estudio].forEach(f => {
                    contenido += `
            <tr>
                <td>${f.fecha}</td>
                <td>${f.paciente}</td>
                <td>${f.documento}</td>
                <td>${f.celular}</td>
                <td style="text-align:right;">$ ${f.valor.toLocaleString('es-AR')}</td>
            </tr>
            `;
                });

                contenido += `</table>`;
            }

            // =========================
            // TOTAL + FIRMA
            // =========================
            contenido += `
    <div class="total">
        <span>Total a pagar:</span>
        <span>$ ${total.toLocaleString('es-AR')}</span>
    </div>

    <div class="firma">
        <div>
            ___________________________<br>
            Firma Profesional
        </div>
        <div>
            ___________________________<br>
            Aclaración
        </div>
    </div>

    </body>
    </html>
    `;

            let w = window.open('', '_blank');
            w.document.write(contenido);
            w.document.close();

            w.onload = () => setTimeout(() => w.print(), 500);

        });

        // =========================
        // ELIMINAR SELECCIONADOS
        // =========================
        $('#btn_eliminar').click(function () {

            let ids = $('.checkbox_cardio:checked')
                .map(function () {
                    return $(this).closest('tr').data('id');
                }).get();

            if (!ids.length) {
                Swal.fire('Atención', 'No seleccionaste registros', 'warning');
                return;
            }

            Swal.fire({
                title: '¿Eliminar seleccionados?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, eliminar'
            }).then(result => {

                if (result.isConfirmed) {

                    $.post('ajax/delete_cardiologia.php', { ids: ids }, function () {
                        Swal.fire('Eliminado', 'Registros eliminados', 'success')
                            .then(() => location.reload());
                    });

                }

            });

        });

        // =========================
        // ELIMINAR FILTRADOS (PRO)
        // =========================
        $('#btn_eliminar_filtrados').click(function () {

            let fechaFiltro = $('#filtro_fecha').val();
            let noAvisados = $('#filtro_no_avisados').is(':checked');

            // 🚨 VALIDACIÓN CLAVE
            if (!fechaFiltro && !noAvisados) {
                Swal.fire(
                    'Atención',
                    'Debes aplicar al menos un filtro (fecha o no avisados)',
                    'warning'
                );
                return;
            }

            let ids = [];

            // SOLO FILAS FILTRADAS
            table.rows({ search: 'applied' }).every(function () {
                let row = this.node();
                ids.push($(row).data('id'));
            });

            if (!ids.length) {
                Swal.fire('Atención', 'No hay registros filtrados', 'warning');
                return;
            }

            Swal.fire({
                title: '¿Eliminar todos los filtrados?',
                text: 'Se eliminarán SOLO los resultados del filtro aplicado',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, eliminar'
            }).then(result => {

                if (result.isConfirmed) {

                    $.post('ajax/delete_cardiologia.php', { ids: ids }, function () {
                        Swal.fire('Eliminado', 'Registros eliminados', 'success')
                            .then(() => location.reload());
                    });

                }

            });

        });
        // =========================
        // ABRIR MODAL Y CARGAR DATOS
        // =========================
        $(document).on('click', '.btnEditar', function () {

            let id = $(this).data('id');

            $.get('ajax/get_cardiologia.php', { id: id }, function (data) {

                let r = JSON.parse(data);

                $('#edit_id').val(r.Id);
                $('#edit_apellido').val(r.apellido);
                $('#edit_nombre').val(r.nombre);
                $('#edit_documento').val(r.documento);
                $('#edit_celular').val(r.celular);
                $('#edit_nacimiento').val(r.nacimiento);
                $('#edit_domicilio').val(r.domicilio);
                $('#edit_obra_social').val(r.obra_social);
                $('#edit_estudio').val(r.estudio);
                $('#edit_valor').val(r.valor);
                $('#edit_cobrado').val(r.cobrado);
                $('#edit_turno').val(r.turno);
                $('#edit_aviso').val(r.aviso);


                $('#modalEdit').modal('show');
            });

        });


        // =========================
        // GUARDAR CAMBIOS
        // =========================
        $('#guardarEdit').click(function () {

            $.ajax({
                url: 'ajax/update_cardiologia.php',
                type: 'POST',
                data: {
                    id: $('#edit_id').val(),
                    apellido: $('#edit_apellido').val(),
                    nombre: $('#edit_nombre').val(),
                    documento: $('#edit_documento').val(),
                    celular: $('#edit_celular').val(),
                    nacimiento: $('#edit_nacimiento').val(),
                    domicilio: $('#edit_domicilio').val(),
                    obra_social: $('#edit_obra_social').val(),
                    estudio: $('#edit_estudio').val(),
                    valor: $('#edit_valor').val(),
                    cobrado: $('#edit_cobrado').val(),
                    turno: $('#edit_turno').val(),
                    aviso: $('#edit_aviso').val(),

                },

                success: function () {

                    let fila = $('tr[data-id="' + $('#edit_id').val() + '"]');

                    // 🔥 ACTUALIZAR DATA (CLAVE PARA FILTRO)
                    fila.data('aviso', $('#edit_aviso').val());
                    fila.data('valor', $('#edit_valor').val());
                    fila.data('cobrado', $('#edit_cobrado').val());

                    // 🔥 ACTUALIZAR COLUMNAS VISUALES
                    fila.find('td:eq(2)').text($('#edit_apellido').val() + ' ' + $('#edit_nombre').val());
                    fila.find('td:eq(3)').text($('#edit_documento').val());
                    fila.find('td:eq(4)').text($('#edit_celular').val());
                    fila.find('td:eq(5)').text($('#edit_nacimiento').val());
                    fila.find('td:eq(6)').text($('#edit_domicilio').val());
                    fila.find('td:eq(7)').text($('#edit_obra_social').val());
                    fila.find('td:eq(8)').text($('#edit_estudio').val());
                    fila.find('td:eq(9)').html('$ ' + parseFloat($('#edit_valor').val()).toLocaleString('es-AR'));
                    fila.find('td:eq(10)').html('$ ' + parseFloat($('#edit_cobrado').val()).toLocaleString('es-AR'));
                    fila.find('td:eq(11)').text($('#edit_turno').val());

                    // 🔥 ACTUALIZAR BADGE
                    let aviso = $('#edit_aviso').val();

                    if (aviso === 'Si') {
                        fila.find('td:eq(12)').html('<span class="badge badge-success">Avisado</span>');
                    } else {
                        fila.find('td:eq(12)').html('<span class="badge badge-danger">Pendiente</span>');
                    }

                    // 🔥 REDIBUJAR TABLA (CLAVE PARA FILTROS)
                    table.draw(false);

                    // 🔥 EFECTO VISUAL PRO
                    fila.addClass('table-success');
                    setTimeout(() => fila.removeClass('table-success'), 1500);

                    Swal.fire('Guardado', 'Actualizado correctamente', 'success');

                    $('#modalEdit').modal('hide');
                }
            });

        });
    });
</script>