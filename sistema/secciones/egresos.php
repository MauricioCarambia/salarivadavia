<?php
require_once __DIR__ . '/../inc/db.php';


/* =========================
   PROFESIONALES
========================= */
$profesionales = $pdo->query("
    SELECT id, nombre, apellido 
    FROM profesionales 
    ORDER BY apellido ASC
")->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   EGRESOS HISTORIAL
========================= */
$egresos = $pdo->query("
    SELECT e.*, p.nombre, p.apellido
    FROM egresos e
    LEFT JOIN profesionales p ON p.id = e.profesional_id
    ORDER BY e.id DESC
    LIMIT 100
")->fetchAll(PDO::FETCH_ASSOC);
?>


<!-- =======================
         NUEVO EGRESO
    ======================= -->
<div class="card card-danger card-outline mb-3">
    <div class="card-body">
        <h5>💸 Nuevo Egreso</h5>

        <form id="formEgreso">

            <div class="row">

                <!-- Tipo -->
                <div class="col-md-3">
                    <label>Tipo</label>
                    <select id="tipo" class="form-control" required>
                        <option value="caja">Caja</option>
                        <option value="fondo">Fondo</option>
                    </select>
                </div>

                <!-- Concepto -->
                <div class="col-md-4">
                    <label>Concepto</label>
                    <input type="text" id="concepto" class="form-control" required>
                </div>

                <!-- Monto -->
                <div class="col-md-3">
                    <label>Monto</label>
                    <input type="number" step="0.01" id="monto" class="form-control" required>
                </div>

                <!-- Profesional -->
                <div class="col-md-4 mt-2" id="boxProfesional" style="display:none;">
                    <label>Profesional</label>
                    <select id="profesional" class="form-control">
                        <option value="">Seleccionar</option>
                        <?php foreach ($profesionales as $p): ?>
                            <option value="<?= $p['id'] ?>">
                                <?= $p['apellido'] . ' ' . $p['nombre'] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-12 mt-3">
                    <button class="btn btn-danger">
                        Registrar Egreso
                    </button>
                </div>

            </div>

        </form>
    </div>
</div>

<!-- =======================
         HISTORIAL
    ======================= -->
<div class="card card-info card-outline">
    <div class="card-body">
        <h5>📊 Historial de Egresos</h5>

        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Tipo</th>
                    <th>Concepto</th>
                    <th>Profesional</th>
                    <th>Monto</th>
                </tr>
            </thead>

            <tbody id="tablaEgresos">
                <?php foreach ($egresos as $e): ?>
                    <tr>
                        <td><?= $e['fecha'] ?></td>
                        <td>
                            <span class="badge badge-<?= $e['tipo'] == 'caja' ? 'warning' : 'info' ?>">
                                <?= strtoupper($e['tipo']) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($e['concepto']) ?></td>
                        <td>
                            <?= $e['apellido'] ? $e['apellido'] . ' ' . $e['nombre'] : '-' ?>
                        </td>
                        <td>$<?= number_format($e['monto'], 2, ',', '.') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>

        </table>
    </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    function formatearNumero(valor) {
        return Number(valor || 0).toLocaleString('es-AR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    /* =========================
       MOSTRAR PROFESIONAL SOLO FONDO
    ========================= */
    $("#tipo").change(function() {
        if ($(this).val() === "fondo") {
            $("#boxProfesional").show();
        } else {
            $("#boxProfesional").hide();
            $("#profesional").val("");
        }
    });

    /* =========================
       GUARDAR EGRESO
    ========================= */
 $("#formEgreso").submit(function(e) {
    e.preventDefault();

    Swal.fire({
        title: "¿Confirmar egreso?",
       text: `Concepto: ${$("#concepto").val()} - Monto: $${$("#monto").val()}`,
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#d33",
        cancelButtonColor: "#6c757d",
        confirmButtonText: "Sí, registrar",
        cancelButtonText: "Cancelar"
    }).then((result) => {

        if (result.isConfirmed) {

            $.ajax({
                url: "ajax/guardar_egreso.php",
                type: "POST",
                contentType: "application/json",
                data: JSON.stringify({
                    tipo: $("#tipo").val(),
                    concepto: $("#concepto").val(),
                    monto: $("#monto").val(),
                    profesional_id: $("#profesional").val()
                }),
                success: function(res) {
                    if (res.success) {
                        Swal.fire("OK", "Egreso registrado", "success")
                            .then(() => location.reload());
                    } else {
                        Swal.fire("Error", res.message, "error");
                    }
                }
            });

        }

    });
});
</script>