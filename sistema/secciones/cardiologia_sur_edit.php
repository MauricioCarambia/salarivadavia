<?php
require_once __DIR__ . '/../inc/db.php';

$rand = rand(1, 9999);
$id = $_GET['id'] ?? 0;
$v = $_GET['v'] ?? '';

$swalGuardado = false;
$swalError = false;

/* ===============================
OBTENER TURNO
=============================== */
$stmt = $pdo->prepare("
    SELECT *
    FROM cardiologia_sur
    WHERE id = :id
    LIMIT 1
");

$stmt->execute([':id' => $id]);
$rArray = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$rArray) {
    echo '<div class="alert alert-danger">El turno no existe.</div>';
    exit;
}

/* ===============================
ACTUALIZAR
=============================== */
if (isset($_POST['guardar'])) {

    try {

        $datos = [
            ':apellido' => trim($_POST['apellido']),
            ':nombre' => trim($_POST['nombre']),
            ':documento' => trim($_POST['documento']),
            ':celular' => trim($_POST['celular']),
            ':nacimiento' => $_POST['nacimiento'] ?? null,
            ':domicilio' => trim($_POST['domicilio']),
            ':obra_social' => trim($_POST['obra_social']),
            ':estudio' => trim($_POST['estudio']),
            ':valor' => $_POST['valor'] ?? 0,
            ':cobrado' => $_POST['cobrado'] ?? 0,
            ':turno' => trim($_POST['turno']),
            ':aviso' => $_POST['aviso'] ?? '',
            ':id' => $id
        ];

        $update = $pdo->prepare("
    UPDATE cardiologia_sur SET
        apellido = :apellido,
        nombre = :nombre,
        documento = :documento,
        celular = :celular,
        nacimiento = :nacimiento,
        domicilio = :domicilio,
        obra_social = :obra_social,
        estudio = :estudio,
        valor = :valor,
        cobrado = :cobrado,
        turno = :turno,
        aviso = :aviso
    WHERE id = :id
");
        $update->execute($datos);

        $swalGuardado = true;

        // recargar datos
        $stmt->execute([':id' => $id]);
        $rArray = $stmt->fetch(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        $swalError = "No se pudo actualizar el turno.";
    }
}
?>
<!-- Main Wrapper -->
<div class="row">
    <div class="col-12">
        <div class="card card-info card-outline">

            <!-- HEADER -->
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-edit"></i> Editar Turno - Cardiología
                </h3>
            </div>

            <!-- BODY -->
            <div class="card-body">

                <form method="POST" action="./?seccion=cardiologia_sur_edit&id=<?= $id ?>&v=<?= $v ?>&nc=<?= $rand ?>">

                    <div class="row">

                        <div class="col-md-6 form-group">
                            <label>Apellido</label>
                            <input type="text" name="apellido" class="form-control"
                                value="<?= htmlspecialchars($rArray['apellido']) ?>" required>
                        </div>

                        <div class="col-md-6 form-group">
                            <label>Nombre</label>
                            <input type="text" name="nombre" class="form-control"
                                value="<?= htmlspecialchars($rArray['nombre']) ?>" required>
                        </div>

                        <div class="col-md-6 form-group">
                            <label>DNI</label>
                            <input type="text" name="documento" class="form-control"
                                value="<?= htmlspecialchars($rArray['documento']) ?>" required>
                        </div>

                        <div class="col-md-6 form-group">
                            <label>Celular</label>
                            <input type="text" name="celular" class="form-control"
                                value="<?= htmlspecialchars($rArray['celular']) ?>" required>
                        </div>
                        <div class="col-md-4 form-group">
                            <label>Fecha de Nacimiento</label>
                            <input type="date" name="nacimiento" class="form-control"
                                value="<?= htmlspecialchars($rArray['nacimiento']) ?>">
                        </div>

                        <div class="col-md-4 form-group">
                            <label>Dirección</label>
                            <input type="text" name="domicilio" class="form-control"
                                value="<?= htmlspecialchars($rArray['domicilio']) ?>">
                        </div>

                        <div class="col-md-4 form-group">
                            <label>Obra Social</label>
                            <input type="text" name="obra_social" class="form-control"
                                value="<?= htmlspecialchars($rArray['obra_social']) ?>">
                        </div>
                        <div class="col-md-4 form-group">
                            <label>Estudio</label>
                            <input type="text" name="estudio" class="form-control"
                                value="<?= htmlspecialchars($rArray['estudio']) ?>" required>
                        </div>

                        <div class="col-md-4 form-group">
                            <label>Valor Médico</label>
                            <input type="number" step="0.01" name="valor" class="form-control"
                                value="<?= htmlspecialchars($rArray['valor']) ?>" required>
                        </div>

                        <div class="col-md-4 form-group">
                            <label>Cobrado Paciente</label>
                            <input type="number" step="0.01" name="cobrado" class="form-control"
                                value="<?= htmlspecialchars($rArray['cobrado']) ?>" required>
                        </div>

                        <div class="col-md-4 form-group">
                            <label>Turno</label>
                            <input type="text" name="turno" class="form-control"
                                value="<?= htmlspecialchars($rArray['turno']) ?>">
                        </div>

                        <div class="col-md-4 form-group">
                            <label>Aviso</label>
                            <select name="aviso" class="form-control">
                                <option value="">Seleccionar</option>
                                <option value="Si" <?= $rArray['aviso'] == 'Si' ? 'selected' : '' ?>>Sí</option>
                                <option value="No" <?= $rArray['aviso'] == 'No' ? 'selected' : '' ?>>No</option>
                            </select>
                        </div>

                    </div>

                    <div class="text-right mt-3">

                        <?php if ($v != ''): ?>
                            <a href="./<?= $_SESSION["volver"] ?>&nc=<?= $rand ?>" class="btn btn-secondary">
                                Volver
                            </a>
                        <?php else: ?>
                            <a href="./?seccion=estudios_cardiologia&nc=<?= $rand ?>" class="btn btn-secondary">
                                Volver
                            </a>
                        <?php endif; ?>

                        <button type="submit" name="guardar" class="btn btn-primary">
                            <i class="fa fa-save"></i> Guardar cambios
                        </button>

                    </div>

                </form>

            </div>
        </div>
    </div>
</div>
<?php if ($swalGuardado || $swalError): ?>
    <script>
        document.addEventListener("DOMContentLoaded", function () {

            <?php if ($swalGuardado): ?>
                Swal.fire({
                    icon: 'success',
                    title: 'Turno actualizado',
                    text: 'Los cambios se guardaron correctamente.',
                    confirmButtonColor: '#28a745'
                }).then(() => {
                    window.location.href = './?seccion=estudios_cardiologia&nc=<?= $rand ?>';
                });
            <?php elseif ($swalError): ?>
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: '<?= addslashes($swalError) ?>'
                });
            <?php endif; ?>

        });
    </script>
<?php endif; ?>