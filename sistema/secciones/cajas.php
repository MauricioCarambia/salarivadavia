<?php
require_once __DIR__ . '/../inc/db.php';

$usuarioId = $_SESSION['user_id'] ?? null;
if (!$usuarioId) {
    header("Location: login.php");
    exit;
}

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

$stmt = $pdo->query("
    SELECT 
        cs.id,
        cs.caja_id,
        cs.turno,
        cs.estado,
        cs.fecha_apertura,
        cs.fecha_cierre,
        cs.monto_inicial,

        c.nombre AS caja_nombre,
        u.nombre AS nombre_usuario,

        ac.total_sistema,
        ac.total_real,
        ac.diferencia

    FROM caja_sesion cs
    JOIN cajas c ON c.id = cs.caja_id
    LEFT JOIN empleados u ON u.id = cs.usuario_id

    LEFT JOIN arqueos_caja ac 
        ON ac.caja_sesion_id = cs.id

    ORDER BY cs.fecha_cierre DESC
");
$historialSesiones = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

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

                                $sesionActiva = null;

                                foreach ($historialSesiones as $h) {
                                    if ($h['caja_id'] == $c['id'] && $h['estado'] === 'abierta') {
                                        $sesionActiva = $h;
                                        break;
                                    }
                                }
                            ?>
                                <tr>
                                    <td class="text-center"><?= htmlspecialchars($c['nombre']) ?></td>

                                    <td class="text-center">
                                        <?= $sesionActiva
                                            ? '<span class="badge badge-success">Abierta</span>'
                                            : '<span class="badge badge-secondary">Cerrada</span>' ?>
                                    </td>

                                    <td class="text-center"><?= $sesionActiva['turno'] ?? '-' ?></td>
                                    <td class="text-center"><?= $sesionActiva['fecha_apertura'] ?? '-' ?></td>
                                    <td class="text-center"><?= $sesionActiva['fecha_cierre'] ?? '-' ?></td>

                                    <td class="text-center">
                                        <div class="btn-group">
                                            <?php if (!$sesionActiva): ?>
                                                <button class="btn btn-info btn-sm abrir-caja"
                                                    data-id="<?= $c['id'] ?>"
                                                    data-nombre="<?= htmlspecialchars($c['nombre']) ?>">
                                                    Abrir
                                                </button>
                                            <?php else: ?>
                                                <button class="btn btn-warning btn-sm cerrar-caja"
                                                    data-sesion-id="<?= $sesionActiva['id'] ?>">
                                                    Cerrar
                                                </button>
                                            <?php endif; ?>

                                            <button class="btn btn-success btn-sm editar-caja rounded-circle"
                                                data-id="<?= $c['id'] ?>"
                                                data-nombre="<?= htmlspecialchars($c['nombre']) ?>"><i
                                                    class="fas fa-pencil-alt"></i>
                                            </button>

                                            <button class="btn btn-danger btn-sm eliminar-caja rounded-circle"
                                                data-id="<?= $c['id'] ?>"><i
                                                    class="fas fa-trash"></i>
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
                        <select class="form-control" id="turnoCaja">
                            <option value="Mañana">Mañana</option>
                            <option value="Tarde">Tarde</option>
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

        $(".cerrar-caja").click(function() {

            cerrarCajaId = $(this).data("sesion-id");

            $("#montoReal").val('');
            $("#diferencia").html("");

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

                        const cajaEsperada = montoInicialGlobal + totalSistemaGlobal;

                        $("#diferencia").html(`
                        Monto inicial: <strong>${montoInicialGlobal.toFixed(2)}</strong><br>
                        Total sistema: <strong>${totalSistemaGlobal.toFixed(2)}</strong><br>
                        Caja esperada: <strong>${cajaEsperada.toFixed(2)}</strong>
                    `);
                    }
                }
            });

            $("#cerrarCajaModal").modal("show");
        });

        // 🔥 cálculo en vivo
        $("#montoReal").on("input", function() {

            const montoReal = parseFloat($(this).val());

            const cajaEsperada = montoInicialGlobal + totalSistemaGlobal;

            if (isNaN(montoReal)) {
                $("#diferencia").html(`
                Monto inicial: <strong>${montoInicialGlobal.toFixed(2)}</strong><br>
                Total sistema: <strong>${totalSistemaGlobal.toFixed(2)}</strong><br>
                Caja esperada: <strong>${cajaEsperada.toFixed(2)}</strong>
            `);
                return;
            }

            const diff = montoReal - cajaEsperada;

            // 🔥 color automático
            let color = "secondary";
            if (diff > 0) color = "success";
            if (diff < 0) color = "danger";

            $("#diferencia").html(`
            Monto inicial: <strong>${montoInicialGlobal.toFixed(2)}</strong><br>
            Total sistema: <strong>${totalSistemaGlobal.toFixed(2)}</strong><br>
            Caja esperada: <strong>${cajaEsperada.toFixed(2)}</strong><br>
            Diferencia: <span class="badge badge-${color}">
                ${diff.toFixed(2)}
            </span>
        `);
        });
        // Abrir Caja
        $(".abrir-caja").click(function() {
            $("#abrirCajaId").val($(this).data("id"));
            $("#abrirCajaNombre").val($(this).data("nombre"));
            $("#montoInicial").val('');
            $("#turnoCaja").val("Mañana");
            $("#abrirCajaModal").modal("show");
        });




        // Submit Abrir Caja
        $("#formAbrirCaja").submit(function(e) {
            e.preventDefault();
            let montoInicial = parseFloat($("#montoInicial").val());

            // 🔥 Si viene vacío → lo convertimos en 0
            if (isNaN(montoInicial)) {
                montoInicial = 0;
            }
            const payload = {
                caja_id: parseInt($("#abrirCajaId").val()),
                monto_inicial: montoInicial,
                turno: $("#turnoCaja").val()
            };
            $.ajax({
                url: "ajax/abrir_caja.php",
                type: "POST",
                contentType: "application/json",
                data: JSON.stringify(payload),
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

        // Submit Cerrar Caja
        $("#formCerrarCaja").submit(function(e) {
            e.preventDefault();
            const montoReal = parseFloat($("#montoReal").val());

            // ✅ VALIDACIONES
            if (isNaN(montoReal)) {
                return Swal.fire("Error", "Ingrese el monto real", "error");
            }

            if (montoReal < 0) {
                return Swal.fire("Error", "El monto no puede ser negativo", "error");
            }

            if (!cerrarCajaId) {
                return Swal.fire("Error", "Caja inválida", "error");
            }
            $.ajax({
                url: "ajax/cerrar_caja.php",
                type: "POST",
                contentType: "application/json",
                data: JSON.stringify({
                    caja_id: cerrarCajaId,
                    monto_real: montoReal
                }),
                success: function(data) {
                    if (data.success) {
                        $("#cerrarCajaModal").modal("hide");
                        Swal.fire("Éxito", `Caja cerrada. Diferencia: ${data.diferencia.toFixed(2)}`, "success")
                            .then(() => location.reload());
                    } else {
                        Swal.fire("Error", data.message, "error");
                    }
                }
            });
        });
        $('.datatable').each(function() {
            initDataTable($(this));
        });
        // ==========================
        // NUEVA CAJA
        // ==========================
        $("#btnNuevaCaja").click(function() {
            $("#cajaId").val('');
            $("#nombreCaja").val('');
            $("#tituloCaja").text("Nueva Caja");
            $("#cajaModal").modal("show");
        });

        // ==========================
        // EDITAR CAJA
        // ==========================
        $(".editar-caja").click(function() {
            $("#cajaId").val($(this).data("id"));
            $("#nombreCaja").val($(this).data("nombre"));
            $("#tituloCaja").text("Editar Caja");
            $("#cajaModal").modal("show");
        });

        // ==========================
        // GUARDAR (INSERT / UPDATE)
        // ==========================
        $("#formCaja").submit(function(e) {
            e.preventDefault();

            const payload = {
                id: $("#cajaId").val(),
                nombre: $("#nombreCaja").val()
            };

            $.ajax({
                url: "ajax/guardar_caja.php",
                type: "POST",
                contentType: "application/json",
                data: JSON.stringify(payload),
                success: function(data) {
                    if (data.success) {
                        $("#cajaModal").modal("hide");
                        Swal.fire("Éxito", "Caja guardada correctamente", "success")
                            .then(() => location.reload());
                    } else {
                        Swal.fire("Error", data.message, "error");
                    }
                }
            });
        });

        // ==========================
        // ELIMINAR
        // ==========================
        $(".eliminar-caja").click(function() {
            const id = $(this).data("id");

            Swal.fire({
                title: "¿Eliminar caja?",
                text: "Esta acción no se puede deshacer",
                icon: "warning",
                showCancelButton: true,
                confirmButtonText: "Sí, eliminar"
            }).then((result) => {
                if (result.isConfirmed) {

                    $.ajax({
                        url: "ajax/eliminar_caja.php",
                        type: "POST",
                        contentType: "application/json",
                        data: JSON.stringify({
                            id: id
                        }),
                        success: function(data) {
                            if (data.success) {
                                Swal.fire("Eliminado", "Caja eliminada", "success")
                                    .then(() => location.reload());
                            } else {
                                Swal.fire("Error", data.message, "error");
                            }
                        }
                    });

                }
            });
        });
    });
</script>