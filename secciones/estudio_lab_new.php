<?php
require_once __DIR__ . '/../inc/db.php';

$rand = rand(1, 9999);
$v = $_GET['v'] ?? '';

$swalGuardado = false;
$swalError = false;

$estudio = '';
$valor = '';

if (isset($_POST['guardar'])) {

    $estudio = trim($_POST['estudio'] ?? '');
    $valor = trim($_POST['valor'] ?? '');

    // ✅ soporta coma o punto
    $valor = str_replace(',', '.', $valor);

    try {

        // =========================
        // VALIDAR DUPLICADO
        // =========================
        $stmt = $pdo->prepare("
            SELECT id 
            FROM estudio_lab 
            WHERE estudio = :estudio
            LIMIT 1
        ");
        $stmt->execute([':estudio' => $estudio]);

        if ($stmt->rowCount() == 0) {

            // =========================
            // INSERT
            // =========================
            $insert = $pdo->prepare("
                INSERT INTO estudio_lab (estudio, valor)
                VALUES (:estudio, :valor)
            ");

            $insert->execute([
                ':estudio' => $estudio,
                ':valor' => $valor
            ]);

            $swalGuardado = true;

            // limpiar campos
            $estudio = '';
            $valor = '';

        } else {
            $swalError = "El estudio ya se encuentra registrado.";
        }

    } catch (PDOException $e) {
        $swalError = "Error al guardar el estudio.";
    }
}
?>

<div class="row">
    <div class="col-12">

        <div class="card card-info card-outline">

            <!-- HEADER -->
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-flask"></i> Nuevo Estudio de Laboratorio
                </h3>
            </div>

            <!-- BODY -->
            <div class="card-body">

                <form method="POST"
                    action="./<?= ($v != '' ? 'index_clean.php' : '') ?>?seccion=estudio_lab_new&v=<?= $v ?>&nc=<?= $rand ?>">

                    <div class="row">

                        <!-- ESTUDIO -->
                        <div class="col-md-8 form-group">
                            <label>Estudio <span class="text-danger">*</span></label>
                            <input type="text"
                                   name="estudio"
                                   class="form-control"
                                   value="<?= htmlspecialchars($estudio) ?>"
                                   required>
                        </div>

                        <!-- VALOR -->
                        <div class="col-md-4 form-group">
                            <label>Valor <span class="text-danger">*</span></label>
                            <input type="number"
                                   step="0.01"
                                   min="0"
                                   name="valor"
                                   class="form-control"
                                   value="<?= htmlspecialchars($valor) ?>"
                                   placeholder="Ej: 1500.50"
                                   required>
                        </div>

                    </div>

                    <!-- BOTONES -->
                    <div class="text-right mt-3">

                        <?php if ($v != ''): ?>
                            <a href="./<?= $_SESSION["volver"] ?>&nc=<?= $rand ?>" class="btn btn-secondary">
                                Volver al turno
                            </a>
                        <?php else: ?>
                            <a href="./?seccion=estudios_laboratorio&nc=<?= $rand ?>" class="btn btn-secondary">
                                Volver
                            </a>
                        <?php endif; ?>

                        <button type="submit" name="guardar" class="btn btn-success">
                            <i class="fa fa-save"></i> Guardar
                        </button>

                    </div>

                </form>

            </div>
        </div>
    </div>
</div>

<!-- SWEET ALERT -->
<?php if ($swalGuardado || $swalError): ?>
<script>
document.addEventListener("DOMContentLoaded", function () {

    <?php if ($swalGuardado): ?>
        Swal.fire({
            icon: 'success',
            title: 'Estudio guardado',
            text: 'Se registró correctamente',
            confirmButtonColor: '#28a745'
        }).then(() => {
            window.location.href = './?seccion=estudios_laboratorio&nc=<?= $rand ?>';
        });
    <?php elseif ($swalError): ?>
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: '<?= addslashes($swalError) ?>',
            confirmButtonColor: '#dc3545'
        });
    <?php endif; ?>

});
</script>
<?php endif; ?>