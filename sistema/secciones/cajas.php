<?php

require_once __DIR__ . '/../inc/db.php';
/* =================================
    👤 USUARIO Y ROL
================================= */
$usuarioId = $_SESSION['user_id'] ?? null;

$stmt = $pdo->prepare("
    SELECT r.nombre as rol
    FROM empleados e
    INNER JOIN roles r ON r.id = e.rol_id
    WHERE e.id = ?
");
$stmt->execute([$usuarioId]);
$rol = $stmt->fetchColumn();
$esAdmin = ($rol === 'Administrador');

if (!$usuarioId) {
    header("Location: login.php");
    exit;
}
/* =================================
    📅 FILTROS
================================= */
$hoy = date('Y-m-d');

$desde = $_GET['desde'] ?? $hoy;
$hasta = $_GET['hasta'] ?? $hoy;
$caja_id = $_GET['caja_id'] ?? null;
$turno = $_GET['turno'] ?? null;
$usuarioFiltro = $_GET['usuario_id'] ?? null;

/* =================================
    🧠 QUERY DINÁMICA
================================= */
$where = [];
$params = [];

if ($desde) {
    $where[] = "DATE(cs.fecha_apertura) >= ?";
    $params[] = $desde;
}

if ($hasta) {
    $where[] = "DATE(cs.fecha_apertura) <= ?";
    $params[] = $hasta;
}

if ($caja_id) {
    $where[] = "cs.caja_id = ?";
    $params[] = $caja_id;
}

if ($turno) {
    $where[] = "cs.turno = ?";
    $params[] = $turno;
}

if ($usuarioFiltro) {
    $where[] = "cs.usuario_id = ?";
    $params[] = $usuarioFiltro;
}

/* 🔥 ARMAR WHERE */
$whereSQL = "";
if (!empty($where)) {
    $whereSQL = "WHERE " . implode(" AND ", $where);
}

/* =================================
    📊 CONSULTA FINAL
================================= */
$sql = "
SELECT 
    cs.id,
    cs.caja_id,
    cs.turno,
    cs.estado,
    cs.fecha_apertura,
    cs.fecha_cierre,
    cs.monto_inicial,
cs.usuario_id,
    c.nombre AS caja_nombre,
    u.nombre AS nombre_usuario,

    ac.total_sistema,
    ac.total_caja,
    ac.total_fondo,
    ac.total_real,
    ac.diferencia

FROM caja_sesion cs
JOIN cajas c ON c.id = cs.caja_id
LEFT JOIN empleados u ON u.id = cs.usuario_id
LEFT JOIN arqueos_caja ac ON ac.caja_sesion_id = cs.id

$whereSQL

ORDER BY cs.fecha_cierre DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$historialSesiones = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (!function_exists('obtenerCajaAbierta')) {
    function obtenerCajaAbierta($pdo, $usuarioId)
    {
        $stmt = $pdo->prepare("
        SELECT cs.*, c.id AS caja_id, c.nombre
        FROM caja_sesion cs
        INNER JOIN cajas c ON c.id = cs.caja_id
        WHERE cs.estado = 'abierta'
          AND cs.usuario_id = ?
        LIMIT 1
    ");
        $stmt->execute([$usuarioId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

$cajas = $pdo->query("SELECT * FROM cajas ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
$cajaAbierta = obtenerCajaAbierta($pdo, $usuarioId);
/* =================================
    🔓 CAJAS ABIERTAS (SIN FILTRO FECHA)
================================= */
$stmt = $pdo->query("
    SELECT *
    FROM caja_sesion
    WHERE estado = 'abierta'
");

$cajasAbiertas = [];

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $cajasAbiertas[$row['caja_id']] = $row;
}
function obtenerCajaSesionActiva($pdo, $usuarioId)
{
    $stmt = $pdo->prepare("
        SELECT id, caja_id 
        FROM caja_sesion 
        WHERE usuario_id = ? AND estado = 'abierta'
        LIMIT 1
    ");
    $stmt->execute([$usuarioId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}


?>
<div class="card card-outline card-info p-3 mb-3">
    <form method="get" class="row">
        <input type="hidden" name="seccion" value="cajas">
        <?php if ($esAdmin): ?>
            <div class="col-md-2"><label>Desde</label><input type="date" name="desde" value="<?= $desde ?>" class="form-control"></div>
            <div class="col-md-2"><label>Hasta</label><input type="date" name="hasta" value="<?= $hasta ?>" class="form-control"></div>
        <?php endif; ?>
        <div class="col-md-2">
            <label>Caja</label>
            <select name="caja_id" class="form-control">
                <option value="">Todas</option>
                <?php foreach ($pdo->query("SELECT id, nombre FROM cajas")->fetchAll() as $cj): ?>
                    <option value="<?= $cj['id'] ?>" <?= $caja_id == $cj['id'] ? 'selected' : '' ?>><?= $cj['nombre'] ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label>Turno</label>
            <select name="turno" class="form-control">
                <option value="">Todos</option>
                <option value="mañana" <?= $turno == 'mañana' ? 'selected' : '' ?>>Mañana</option>
                <option value="tarde" <?= $turno == 'tarde' ? 'selected' : '' ?>>Tarde</option>
            </select>
            </select>
        </div>
        <div class="col-md-2">
            <label>Empleado</label>
            <select name="usuario_id" class="form-control">
                <option value="">Todos</option>
                <?php foreach ($pdo->query("SELECT id, nombre FROM empleados ORDER BY nombre")->fetchAll() as $emp): ?>
                    <option value="<?= $emp['id'] ?>" <?= $usuarioFiltro == $emp['id'] ? 'selected' : '' ?>><?= htmlspecialchars($emp['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-1 d-flex align-items-end">
            <button class="btn btn-primary w-100">Filtrar</button>
        </div>
        <div class="col-md-1 d-flex align-items-end">
            <a href="?seccion=<?= $_GET['seccion'] ?? '' ?>" class="btn btn-secondary w-100">
                Limpiar
            </a>
        </div>
    </form>
</div>
<!-- Tabla principal -->
<div class="row mb-3">
    <div class="col-12">
        <div class="card card-info card-outline">
            <div class="container-fluid mt-3">
                <h3 class="mb-3">Gestión de Cajas <button class="btn btn-primary btn-sm" id="btnNuevaCaja">
                        <i class="fas fa-plus"></i> Nueva Caja
                    </button></h3>
                <div class="table-responsive mb-4">

                    <table class="table table-striped table-hover">
                        <thead class="thead-dark">
                            <tr class="text-center">
                                <th>Nombre</th>
                                <th>Estado</th>
                                <th>Turno</th>
                                <th>Fecha Apertura</th>
                                <th>Fecha Cierre</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>

                            <?php foreach ($cajas as $c):

                                $sesionActiva = $cajasAbiertas[$c['id']] ?? null;

                            ?>

                                <tr>

                                    <td class="text-center">
                                        <?= htmlspecialchars($c['nombre']) ?>
                                    </td>

                                    <td class="text-center">

                                        <?= $sesionActiva
                                            ? '<span class="badge badge-success">Abierta</span>'
                                            : '<span class="badge badge-secondary">Cerrada</span>' ?>

                                    </td>

                                    <td class="text-center">
                                        <?= $sesionActiva['turno'] ?? '-' ?>
                                    </td>

                                    <td class="text-center">
                                        <?= $sesionActiva['fecha_apertura'] ?? '-' ?>
                                    </td>

                                    <td class="text-center">
                                        <?= $sesionActiva['fecha_cierre'] ?? '-' ?>
                                    </td>

                                    <td class="text-center">

                                        <div class="btn-group">

                                            <?php
                                            $puedeCerrar = $sesionActiva
                                                && (
                                                    $sesionActiva['usuario_id'] == $usuarioId
                                                    || $esAdmin
                                                );
                                            ?>

                                            <?php if (!$sesionActiva): ?>

                                                <button class="btn btn-info btn-sm abrir-caja"
                                                    data-id="<?= $c['id'] ?>"
                                                    data-nombre="<?= htmlspecialchars($c['nombre']) ?>">

                                                    Abrir

                                                </button>

                                            <?php else: ?>

                                                <?php if ($puedeCerrar): ?>

                                                    <button class="btn btn-warning btn-sm cerrar-caja"
                                                        data-sesion-id="<?= $sesionActiva['id'] ?>">

                                                        Cerrar

                                                    </button>

                                                <?php else: ?>

                                                    <span class="badge badge-secondary">
                                                        Solo el usuario que abrió o un administrador puede cerrar
                                                    </span>

                                                <?php endif; ?>

                                            <?php endif; ?>

                                            <button class="btn btn-success btn-sm editar-caja rounded-circle"
                                                data-id="<?= $c['id'] ?>"
                                                data-nombre="<?= htmlspecialchars($c['nombre']) ?>">

                                                <i class="fas fa-pencil-alt"></i>

                                            </button>

                                            <button class="btn btn-danger btn-sm eliminar-caja rounded-circle"
                                                data-id="<?= $c['id'] ?>">

                                                <i class="fas fa-trash"></i>

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
<!-- Tabla historial -->
<div class="row mb-3">
    <div class="col-12">
        <div class="card card-info card-outline">
            <div class="container-fluid mt-3">
                <h3 class="mb-3">Historial de Cajas</h3>
                <div class="table-responsive">
                    <table class="table  table-striped table-hover datatable">
                        <thead class="thead-dark">
                            <tr>
                                <th>Caja</th>
                                <th>Usuario</th>
                                <th>Turno</th>
                                <th>Fecha Apertura</th>
                                <th>Fecha Cierre</th>
                                <th>Monto Inicial</th>
                                <th>Total Sistema</th>
                                <th>Total en caja</th>
                                <th>Diferencia</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($historialSesiones as $h): ?>
                                <tr>
                                    <td><?= htmlspecialchars($h['caja_nombre']) ?></td>
                                    <td><?= htmlspecialchars($h['nombre_usuario'] ?? '-') ?></td>
                                    <td><?= $h['turno'] ?></td>
                                    <td><?= $h['fecha_apertura'] ?></td>
                                    <td><?= $h['fecha_cierre'] ?? '-' ?></td>

                                    <td><?= $h['total_sistema'] !== null ? number_format($h['monto_inicial'], 2) : '-' ?>
                                    <td><?= $h['total_sistema'] !== null ? number_format($h['total_sistema'], 2) : '-' ?>
                                    </td>
                                    <td><?= $h['total_real'] !== null ? number_format($h['total_real'], 2) : '-' ?></td>
                                    <td>
                                        <?php
                                        if ($h['diferencia'] !== null) {

                                            $dif = floatval($h['diferencia']);

                                            if ($dif < 0) {
                                                echo '<span class="badge badge-danger">-$' . number_format(abs($dif), 2) . '</span>';
                                            } elseif ($dif > 0) {
                                                echo '<span class="badge badge-success">$' . number_format($dif, 2) . '</span>';
                                            } else {
                                                echo '<span class="badge badge-secondary">$0.00</span>';
                                            }
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </td>

                                    <td>
                                        <?= $h['estado'] === 'abierta'
                                            ? '<span class="badge badge-success">Abierta</span>'
                                            : '<span class="badge badge-secondary">Cerrada</span>' ?>
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
<div class="modal fade" id="cajaModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form id="formCaja">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="tituloCaja">Nueva Caja</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>

                <div class="modal-body">
                    <input type="hidden" id="cajaId">

                    <div class="form-group">
                        <label>Nombre de la Caja</label>
                        <input type="text" class="form-control" id="nombreCaja" required>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Guardar</button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                </div>
            </div>
        </form>
    </div>
</div>
<!-- Modal Abrir Caja -->
<div class="modal fade" id="abrirCajaModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <form id="formAbrirCaja">
            <div class="modal-content border-success">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fas fa-plus-circle"></i> Abrir Caja</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="abrirCajaId">
                    <input type="hidden" id="abrirCajaNombre">
                    <div class="form-group">
                        <label>Monto Inicial</label>
                        <div class="input-group">
                            <div class="input-group-prepend"><span class="input-group-text">$</span></div>
                            <input type="number" class="form-control" id="montoInicial" min="0" step="0.01"
                                value="0.00" placeholder="Ingrese monto inicial">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Turno</label>
                        <select class="form-control" id="turnoCaja" required>
                            <option value="" disabled selected>Selecciona turno...</option>
                            <option value="mañana">Mañana</option>
                            <option value="tarde">Tarde</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> Abrir</button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><i class="fas fa-times"></i>
                        Cancelar</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal Cerrar Caja -->
<div class="modal fade" id="cerrarCajaModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <form id="formCerrarCaja">
            <div class="modal-content border-danger">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-times-circle"></i> Cerrar Caja</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Monto Real</label>
                        <div class="input-group">
                            <div class="input-group-prepend"><span class="input-group-text">$</span></div>
                            <input type="number" class="form-control" id="montoReal" step="0.01"
                                placeholder="Ingrese monto en caja" required>
                        </div>
                    </div>
                    <div id="diferencia" class="font-weight-bold mt-2"></div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-danger"><i class="fas fa-check"></i> Cerrar</button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><i class="fas fa-times"></i>
                        Cancelar</button>
                </div>
            </div>
        </form>
    </div>
</div>
<script>
    $(document).ready(function() {

        let cerrarCajaId = null;
        let totalSistemaGlobal = 0;
        let montoInicialGlobal = 0;
        let cajaEsperadaGlobal = 0;
        let totalFondoGlobal = 0;
        let ingEfGlobal = 0;
        let egrEfGlobal = 0;
        let egrProfGlobal = 0;

        // ==========================
        // CERRAR CAJA (CLICK)
        // ==========================
        $(".cerrar-caja").click(function() {

            cerrarCajaId = $(this).data("sesion-id");

            $("#montoReal").val('');
            $("#diferencia").html("Cargando...");

            $.ajax({
                url: "ajax/obtener_total_sistema.php",
                type: "POST",
                contentType: "application/json",
                data: JSON.stringify({
                    caja_id: cerrarCajaId
                }),
                success: function(data) {

                    if (data.success) {

                        totalSistemaGlobal = parseFloat(data.total_sistema);
                        montoInicialGlobal = parseFloat(data.monto_inicial);
                        cajaEsperadaGlobal = parseFloat(data.caja_esperada);
                        totalFondoGlobal = parseFloat(data.total_fondo ?? 0);

                        ingEfGlobal = parseFloat(data.ingresos_efectivo ?? 0);
                        egrEfGlobal = parseFloat(data.egresos_efectivo ?? 0);
                        egrProfGlobal = parseFloat(data.egresos_profesionales_efectivo ?? 0);

                        renderDetalle(null);
                    } else {
                        $("#diferencia").html("Error al obtener datos");
                    }
                }
            });

            $("#cerrarCajaModal").modal("show");
        });

        // ==========================
        // RENDER DETALLE
        // ==========================
        function renderDetalle(montoReal) {

            let diff = 0;
            let color = "secondary";

            if (montoReal !== null && !isNaN(montoReal)) {
                diff = montoReal - (cajaEsperadaGlobal + totalFondoGlobal);

                if (diff > 0) color = "success";
                if (diff < 0) color = "danger";
            }

            $("#diferencia").html(`
        <div class="mb-2">
            <strong>Caja</strong><br>
            Inicial: <strong>$${montoInicialGlobal.toFixed(2)}</strong><br>
           
            Caja: 
            <strong class="text-primary">
                $${cajaEsperadaGlobal.toFixed(2)}
            </strong>
        </div>

        <div class="mb-2">
            Fondo: 
            <strong class="text-info">
                $${totalFondoGlobal.toFixed(2)}
            </strong>
        </div>

        <div class="mb-2">
            Total:
            <strong class="text-success">
                $${(cajaEsperadaGlobal + totalFondoGlobal).toFixed(2)}
            </strong><br>

         
        </div>

        ${
            montoReal !== null
            ? `<div>
                    <strong>Diferencia:</strong> 
                    <span class="badge badge-${color}">
                        $${diff.toFixed(2)}
                    </span>
               </div>`
            : ""
        }
    `);
        }

        // ==========================
        // INPUT MONTO REAL
        // ==========================
        $("#montoReal").on("input", function() {
            const montoReal = parseFloat($(this).val());
            renderDetalle(montoReal);
        });

        // ==========================
        // ABRIR CAJA
        // ==========================
        $(".abrir-caja").click(function() {
            $("#abrirCajaId").val($(this).data("id"));
            $("#abrirCajaNombre").val($(this).data("nombre"));
            $("#montoInicial").val('');
            $("#turnoCaja").val("");
            $("#abrirCajaModal").modal("show");
        });

        // ==========================
        // SUBMIT ABRIR
        // ==========================
        $("#formAbrirCaja").submit(function(e) {
            e.preventDefault();

            let montoInicial = parseFloat($("#montoInicial").val());
            if (isNaN(montoInicial)) montoInicial = 0;

            $.ajax({
                url: "ajax/abrir_caja.php",
                type: "POST",
                contentType: "application/json",
                data: JSON.stringify({
                    caja_id: parseInt($("#abrirCajaId").val()),
                    monto_inicial: montoInicial,
                    turno: $("#turnoCaja").val()
                }),
                success: function(data) {
                    if (data.success) {
                        $("#abrirCajaModal").modal("hide");
                        Swal.fire("Éxito", "Caja abierta correctamente", "success")
                            .then(() => location.reload());
                    } else {
                        Swal.fire("Error", data.message, "error");
                    }
                }
            });
        });
        // ==========================
        // 🆕 NUEVA CAJA
        // ==========================
        $("#btnNuevaCaja").click(function() {

            $("#tituloCaja").text("Nueva Caja");

            $("#cajaId").val('');
            $("#nombreCaja").val('');

            $("#cajaModal").modal("show");
        });
        // ==========================
        // 💾 GUARDAR CAJA
        // ==========================
        $("#formCaja").submit(function(e) {
            e.preventDefault();

            const id = $("#cajaId").val();
            const nombre = $("#nombreCaja").val();

            if (!nombre) {
                return Swal.fire("Error", "Ingrese un nombre", "error");
            }

            $.ajax({
                url: "ajax/guardar_caja.php",
                type: "POST",
                contentType: "application/json",
                data: JSON.stringify({
                    id: id || null,
                    nombre: nombre
                }),
                success: function(res) {
                    if (res.success) {
                        $("#cajaModal").modal("hide");

                        Swal.fire("OK", "Caja guardada", "success")
                            .then(() => location.reload());
                    } else {
                        Swal.fire("Error", res.message, "error");
                    }
                }
            });
        });
        // ==========================
        // SUBMIT CERRAR
        // ==========================
        $("#formCerrarCaja").submit(function(e) {
            e.preventDefault();

            const montoReal = parseFloat($("#montoReal").val());

            if (isNaN(montoReal)) {
                return Swal.fire("Error", "Ingrese el monto real", "error");
            }

            if (montoReal < 0) {
                return Swal.fire("Error", "El monto no puede ser negativo", "error");
            }

            $.ajax({
                url: "ajax/cerrar_caja.php",
                type: "POST",
                dataType: "json",
                xhrFields: {
                    withCredentials: true
                },
                data: {
                    caja_id: cerrarCajaId,
                    monto_real: montoReal
                },
                success: function(data) {

                    console.log(data);

                    if (data.success) {

                        $("#cerrarCajaModal").modal("hide");

                        $(".modal-backdrop").remove();
                        $("body").removeClass("modal-open");

                        Swal.fire(
                            "Éxito",
                            `Caja cerrada. Diferencia: ${parseFloat(data.diferencia).toFixed(2)}`,
                            "success"
                        ).then(() => location.reload());

                    } else {
                        Swal.fire("Error", data.message, "error");
                    }
                },
                error: function(xhr) {
                    console.log(xhr.responseText);
                    Swal.fire("Error", "Error servidor", "error");
                }
            });
        });
        $(document).on("click", ".editar-caja", function() {

            $("#tituloCaja").text("Editar Caja");

            const id = $(this).data("id");
            const nombre = $(this).data("nombre");

            $("#cajaId").val(id);
            $("#nombreCaja").val(nombre);

            $("#cajaModal").modal("show");
        });
        $(document).on("click", ".eliminar-caja", function() {

            const id = $(this).data("id");

            Swal.fire({
                title: "¿Eliminar caja?",
                text: "Esta acción no se puede deshacer",
                icon: "warning",
                showCancelButton: true,
                confirmButtonText: "Sí, eliminar",
                cancelButtonText: "Cancelar"
            }).then((result) => {

                if (result.isConfirmed) {

                    $.ajax({
                        url: "ajax/eliminar_caja.php",
                        type: "POST",
                        data: {
                            id: id
                        },
                        success: function(res) {

                            if (res.success) {
                                Swal.fire("Eliminado", "Caja eliminada correctamente", "success")
                                    .then(() => location.reload());
                            } else {
                                Swal.fire("Error", res.message, "error");
                            }
                        },
                        error: function() {
                            Swal.fire("Error", "Error del servidor", "error");
                        }
                    });

                }

            });
        });
    });
</script>