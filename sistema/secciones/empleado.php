<?php
$mensaje = '';

if (!isset($_SESSION['login'])) {
    header("Location: login.php");
    exit;
}

// 🔥 PERMISOS
if (empty($_SESSION['es_admin']) && !in_array('gestionar_roles', $_SESSION['accesos'] ?? [])) {
    die('<div class="alert alert-danger">No tenés permisos para acceder a esta sección</div>');
}

$user_id = $_SESSION['user_id'] ?? 0;
$es_admin = !empty($_SESSION['es_admin']);

// =============================
// EMPLEADOS
// =============================
$stmt = $conexion->prepare("
    SELECT e.Id, e.usuario, e.activo,e.nombre, e.rol_id, r.nombre AS rol 
    FROM empleados e 
    LEFT JOIN roles r ON e.rol_id = r.id
    ORDER BY e.usuario ASC
");
$stmt->execute();
$empleados = $stmt->fetchAll(PDO::FETCH_ASSOC);

// =============================
// ROLES
// =============================
$stmt = $conexion->prepare("
    SELECT 
        r.id, 
        r.nombre AS rol_nombre,
        COALESCE(GROUP_CONCAT(a.nombre ORDER BY a.nombre SEPARATOR ', '), '') AS accesos
    FROM roles r
    LEFT JOIN roles_accesos ra ON r.id = ra.rol_id
    LEFT JOIN accesos a ON ra.acceso_id = a.id
    GROUP BY r.id
    ORDER BY r.nombre ASC
");
$stmt->execute();
$roles_con_accesos = $stmt->fetchAll(PDO::FETCH_ASSOC);
$stmt = $conexion->prepare("SELECT Id, nombre FROM accesos ORDER BY nombre ASC");
$stmt->execute();
$accesos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid">

    <div class="row mb-3">
        <div class="col-12">
            <h3>
                Gestión de Roles
                <a href="./?seccion=rol_new&nc=<?= $rand ?>" class="btn btn-primary btn-sm ml-2">
                    <i class="fa fa-plus"></i> Nuevo rol
                </a>
            </h3>
        </div>
    </div>

    <div class="row">

        <!-- ================= EMPLEADOS ================= -->
        <div class="col-md-6">
            <div class="card card-outline card-info">
                <div class="card-header">
                    <h3 class="card-title">Empleados</h3>
                </div>

                <div class="card-body table-responsive">
                    <table class="table table-hover tabla-empleados">
                        <thead class="thead-dark">
                            <tr>
                                <th>Nombre</th>
                                <th>Rol</th>
                                <th width="220">Acciones</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($empleados as $emp): ?>

                                <?php
                                $disabled = !$emp['activo'] ? 'disabled' : '';
                                $rolNombre = !empty($emp['rol']) ? $emp['rol'] : null;
                                $esAdminFila = strtolower($rolNombre) === 'administrador';
                                ?>

                                <tr class="<?= !$emp['activo'] ? 'table-danger' : '' ?>">

                                    <td><?= htmlspecialchars($emp['nombre']) ?></td>

                                    <td>
                                        <?php if ($rolNombre): ?>
                                            <span class="badge <?= $esAdminFila ? 'badge-danger' : 'badge-info' ?>">
                                                <?= htmlspecialchars($rolNombre) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary">Sin rol</span>
                                        <?php endif; ?>
                                    </td>

                                    <td class="text-center">
                                        <div class="d-flex align-items-center justify-content-center flex-wrap">

                                            <!-- SWITCH -->
                                            <div class="custom-control custom-switch mr-2">
                                                <input type="checkbox"
                                                    class="custom-control-input toggle-activo"
                                                    id="emp<?= $emp['Id'] ?>"
                                                    data-id="<?= $emp['Id'] ?>"
                                                    data-admin="<?= $esAdminFila ? 1 : 0 ?>"
                                                    <?= $emp['activo'] ? 'checked' : '' ?>>
                                                <label class="custom-control-label" for="emp<?= $emp['Id'] ?>"></label>
                                            </div>

                                            <!-- BADGE -->
                                            <span class="badge estado-badge <?= $emp['activo'] ? 'badge-success' : 'badge-danger' ?> mr-2">
                                                <?= $emp['activo'] ? 'Activo' : 'Inactivo' ?>
                                            </span>

                                            <!-- BOTONES -->
                                            <button class="btn btn-success btn-sm editar-rol-empleado rounded-circle"
                                                data-id="<?= $emp['Id'] ?>"
                                                data-rol="<?= $emp['rol_id'] ?>">
                                                <i class="fa fa-user-tag"></i>
                                            </button>

                                            <button class="btn btn-warning btn-sm cambiar-pass mr-1 rounded-circle"
                                                data-id="<?= $emp['Id'] ?>"
                                                <?= $disabled ?>>
                                                <i class="fa fa-key"></i>
                                            </button>

                                            <button class="btn btn-danger btn-sm eliminar-empleado rounded-circle"
                                                data-id="<?= $emp['Id'] ?>"
                                                <?= $disabled ?>>
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

        <!-- ================= ROLES ================= -->
        <div class="col-md-6">
            <div class="card card-outline card-info">
                <div class="card-header">
                    <h3 class="card-title">Roles</h3>
                </div>

                <div class="card-body table-responsive">
                    <table class="table table-hover tabla-roles">
                        <thead class="thead-dark">
                            <tr>
                                <th>Rol</th>
                                <th>Accesos</th>
                                <th width="120">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($roles_con_accesos as $rol): ?>
                                <tr>
                                    <td>
                                        <span class="badge badge-success">
                                            <?= htmlspecialchars($rol['rol_nombre']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?= $rol['accesos']
                                            ? htmlspecialchars($rol['accesos'])
                                            : '<span class="text-muted">Sin accesos</span>' ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group">
                                            <button class="btn btn-success btn-sm editar-rol rounded-circle"
                                                data-id="<?= $rol['id'] ?>">
                                                <i class="fa fa-user-tag"></i>
                                            </button>
                                            <button class="btn btn-danger btn-sm eliminar-rol rounded-circle"
                                                data-id="<?= $rol['id'] ?>">
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
<!-- ================= MODAL ROL ================= -->
<div class="modal fade" id="modalRolEmpleado">
    <div class="modal-dialog">
        <div class="modal-content">

            <div class="modal-header">
                <h5>Cambiar rol de empleado</h5>
            </div>

            <div class="modal-body">
                <input type="hidden" id="emp_id">

                <select id="nuevo_rol" class="form-control">
                    <option value="">-- Seleccionar --</option>
                    <?php foreach ($roles_con_accesos as $r): ?>
                        <option value="<?= $r['id'] ?>">
                            <?= htmlspecialchars($r['rol_nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="modal-footer">
                <button class="btn btn-success" id="guardarRolEmpleado">Guardar</button>
            </div>

        </div>
    </div>
</div>
<!-- ================= MODAL PASSWORD ================= -->
<div class="modal fade" id="modalPass" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">Cambiar contraseña</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>

            <div class="modal-body">

                <input type="hidden" id="pass_emp_id">

                <div class="form-group">
                    <label>Nueva contraseña</label>
                    <input type="password" id="nueva_pass" class="form-control">
                </div>

                <div class="form-group">
                    <label>Confirmar contraseña</label>
                    <input type="password" id="confirmar_pass" class="form-control">
                </div>

            </div>

            <div class="modal-footer">
                <button class="btn btn-success" id="guardarPass">
                    <i class="fa fa-save"></i> Guardar
                </button>
                <button class="btn btn-secondary" data-dismiss="modal">
                    Cancelar
                </button>
            </div>

        </div>
    </div>
</div>
<div class="modal fade" id="modalRol">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">

            <div class="modal-header">
                <h5>Editar rol y accesos</h5>
            </div>

            <div class="modal-body">

                <input type="hidden" id="rol_id">

                <input type="text" id="rol_nombre" class="form-control mb-3">

                <div class="row">
                    <?php foreach ($accesos as $acc): ?>
                        <div class="col-md-6">
                            <input type="checkbox"
                                class="acceso-check"
                                value="<?= $acc['Id'] ?>"
                                id="acc<?= $acc['Id'] ?>">
                            <?= $acc['nombre'] ?>
                        </div>
                    <?php endforeach; ?>
                </div>

            </div>

            <div class="modal-footer">
                <button class="btn btn-success" id="guardarRolSistema">Guardar</button>
            </div>

        </div>
    </div>
</div>
<script>
    $(function() {

        let USER_ID = <?= (int)$user_id ?>;
        let ES_ADMIN = <?= $es_admin ? 'true' : 'false' ?>;

        let tablaEmpleados = $('.tabla-empleados').DataTable({
            pageLength: 25,
            responsive: true,
            autoWidth: false,
            language: {
                url: "//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json"
            }
        });

        let tablaRoles = $('.tabla-roles').DataTable({
            pageLength: 25,
            responsive: true,
            autoWidth: false,
            language: {
                url: "//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json"
            }
        });

        // ================= TOGGLE =================
        $(document).on('change', '.toggle-activo', function() {

            let checkbox = $(this);
            let id = checkbox.data('id');
            let esAdminFila = checkbox.data('admin') == 1;
            let activo = checkbox.is(':checked') ? 1 : 0;
            let fila = checkbox.closest('tr');

            if (id == USER_ID) {
                Swal.fire('Error', 'No podés desactivarte a vos mismo', 'error');
                checkbox.prop('checked', true);
                return;
            }

            if (esAdminFila && !ES_ADMIN) {
                Swal.fire('Error', 'No podés modificar un administrador', 'error');
                checkbox.prop('checked', !activo);
                return;
            }

            Swal.fire({
                title: '¿Confirmar cambio?',
                text: activo ? "Activar empleado" : "Desactivar empleado",
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sí',
                cancelButtonText: 'Cancelar'
            }).then((result) => {

                if (!result.isConfirmed) {
                    checkbox.prop('checked', !activo);
                    return;
                }

                $.post('ajax/empleado_edit_toggle.php', {
                    id,
                    activo
                }, function(res) {

                    if (res.ok) {

                        let badge = fila.find('.estado-badge');

                        // ✅ actualizar badge
                        badge.toggleClass('badge-success', activo)
                            .toggleClass('badge-danger', !activo)
                            .text(activo ? 'Activo' : 'Inactivo');

                        // ✅ actualizar color fila
                        fila.toggleClass('table-danger', !activo);

                        // ✅ actualizar DataTable
                        tablaEmpleados.row(fila).invalidate().draw(false);

                        Swal.fire('OK', res.mensaje, 'success');

                    } else {
                        Swal.fire('Error', res.error, 'error');
                        checkbox.prop('checked', !activo);
                    }

                }, 'json');

            });

        });

        // ================= EMPLEADO =================
        $('.editar-rol-empleado').click(function() {

            let id = $(this).data('id');
            let rol = $(this).data('rol');

            $('#emp_id').val(id);
            $('#nuevo_rol').val(rol);

            $('#modalRolEmpleado').modal('show');
        });

        $('#guardarRolEmpleado').click(function() {

            let id = $('#emp_id').val();
            let rol = $('#nuevo_rol').val();

            $.post('ajax/empleado_rol.php', {
                id,
                rol
            }, function(res) {

                if (res.ok) {
                    location.reload();
                } else {
                    Swal.fire('Error', res.error, 'error');
                }

            }, 'json');
        });


        // ================= ROL =================
        $('.editar-rol').click(function() {

            let id = $(this).data('id');

            console.log("ROL ID:", id);

            $('#rol_id').val(id);
            $('.acceso-check').prop('checked', false);

            $.post('ajax/rol_get.php', {
                id: id
            }, function(res) {

                console.log(res);

                if (res.ok) {

                    $('#rol_nombre').val(res.rol.nombre);

                    res.accesos.forEach(acc => {
                        $('#acc' + acc).prop('checked', true);
                    });

                    $('#modalRol').modal('show');

                } else {
                    Swal.fire('Error', res.error, 'error');
                }

            }, 'json');

        });


        $('#guardarRolSistema').click(function() {

            let id = $('#rol_id').val();
            let nombre = $('#rol_nombre').val();

            let accesos = [];

            $('.acceso-check:checked').each(function() {
                accesos.push($(this).val());
            });

            $.post('ajax/rol_save.php', {
                id,
                nombre,
                accesos
            }, function(res) {

                if (res.ok) {
                    location.reload();
                } else {
                    Swal.fire('Error', res.error, 'error');
                }

            }, 'json');

        });
        // ================= ELIMINAR EMPLEADO =================
        $('.eliminar-empleado').click(function() {

            let id = $(this).data('id');

            Swal.fire({
                title: '¿Eliminar empleado?',
                text: 'Esta acción no se puede deshacer',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((r) => {

                if (!r.isConfirmed) return;

                $.post('ajax/empleado_delete.php', {
                    id
                }, function(res) {

                    if (res.ok) {
                        Swal.fire('Eliminado', res.mensaje, 'success')
                            .then(() => location.reload());
                    } else {
                        Swal.fire('Error', res.error, 'error');
                    }

                }, 'json');

            });

        });
        // ================= ELIMINAR ROL =================
        $('.eliminar-rol').click(function() {

            let id = $(this).data('id');

            Swal.fire({
                title: '¿Eliminar rol?',
                text: 'Esto eliminará sus accesos',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((r) => {

                if (!r.isConfirmed) return;

                $.post('ajax/rol_delete.php', {
                    id
                }, function(res) {

                    if (res.ok) {
                        Swal.fire('Eliminado', res.mensaje, 'success')
                            .then(() => location.reload());
                    } else {
                        Swal.fire('Error', res.error, 'error');
                    }

                }, 'json');

            });

        });
        // ================= ABRIR MODAL PASSWORD =================
        $('.cambiar-pass').click(function() {

            if (!ES_ADMIN) {
                Swal.fire('Error', 'No tenés permisos', 'error');
                return;
            }

            let id = $(this).data('id');

            $('#pass_emp_id').val(id);
            $('#nueva_pass').val('');
            $('#confirmar_pass').val('');

            $('#modalPass').modal('show');
        });


        // ================= GUARDAR PASSWORD =================
        $('#guardarPass').click(function() {

            let id = $('#pass_emp_id').val();
            let pass = $('#nueva_pass').val();
            let confirm = $('#confirmar_pass').val();

            if (pass.length < 4) {
                Swal.fire('Error', 'La contraseña debe tener al menos 4 caracteres', 'error');
                return;
            }

            if (pass !== confirm) {
                Swal.fire('Error', 'Las contraseñas no coinciden', 'error');
                return;
            }

            Swal.fire({
                title: '¿Cambiar contraseña?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí'
            }).then((r) => {

                if (!r.isConfirmed) return;

                $.post('ajax/empleado_pass.php', {
                    id,
                    pass
                }, function(res) {

                    if (res.ok) {
                        $('#modalPass').modal('hide');
                        Swal.fire('OK', res.mensaje, 'success');
                    } else {
                        Swal.fire('Error', res.error, 'error');
                    }

                }, 'json');

            });

        });

    });
</script>