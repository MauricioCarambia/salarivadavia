<?php
require_once __DIR__ . '/../inc/db.php';

$rand = rand(1, 9999);
$v = $_GET['v'] ?? '';

$swalGuardado = false;
$swalError = false;

// Valores por defecto
$campos = [
    'apellido' => '',
    'nombre' => '',
    'documento' => '',
    'celular' => '',
    'nacimiento' => '',
    'domicilio' => '',
    'obra_social' => '',
    'estudio' => '',
    'valor' => '',
    'cobrado' => '',
    'turno' => '',
    'aviso' => ''
];

// Rellenar desde POST
foreach ($campos as $key => $val) {
    $campos[$key] = $_POST[$key] ?? '';
}

/* ===============================
GUARDAR TURNO
=============================== */
if (isset($_POST['guardar'])) {

    try {

        $sql = $pdo->prepare("
    INSERT INTO cardiologia_sur
    (
        fecha, apellido, nombre, documento,
        celular, nacimiento, domicilio, obra_social,
        estudio, valor, cobrado,
        turno, aviso
    )
    VALUES
    (
        NOW(), :apellido, :nombre, :documento,
        :celular, :nacimiento, :domicilio, :obra_social,
        :estudio, :valor, :cobrado,
        :turno, :aviso
    )
");

        $sql->execute([
            ':apellido' => $campos['apellido'],
            ':nombre' => $campos['nombre'],
            ':documento' => $campos['documento'],
            ':celular' => $campos['celular'],
            ':nacimiento' => $campos['nacimiento'],
            ':domicilio' => $campos['domicilio'],
            ':obra_social' => $campos['obra_social'],
            ':estudio' => $campos['estudio'],
            ':valor' => $campos['valor'],
            ':cobrado' => $campos['cobrado'],
            ':turno' => $campos['turno'],
            ':aviso' => $campos['aviso']
        ]);

        $swalGuardado = true;

        // limpiar formulario
        $campos = array_map(fn($v) => '', $campos);

    } catch (PDOException $e) {

        $swalError = "No se pudo guardar el turno.";
    }
}
?>

<div class="row">
    <div class="col-12">
        <div class="card card-info card-outline">

            <!-- HEADER -->
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-heartbeat"></i> Nuevo Turno - Cardiología
                </h3>
            </div>

            <!-- BODY -->
            <div class="card-body">


                <form method="POST"
                    action="./<?= ($v != '' ? 'index_clean.php' : '') ?>?seccion=cardiologia_sur_new&v=<?= $v ?>&nc=<?= $rand ?>">

                    <div class="row">

                        <!-- APELLIDO -->
                        <div class="col-md-6 form-group">
                            <label>Apellido</label>
                            <input type="text" name="apellido" class="form-control"
                                value="<?= htmlspecialchars($campos['apellido']) ?>" required>
                        </div>

                        <!-- NOMBRE -->
                        <div class="col-md-6 form-group">
                            <label>Nombre</label>
                            <input type="text" name="nombre" class="form-control"
                                value="<?= htmlspecialchars($campos['nombre']) ?>" required>
                        </div>

                        <!-- DOCUMENTO -->
                        <div class="col-md-6 form-group">
                            <label>DNI</label>
                            <input type="text" name="documento" id="dni" class="form-control"
                                value="<?= htmlspecialchars($campos['documento']) ?>" required>
                        </div>

                        <!-- CELULAR -->
                        <div class="col-md-6 form-group">
                            <label>Celular</label>
                            <input type="text" name="celular" class="form-control"
                                value="<?= htmlspecialchars($campos['celular']) ?>" required>
                        </div>
                        <!-- FECHA NACIMIENTO -->
                        <div class="col-md-4 form-group">
                            <label>Fecha de Nacimiento</label>
                            <input type="date" name="nacimiento" class="form-control"
                                value="<?= htmlspecialchars($campos['nacimiento']) ?>">
                        </div>

                        <!-- domicilio -->
                        <div class="col-md-4 form-group">
                            <label>Dirección</label>
                            <input type="text" name="domicilio" class="form-control"
                                value="<?= htmlspecialchars($campos['domicilio']) ?>">
                        </div>

                        <!-- OBRA SOCIAL -->
                        <div class="col-md-4 form-group">
                            <label>Obra Social</label>
                            <input type="text" name="obra_social" class="form-control"
                                value="<?= htmlspecialchars($campos['obra_social']) ?>">
                        </div>
                        <!-- ESTUDIO -->
                        <div class="col-md-4 form-group">
                            <label>Estudio</label>
                            <input type="text" name="estudio" class="form-control"
                                value="<?= htmlspecialchars($campos['estudio']) ?>" required>
                        </div>

                        <!-- VALOR -->
                        <div class="col-md-4 form-group">
                            <label>Valor Médico</label>
                            <input type="number" step="0.01" name="valor" class="form-control"
                                value="<?= htmlspecialchars($campos['valor']) ?>" required>
                        </div>

                        <!-- COBRADO -->
                        <div class="col-md-4 form-group">
                            <label>Cobrado Paciente</label>
                            <input type="number" step="0.01" name="cobrado" class="form-control"
                                value="<?= htmlspecialchars($campos['cobrado']) ?>" required>
                        </div>

                        <!-- TURNO -->
                        <div class="col-md-4 form-group">
                            <label>Turno</label>
                            <input type="text" name="turno" class="form-control"
                                value="<?= htmlspecialchars($campos['turno']) ?>">
                        </div>

                        <!-- AVISO -->
                        <div class="col-md-4 form-group">
                            <label>Aviso</label>
                            <select name="aviso" class="form-control">
                                <option value="">Seleccionar</option>
                                <option value="Si" <?= $campos['aviso'] == 'Si' ? 'selected' : '' ?>>Sí</option>
                                <option value="No" <?= $campos['aviso'] == 'No' ? 'selected' : '' ?>>No</option>
                            </select>
                        </div>

                    </div>

                    <!-- BOTONES -->
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
                            <i class="fa fa-save"></i> Guardar
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
                    title: 'Turno guardado',
                    text: 'Se registró correctamente.',
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
<script>

    let timeout = null;

    $("#dni").on("keyup", function () {

        clearTimeout(timeout);

        const dni = $(this).val().trim();

        if (dni.length < 6) return;

        timeout = setTimeout(() => {

            $.getJSON("./ajax/buscar_paciente.php", { dni }, function (res) {

                if (res.found) {

                    const p = res.data;

                    $("input[name='apellido']").val(p.apellido);
                    $("input[name='nombre']").val(p.nombre);
                    $("input[name='celular']").val(p.celular);
                    $("input[name='nacimiento']").val(p.nacimiento); // 👈 mapeo
                    $("input[name='domicilio']").val(p.domicilio);         // 👈 mapeo
                 

                    Swal.fire({
                        icon: 'info',
                        title: 'Paciente encontrado',
                        text: 'Se completaron los datos automáticamente',
                        timer: 1500,
                        showConfirmButton: false
                    });

                }

            });

        }, 400); // delay para no saturar
    });

</script>