<!-- KPIs PRINCIPALES -->
<div class="row">

    <div class="col-md-3">
        <div class="card card-warning card-outline">
            <div class="card-body text-center">
                <h6>Caja Disponible</h6>
                <h3 id="kpiCaja">$0.00</h3>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card card-info card-outline">
            <div class="card-body text-center">
                <h6>Fondos Disponibles</h6>
                <h3 id="kpiFondo">$0.00</h3>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card card-success card-outline">
            <div class="card-body text-center">
                <h6>Total Disponible</h6>
                <h3 id="kpiTotal">$0.00</h3>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card card-secondary card-outline">
            <div class="card-body text-center">
                <h6>Diferencia Hoy</h6>
                <h3 id="kpiDif">$0.00</h3>
            </div>
        </div>
    </div>

</div>

<!-- FONDOS INDIVIDUALES -->
<div class="row mt-3">
    <div class="col-12">
        <h5>Distribución de Fondos</h5>
    </div>

    <div id="contenedorFondos" class="row col-12">
        <!-- Se cargan por JS -->
    </div>
</div>

<hr>

<!-- FLUJO DEL DÍA -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Flujo del Día</h5>
    </div>

    <div class="card-body">

        <div class="row">

            <div class="col-md-6 text-center">
                <h6>Ingresos</h6>
                <h3 class="text-success" id="ingresos">$0.00</h3>
            </div>

            <div class="col-md-6 text-center">
                <h6>Egresos</h6>
                <h3 class="text-danger" id="egresos">$0.00</h3>
            </div>

        </div>

    </div>
</div>

<hr>

<!-- LIBRO DIARIO -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Libro Diario</h5>
    </div>

    <div class="card-body table-responsive">

        <table class="table table-striped table-hover">

            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Caja</th>
                    <th>Fondos</th>
                    <th>Total</th>
                </tr>
            </thead>

            <tbody id="libro"></tbody>

        </table>

    </div>
</div>