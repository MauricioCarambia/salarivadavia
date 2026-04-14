<?php
require_once 'inc/db.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$mensaje = '';
$tipoMensaje = '';

/* =========================
   GUARDAR PAGO
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $monto = isset($_POST['monto']) ? (float) $_POST['monto'] : 0;
    $fecha = $_POST['fecha'] ?? '';

    if ($monto > 0 && $fecha) {

        $fecha .= '-01';

       $stmt = $conexion->prepare("
    INSERT INTO pagos_afiliados (paciente_id, monto, fecha_pago, fecha_correspondiente)
    SELECT 
        p.Id,
        :monto,
        NOW(),
        :fecha
    FROM pacientes p
    WHERE SUBSTRING_INDEX(SUBSTRING_INDEX(p.nro_afiliado, '/', 1), ' ', 1) = (
        SELECT SUBSTRING_INDEX(SUBSTRING_INDEX(nro_afiliado, '/', 1), ' ', 1)
        FROM pacientes
        WHERE Id = :paciente_id
    )
");

       $stmt->execute([
    ':paciente_id' => $id,
    ':monto' => $monto,
    ':fecha' => $fecha
]);

        $tipoMensaje = 'success';
        $mensaje = 'Pago cargado correctamente';
    } else {
        $tipoMensaje = 'error';
        $mensaje = 'Datos inválidos';
    }
}

/* =========================
   ULTIMO MES PAGADO
========================= */
$stmt = $conexion->prepare("
    SELECT 
        MONTH(fecha_correspondiente) AS mes,
        YEAR(fecha_correspondiente) AS anio
    FROM pagos_afiliados
    WHERE paciente_id = :id
    ORDER BY fecha_correspondiente DESC
    LIMIT 1
");
$stmt->execute([':id' => $id]);
$ultimoPago = $stmt->fetch(PDO::FETCH_ASSOC);

/* =========================
   DATOS PACIENTE
========================= */
$stmtPaciente = $conexion->prepare("
    SELECT 
        os.obra_social,
        p.*
    FROM pacientes p
    LEFT JOIN obras_sociales os 
        ON os.Id = p.obra_social_id
    WHERE p.Id = :id
");
$stmtPaciente->execute([':id' => $id]);
$paciente = $stmtPaciente->fetch(PDO::FETCH_ASSOC);

/* =========================
   MESES
========================= */
$meses = [
    'Enero',
    'Febrero',
    'Marzo',
    'Abril',
    'Mayo',
    'Junio',
    'Julio',
    'Agosto',
    'Septiembre',
    'Octubre',
    'Noviembre',
    'Diciembre'
];
?>

<div class="row">
    <div class="col-6">
        <div class="card card-info card-outline">
            <h1  class="ml-2"> Nuevo pago de afiliado

            </h1>
            <div class="card-header">
                <h3 class="card-title">
                    Último mes:
                    <?= $ultimoPago
                        ? $meses[$ultimoPago['mes'] - 1] . ' de ' . $ultimoPago['anio']
                        : 'Sin pagos registrados'; ?>
                </h3>
            </div>

            <div class="card-body">

                <form method="POST">

                    <div class="form-group">
                        <label>Monto</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text">$</span>
                            </div>
                            <input type="number" name="monto" step="0.01" min="0" class="form-control" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Mes correspondiente</label>
                        <input type="month" name="fecha" class="form-control" value="<?= date('Y-m') ?>" required>
                    </div>
                    
                    <button type="button" id="btnPagoMasivo" class="btn btn-success float-right ml-2">
    <i class="fa fa-users"></i> Pago Masivo
</button>
<a href="./?seccion=socios" class="btn btn-secondary float-right">
                        Volver
                    </a>
                </form>

            </div>
        </div>
    </div>


    <!-- ================= PACIENTE ================= -->

    <div class="col-6">
        <div class="card card-info card-outline">

            <div class="card-header">
                <h3 class="card-title">Datos del paciente</h3>
            </div>

            <div class="card-body">

                <?php if ($paciente): ?>

                    <strong>Apellido:</strong> <?= $paciente['apellido'] ?><br>
                    <strong>Nombre:</strong> <?= $paciente['nombre'] ?><br>
                    <strong>Domicilio:</strong> <?= $paciente['domicilio'] ?><br>
                    <strong>Provincia:</strong> <?= $paciente['provincia'] ?><br>
                    <strong>Localidad:</strong> <?= $paciente['localidad'] ?><br>
                    <strong>Celular:</strong> <?= $paciente['celular'] ?><br>
                    <strong>Email:</strong> <?= $paciente['email'] ?><br>
                    <strong>Documento:</strong> <?= $paciente['tipo_documento'] ?>     <?= $paciente['documento'] ?><br>
                    <strong>N° socio:</strong> <?= $paciente['nro_afiliado'] ?><br>
                    <strong>Obra social:</strong> <?= $paciente['obra_social'] ?><br>

                <?php else: ?>
                    <div class="alert alert-warning">Paciente no encontrado</div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="modalMasivo">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5>Pago masivo por afiliado</h5>
                <button class="close" data-dismiss="modal">&times;</button>
            </div>

            <div class="modal-body" id="contenidoMasivo">
                Cargando...
            </div>

            <div class="modal-footer">
                <button class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button class="btn btn-success" id="confirmarPagoMasivo">
                    Confirmar pago
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ================= SWEET ALERT ================= -->
<?php if ($mensaje): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            Swal.fire({
                icon: '<?= $tipoMensaje ?>',
                title: '<?= $mensaje ?>',
                timer: 2000,
                showConfirmButton: false
            });
        });
    </script>
<?php endif; ?>
<script>
    document.getElementById('btnPagoMasivo').addEventListener('click', function() {

    let pacienteId = <?= $id ?>;

    fetch('ajax/preview_pago_masivo.php?paciente_id=' + pacienteId)
        .then(r => r.json())
        .then(data => {

            let html = `
                <p><strong>Se aplicará el pago a ${data.length} afiliados:</strong></p>
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Afiliado</th>
                            <th>N°</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            data.forEach(p => {
                html += `
                    <tr>
                        <td>${p.apellido} ${p.nombre}</td>
                        <td>${p.nro_afiliado}</td>
                    </tr>
                `;
            });

            html += `</tbody></table>`;

            document.getElementById('contenidoMasivo').innerHTML = html;

            $('#modalMasivo').modal('show');
        });
});
document.getElementById('confirmarPagoMasivo').addEventListener('click', function() {

    let form = document.querySelector('form');
    let formData = new FormData(form);

    fetch('', { // mismo archivo
        method: 'POST',
        body: formData
    })
    .then(() => {
        location.reload();
    });
});
</script>