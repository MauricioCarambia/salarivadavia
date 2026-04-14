<div class="card card-primary card-outline">
    <div class="card-body">

        <label>💰 Dinero inicial en caja</label>
        <input type="number" id="dineroInicial" class="form-control mb-3" value="0">

        <h4 class="mt-4">📊 Detalle de Cajas</h4>

        <div class="table-responsive">
            <table class="table table-bordered table-striped" id="tablaCaja">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Ingresos</th>
                        <th>Fondos</th>
                        <th>Total Diario</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Se llena por AJAX -->
                </tbody>
                <tfoot>
                    <tr>
                        <th>TOTALES</th>
                        <th id="totalIngresos">$ 0</th>
                        <th id="totalFondos">$ 0</th>
                        <th id="totalGeneral">$ 0</th>
                    </tr>
                </tfoot>
            </table>
        </div>

        <h4 class="mt-4">💵 Resultado Final</h4>
        <h2 id="resultado">$ 0</h2>

    </div>
</div>
<script>
  let totalIngresos = 0;
let totalFondos = 0;

function cargarDatosCaja() {

    $.get('ajax/obtener_totales_cajas.php', function(res) {

        let tbody = '';
        totalIngresos = 0;
        totalFondos = 0;

        res.forEach(row => {

            let ingresos = parseFloat(row.ingresos);
            let fondos = parseFloat(row.fondos);

            let totalDia = ingresos - fondos;

            totalIngresos += ingresos;
            totalFondos += fondos;

            tbody += `
                <tr>
                    <td>${row.fecha}</td>
                    <td>$ ${ingresos.toFixed(2)}</td>
                    <td>$ ${fondos.toFixed(2)}</td>
                    <td>$ ${totalDia.toFixed(2)}</td>
                </tr>
            `;
        });

        $('#tablaCaja tbody').html(tbody);

        $('#totalIngresos').text('$ ' + totalIngresos.toFixed(2));
        $('#totalFondos').text('$ ' + totalFondos.toFixed(2));

        calcularTotalFinal();
    }, 'json');
}

function calcularTotalFinal() {

    let inicial = parseFloat($('#dineroInicial').val()) || 0;

    let total = inicial + totalIngresos - totalFondos;

    $('#totalGeneral').text('$ ' + total.toFixed(2));
    $('#resultado').text('$ ' + total.toFixed(2));
}

// evento input
$('#dineroInicial').on('input', calcularTotalFinal);

// iniciar
cargarDatosCaja();
</script>