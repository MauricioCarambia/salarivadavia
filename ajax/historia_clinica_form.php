<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../inc/db.php';

$idHC = (int) ($_GET['id'] ?? 0);
$pacienteId = (int) ($_GET['paciente_id'] ?? 0);
$profesionalId = (int) ($_GET['profesional_id'] ?? 0);

if (!$pacienteId) {
    die('<div class="alert alert-danger">Paciente no recibido</div>');
}

// Traer datos si edita
$hcData = [];
if ($idHC) {
    $stmt = $pdo->prepare("SELECT * FROM historias_clinicas WHERE Id = ? AND paciente_id = ?");
    $stmt->execute([$idHC, $pacienteId]);
    $hcData = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<form id="hcForm">
    <input type="hidden" name="id" value="<?= $idHC ?>">
    <input type="hidden" name="paciente_id" value="<?= $pacienteId ?>">
    <input type="hidden" name="profesional_id" value="<?= $profesionalId ?>">

    <!-- DEBUG (sacalo después) -->
    <!-- <pre>Paciente: <?= $pacienteId ?> | Profesional: <?= $_SESSION['user_id'] ?? 'NO SESSION' ?></pre> -->

    <!-- Fecha -->
    <div class="row mb-3 align-items-center">
        <label class="col-md-3 col-form-label">
            <i class="fas fa-calendar-alt"></i> Fecha
        </label>
        <div class="col-md-9">
            <input type="date" name="fecha" class="form-control" value="<?= $hcData['fecha'] ?? date('Y-m-d') ?>"
                required>
        </div>
    </div>

    <?php
    $campos = [
        'motivo' => ['label' => 'Motivo', 'icon' => 'fa-comment-medical', 'rows' => 2],
        'sintomas' => ['label' => 'Síntomas', 'icon' => 'fa-thermometer-half', 'rows' => 2],
        'vitales' => ['label' => 'Signos vitales', 'icon' => 'fa-heartbeat', 'rows' => 2],
        'examenes' => ['label' => 'Exámenes', 'icon' => 'fa-file-medical', 'rows' => 2],
        'diagnostico' => ['label' => 'Diagnóstico', 'icon' => 'fa-stethoscope', 'rows' => 2],
        'medicamento' => ['label' => 'Medicación', 'icon' => 'fa-pills', 'rows' => 2],
        'texto' => ['label' => 'Observaciones', 'icon' => 'fa-notes-medical', 'rows' => 3]
    ];
    ?>

    <?php foreach ($campos as $campo => $info): ?>
        <div class="row mb-3 align-items-start">
            <label class="col-md-3 col-form-label">
                <i class="fas <?= $info['icon'] ?>"></i> <?= $info['label'] ?>
            </label>
            <div class="col-md-9">
                <textarea name="<?= $campo ?>" class="form-control"
                    rows="<?= $info['rows'] ?>"><?= htmlspecialchars($hcData[$campo] ?? '') ?></textarea>
            </div>
        </div>
    <?php endforeach; ?>

    <div class="d-flex justify-content-end mt-3">
        <button type="submit" class="btn btn-success mr-2">
            <i class="fa fa-save"></i> Guardar
        </button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
            Cancelar
        </button>
    </div>
</form>

<script>
$(document).on('submit', '#hcForm', function (e) {
    e.preventDefault();

    let formData = $(this).serialize();

    $.ajax({
        url: 'ajax/hc_save.php',
        type: 'POST',
        data: formData,
        dataType: 'json',

        beforeSend: function () {
            Swal.fire({
                title: 'Guardando...',
                text: 'Por favor esperá',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
        },

        success: function (res) {

            if (res.success) {

                // 🔥 Mostrar éxito
                Swal.fire({
                    icon: 'success',
                    title: 'Guardado correctamente',
                    timer: 1200,
                    showConfirmButton: false
                });

                // 🔥 Cerrar modal
                let modalEl = document.getElementById('hcModal');
                let modal = bootstrap.Modal.getInstance(modalEl);

                if (modal) {
                    modal.hide();
                }

                // 🔥 Recargar después
                setTimeout(() => {
                    location.reload();
                }, 1300);

            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: res.message
                });
            }
        },

        error: function (xhr) {
            console.error(xhr.responseText);

            Swal.fire({
                icon: 'error',
                title: 'Error inesperado',
                text: 'No se pudo guardar la historia clínica'
            });
        }
    });
});
</script>