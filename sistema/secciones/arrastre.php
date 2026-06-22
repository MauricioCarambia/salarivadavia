<?php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/services/afiliados.php';
$stmt = $pdo->query("
    SELECT COUNT(*) total
    FROM pacientes
    WHERE tipo_socio = 'vitalicio'
");

$totalVitalicios = (int)$stmt->fetchColumn();
$stmt = $pdo->query("
    SELECT COUNT(*) total
    FROM pacientes p

    LEFT JOIN (
        SELECT
            paciente_id,
            MAX(fecha_correspondiente) ultimo_pago
        FROM pagos_afiliados
        GROUP BY paciente_id
    ) pa ON pa.paciente_id = p.Id

    WHERE
        p.tipo_socio <> 'vitalicio'

        AND pa.ultimo_pago IS NOT NULL

        AND PERIOD_DIFF(
            DATE_FORMAT(
                DATE_SUB(CURDATE(), INTERVAL 1 MONTH),
                '%Y%m'
            ),
            DATE_FORMAT(
                pa.ultimo_pago,
                '%Y%m'
            )
        ) <= 2
");

$totalActivos = (int)$stmt->fetchColumn();
?>
<div class="mb-3">

    <span class="badge badge-success p-2 mr-2">
        Socios activos:
        <?= number_format($totalActivos, 0, ',', '.') ?>
    </span>

    <span class="badge badge-info p-2">
        Vitalicios:
        <?= number_format($totalVitalicios, 0, ',', '.') ?>
    </span>

</div>
<div class="card card-success card-outline mb-3">
    <div class="card-body">
        <h5>Arrastre de Caja</h5>

        <form id="formControlDiario">
            <div class="row">
                <div class="col-md-4">
                    <label>Monto Inicial</label>
                    <input type="number" step="0.01" id="montoInicialDia" class="form-control" required>
                    <small class="text-muted">
                        Se precarga con el valor actual. Solo se usa para carga inicial o corrección manual.
                    </small>
                </div>
                <div class="col-md-4">

                    <label>Fondo Inicial</label>

                    <input
                        type="number"
                        step="0.01"
                        id="fondoInicialDia"
                        class="form-control">

                    <small class="text-muted">
                        Se precarga con el valor actual. Solo para carga inicial o corrección manual.
                    </small>

                </div>
                <div class="col-md-4 d-flex align-items-end mb-4">
                    <button type="submit" class="btn btn-success">
                        Guardar Monto Inicial
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
<div class="row">

    <div class="col-md-6">
        <div class="card card-warning card-outline">
            <div class="card-body text-center">
                <h6>Monto Inicial</h6>
                <h3 id="kpiInicial">$0.00</h3>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card card-info card-outline">
            <div class="card-body text-center">
                <h6>Fondo Inicial</h6>
                <h3 id="kpiFondoInicial">$0.00</h3>
            </div>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-md-6">
        <div class="card card-warning card-outline">
            <div class="card-body text-center">
                <h6>Caja Acumulada</h6>
                <h3 id="kpiCaja">$0.00</h3>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card card-info card-outline">
            <div class="card-body text-center">
                <h6>Fondos Acumulados</h6>
                <h3 id="kpiFondos">$0.00</h3>
            </div>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-md-12">
        <div class="card card-success card-outline">
            <div class="card-body text-center">
                <h6>Total Disponible</h6>
                <h3 id="kpiTotal">$0.00</h3>
            </div>
        </div>
    </div>

</div>
<table id="tablaLibroDiario"
    class="table table-striped table-hover">

    <thead>
        <tr>
            <th>Fecha</th>
            <th>Monto Inicial</th>
            <th>Fondo Inicial</th>
            <th>Caja</th>
            <th>Fondos</th>
            <th>Total</th>
        </tr>
    </thead>

    <tbody id="tablaControlDiario"></tbody>
  
</table>
<!-- <div class="card card-info card-outline">
    <div class="card-body">
        <h5>📊 Historial Diario</h5>

        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Monto Inicial</th>
                    <th>Cajas (Arrastre)</th>
                    <th>Fondos (Arrastre)</th>
                    <th>Total Clínica</th>
                </tr>
            </thead>
            <tbody id="tablaControlDiario"></tbody>
        </table>
    </div>
</div> -->
<script>
    const BASE_URL = "/salarivadavia/sistema";
    // ==========================
    // CARGA GENERAL
    // ==========================
    function cargarTodo() {
        cargarDashboard();
        cargarControlDiario();
    }

    // ==========================
    // DASHBOARD ERP
    // ==========================
    function cargarDashboard() {

        $.get(BASE_URL + "/erp/api/dashboard.php", function(res) {

            if (!res.success) return;

            let k = res.kpis || {};

            $("#kpiInicial").text(
                "$" + formatearNumero(k.monto_inicial)
            );

            $("#kpiFondoInicial").text(
                "$" + formatearNumero(k.fondo_inicial)
            );

            // Precargar los inputs del form con los valores reales,
            // para que si el usuario no los toca no se pisen con 0
            if (!$("#montoInicialDia").data("tocado")) {
                $("#montoInicialDia").val(Number(k.monto_inicial || 0).toFixed(2));
            }
            if (!$("#fondoInicialDia").data("tocado")) {
                $("#fondoInicialDia").val(Number(k.fondo_inicial || 0).toFixed(2));
            }

            $("#kpiCaja").text(
                "$" + formatearNumero(k.caja)
            );

            $("#kpiFondos").text(
                "$" + formatearNumero(k.fondo)
            );

            $("#kpiTotal").text(
                "$" + formatearNumero(k.total)
            );

        }, "json");
    }

    // ==========================
    // FORMATO
    // ==========================
    function formatearNumero(valor) {
        return Number(valor || 0).toLocaleString('es-AR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    // ==========================
    // TABLA ERP
    // ==========================
    function cargarControlDiario() {

        $.get(BASE_URL + "/erp/api/libro_diario.php", function(res) {

            if (!res.success) return;

            let html = "";

            (res.data || []).forEach(r => {

                html += `
            <tr>
                <td>${r.fecha}</td>

                <td>$${formatearNumero(r.monto_inicial)}</td>

                <td>$${formatearNumero(r.fondo_inicial)}</td>

                <td>$${formatearNumero(r.saldo_caja)}</td>

                <td>$${formatearNumero(r.saldo_fondo)}</td>

                <td class="font-weight-bold text-success">
                    $${formatearNumero(r.saldo_total)}
                </td>
            </tr>
            `;
            });

            $("#tablaControlDiario").html(html);

            if ($.fn.DataTable.isDataTable("#tablaLibroDiario")) {
                $("#tablaLibroDiario").DataTable().destroy();
            }

            $("#tablaLibroDiario").DataTable({

                responsive: true,

                pageLength: 25,

                order: [
                    [0, "desc"]
                ],

                language: {
                    url: "//cdn.datatables.net/plug-ins/1.13.8/i18n/es-ES.json"
                },

                dom: 'Bfrtip',

                buttons: [

                    {
                        extend: 'copy',
                        text: 'Copiar'
                    },

                    {
                        extend: 'excel',
                        text: 'Excel'
                    },

                    {
                        extend: 'pdf',
                        text: 'PDF'
                    },

                    {
                        extend: 'print',
                        text: 'Imprimir'
                    }

                ]
            });

        }, "json");
    }

    // ==========================
    // GUARDAR MONTO INICIAL
    // ==========================
    $("#formControlDiario").submit(function(e) {
        e.preventDefault();

        const monto = parseFloat($("#montoInicialDia").val());
        const fondo = parseFloat($("#fondoInicialDia").val()) || 0;

        if (isNaN(monto) || monto < 0) {
            Swal.fire("Error", "Monto inválido", "error");
            return;
        }

        $.ajax({
            url: BASE_URL + "/erp/api/caja/apertura.php",
            type: "POST",
            contentType: "application/json",
            dataType: "json",
            data: JSON.stringify({
                monto_inicial: monto,
                fondo_inicial: fondo
            }),
            success: function(res) {
                if (res.success) {
                    Swal.fire("OK", "Caja actualizada", "success");
                    $("#montoInicialDia, #fondoInicialDia").data("tocado", false);
                    cargarDashboard();
                    cargarControlDiario();
                } else {
                    Swal.fire("Error", res.message, "error");
                }
            },
            error: function(xhr) {
                Swal.fire("Error", "No se pudo conectar: " + xhr.status + " " + xhr.statusText, "error");
                console.error(xhr.responseText);
            }
        });
    });

    // ==========================
    // INIT
    // ==========================
    $(document).ready(function() {
        cargarTodo();

        $("#montoInicialDia, #fondoInicialDia").on("input", function() {
            $(this).data("tocado", true);
        });
    });
</script>