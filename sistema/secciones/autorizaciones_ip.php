<?php
require_once __DIR__ . '/../inc/db.php';

if (empty($_SESSION['login'])) {
    header("Location: login.php");
    exit;
}

if (empty($_SESSION['es_admin']) && !in_array('autorizaciones_ip', $_SESSION['accesos'] ?? [])) {
    die('<div class="alert alert-danger">No tenés permisos para acceder a esta sección</div>');
}

$empleados = $pdo->query("
    SELECT Id, nombre, usuario
    FROM empleados
    ORDER BY nombre ASC
")->fetchAll(PDO::FETCH_ASSOC);

$profesionales = $pdo->query("
    SELECT Id, nombre, apellido
    FROM profesionales
    ORDER BY apellido ASC
")->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->query("
    SELECT a.id, a.tipo_usuario, a.usuario_id, a.expira_en, a.creado_en,
        CASE a.tipo_usuario
            WHEN 'empleado' THEN e.nombre
            WHEN 'profesional' THEN CONCAT(p.nombre, ' ', p.apellido)
        END AS usuario_nombre
    FROM autorizaciones_ip a
    LEFT JOIN empleados e ON a.tipo_usuario = 'empleado' AND e.Id = a.usuario_id
    LEFT JOIN profesionales p ON a.tipo_usuario = 'profesional' AND p.Id = a.usuario_id
    ORDER BY a.expira_en DESC
");
$autorizaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="card card-info card-outline">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-globe"></i> Autorizaciones de acceso remoto</h3>
    </div>

    <div class="card-body">

        <p class="text-muted">
            Permite que un empleado o profesional puntual ingrese al sistema desde fuera de la red
            de la clínica durante un tiempo limitado.
        </p>

        <form id="formAutorizacion" class="form-row align-items-end mb-4">
            <?= csrf_field() ?>

            <div class="form-group col-md-3">
                <label>Tipo de usuario</label>
                <select name="tipo_usuario" id="tipoUsuario" class="form-control" required>
                    <option value="">Seleccionar...</option>
                    <option value="empleado">Empleado</option>
                    <option value="profesional">Profesional</option>
                </select>
            </div>

            <div class="form-group col-md-4">
                <label>Usuario</label>
                <select name="usuario_id" id="usuarioId" class="form-control" required disabled>
                    <option value="">Seleccionar tipo primero...</option>
                </select>
            </div>

            <div class="form-group col-md-3">
                <label>Duración</label>
                <select name="horas" class="form-control" required>
                    <option value="4">4 horas</option>
                    <option value="8">8 horas</option>
                    <option value="24" selected>24 horas</option>
                    <option value="48">2 días</option>
                    <option value="168">7 días</option>
                </select>
            </div>

            <div class="form-group col-md-2">
                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-plus"></i> Autorizar
                </button>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-bordered table-striped datatable">
                <thead>
                    <tr>
                        <th>Tipo</th>
                        <th>Usuario</th>
                        <th>Otorgada</th>
                        <th>Vence</th>
                        <th>Estado</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($autorizaciones as $a): ?>
                        <?php $vigente = strtotime($a['expira_en']) > time(); ?>
                        <tr>
                            <td><?= $a['tipo_usuario'] === 'empleado' ? 'Empleado' : 'Profesional' ?></td>
                            <td><?= htmlspecialchars($a['usuario_nombre'] ?? '-') ?></td>
                            <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($a['creado_en']))) ?></td>
                            <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($a['expira_en']))) ?></td>
                            <td>
                                <?php if ($vigente): ?>
                                    <span class="badge badge-success">Vigente</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">Vencida</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($vigente): ?>
                                    <button class="btn btn-danger btn-sm btnRevocar" data-id="<?= $a['id'] ?>">
                                        <i class="fas fa-ban"></i> Revocar
                                    </button>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    </div>
</div>

<script>
    const empleadosLista = <?= json_encode($empleados, JSON_UNESCAPED_UNICODE) ?>;
    const profesionalesLista = <?= json_encode($profesionales, JSON_UNESCAPED_UNICODE) ?>;

    $(document).ready(function() {

        initDataTable($('.datatable'), {
            order: [
                [3, "desc"]
            ]
        });

        $('#tipoUsuario').on('change', function() {
            const tipo = $(this).val();
            const $select = $('#usuarioId');

            $select.empty().prop('disabled', true);

            if (tipo === 'empleado') {
                $select.append('<option value="">Seleccionar...</option>');
                empleadosLista.forEach(e => {
                    $select.append(`<option value="${e.Id}">${e.nombre} (${e.usuario})</option>`);
                });
                $select.prop('disabled', false);
            } else if (tipo === 'profesional') {
                $select.append('<option value="">Seleccionar...</option>');
                profesionalesLista.forEach(p => {
                    $select.append(`<option value="${p.Id}">${p.apellido} ${p.nombre}</option>`);
                });
                $select.prop('disabled', false);
            } else {
                $select.append('<option value="">Seleccionar tipo primero...</option>');
            }
        });

        $('#formAutorizacion').on('submit', function(e) {
            e.preventDefault();

            $.post('ajax/autorizacion_ip_crear.php', $(this).serialize())
                .done(res => {
                    if (res.ok) {
                        Swal.fire('Listo', res.mensaje, 'success').then(() => location.reload());
                    } else {
                        Swal.fire('Error', res.error || 'No se pudo otorgar la autorización', 'error');
                    }
                })
                .fail(() => Swal.fire('Error', 'No se pudo otorgar la autorización', 'error'));
        });

        $('.btnRevocar').on('click', function() {
            const id = $(this).data('id');

            Swal.fire({
                title: '¿Revocar esta autorización?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, revocar',
                cancelButtonText: 'Cancelar'
            }).then(result => {
                if (!result.isConfirmed) return;

                $.post('ajax/autorizacion_ip_revocar.php', {
                        id: id,
                        csrf_token: $('meta[name="csrf-token"]').attr('content')
                    })
                    .done(res => {
                        if (res.ok) {
                            Swal.fire('Listo', res.mensaje, 'success').then(() => location.reload());
                        } else {
                            Swal.fire('Error', res.error || 'No se pudo revocar', 'error');
                        }
                    })
                    .fail(() => Swal.fire('Error', 'No se pudo revocar', 'error'));
            });
        });
    });
</script>
