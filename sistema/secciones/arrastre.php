<div class="card card-success card-outline mb-3">
    <div class="card-body">
        <h5>Arrastre de Caja</h5>

        <form id="formControlDiario">
            <div class="row">
                <div class="col-md-4">
                    <label>Monto Inicial</label>
                    <input type="number" step="0.01" id="montoInicialDia" class="form-control" required>
                    <small class="text-muted">
                        Solo se usa para carga inicial o corrección manual.
                    </small>
                </div>

                <div class="col-md-4 d-flex align-items-end mb-4">
                    <button class="btn btn-success">
                        Guardar Monto Inicial
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
<div class="row mb-3">

    <!-- CAJA inicial -->
    <div class="col-md-4">
        <div class="card card-info card-outline">
            <div class="card-body text-center">
                <h6>Monto inicial cargado</h6>
                <h3 id="kpiInicial">$0.00</h3>
            </div>
        </div>
    </div>
    <!-- CAJA DISPONIBLE -->
    <div class="col-md-4">
        <div class="card card-warning card-outline">
            <div class="card-body text-center">
                <h6>Caja Disponible</h6>
                <h3 id="kpiCaja">$0.00</h3>
            </div>
        </div>
    </div>

    <!-- FONDO DISPONIBLE -->
    <div class="col-md-4">
        <div class="card card-info card-outline">
            <div class="card-body text-center">
                <h6>Fondo Disponible</h6>
                <h3 id="kpiFondos">$0.00</h3>
            </div>
        </div>
    </div>

    <!-- TOTAL -->
    <div class="col-md-12">
        <div class="card card-success card-outline">
            <div class="card-body text-center">
                <h6>Total Disponible</h6>
                <h3 id="kpiTotal">$0.00</h3>
            </div>
        </div>
    </div>

</div>
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
$(document).ready(function() {
    cargarTodo();
    // setInterval(cargarDashboard, 5000);
});

// ==========================
// CARGA GENERAL
// ==========================
function cargarTodo() {
    cargarDashboard();
    cargarControlDiario();
}

// ==========================
// DASHBOARD
// ==========================
function cargarDashboard() {

    $.get("ajax/dashboard_caja.php", function(res) {

        if (!res.success) return;

        let d = res.data;

        // ==========================
        // KPI (YA CALCULADOS EN BACKEND)
        // ==========================
        $("#kpiInicial").text("$" + formatearNumero(d.monto_inicial));

        $("#kpiCaja").text("$" + formatearNumero(d.saldo_caja));

        $("#kpiFondos").text("$" + formatearNumero(d.saldo_fondo));

        $("#kpiTotal").text("$" + formatearNumero(d.saldo_total));

        // sync input
        //$("#montoInicialDia").val(d.monto_inicial);

    }, "json");
}

// ==========================
// FORMATO NUMÉRICO
// ==========================
function formatearNumero(valor) {
    return Number(valor || 0).toLocaleString('es-AR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

// ==========================
// HISTORIAL DIARIO
// ==========================
function cargarControlDiario() {

    $.get("ajax/listar_control_diario.php", function(res) {

        if (!res.success && !Array.isArray(res)) return;

        let data = res.success ? res.data : res;
        let html = "";

        data.forEach(r => {

            html += `
<tr>
    <td>${r.fecha}</td>

    <td>$${formatearNumero(r.monto_inicial)}</td>

    <td>$${formatearNumero(r.total_cajas)}</td>

    <td>$${formatearNumero(r.total_fondos)}</td>

    <td class="font-weight-bold text-success">
        $${formatearNumero(r.saldo_total)}
    </td>
</tr>`;
        });

        $("#tablaControlDiario").html(html);

    }, "json");
}

// ==========================
// GUARDAR MONTO INICIAL
// ==========================
$("#formControlDiario").submit(function(e) {
    e.preventDefault();

    const monto = parseFloat($("#montoInicialDia").val());

    if (isNaN(monto) || monto < 0) {
        Swal.fire("Error", "Monto inválido", "error");
        return;
    }

    $.ajax({
        url: "ajax/guardar_monto_inicial.php",
        type: "POST",
        contentType: "application/json",
        data: JSON.stringify({
            monto_inicial: monto
        }),
        success: function(res) {
            if (res.success) {
                Swal.fire("OK", "Monto actualizado", "success");
                cargarTodo();
            } else {
                Swal.fire("Error", res.message, "error");
            }
        }
    });
});
</script>