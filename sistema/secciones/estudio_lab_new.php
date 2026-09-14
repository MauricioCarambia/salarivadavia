<?php
require_once __DIR__ . '/../inc/db.php';

$rand = rand(1, 9999);
$v = $_GET['v'] ?? '';

$swalGuardado = false;
$swalMensaje = '';
$swalError = false;

$estudio = '';
$valor = '';
$valoresPorLab = [];

$laboratorios = $pdo->query("
    SELECT id, nombre
    FROM laboratorios
    ORDER BY nombre ASC
")->fetchAll(PDO::FETCH_ASSOC);

$modo = $_POST['modo'] ?? 'individual';
$laboratorio_id = $_POST['laboratorio_id'] ?? ($_GET['lab_id'] ?? '');

if (isset($_POST['guardar'])) {

    $estudio = trim($_POST['estudio'] ?? '');

    if ($estudio === '') {

        $swalError = "Ingresá el nombre del estudio.";

    } elseif ($modo === 'todos') {

        // =========================
        // CARGA A TODOS LOS LABORATORIOS
        // (precio individual por laboratorio)
        // =========================
        $valoresPorLab = $_POST['valores'] ?? [];

        $cargados = [];
        $saltadosDuplicado = [];
        $sinPrecio = [];

        foreach ($laboratorios as $lab) {

            $labId = $lab['id'];
            $valorLab = trim($valoresPorLab[$labId] ?? '');

            if ($valorLab === '') {
                $sinPrecio[] = $lab['nombre'];
                continue;
            }

            $valorLab = str_replace(',', '.', $valorLab);

            try {

                $stmt = $pdo->prepare("
                    SELECT id FROM estudio_lab
                    WHERE estudio = :estudio AND laboratorio_id = :laboratorio_id
                    LIMIT 1
                ");
                $stmt->execute([':estudio' => $estudio, ':laboratorio_id' => $labId]);

                if ($stmt->rowCount() > 0) {
                    $saltadosDuplicado[] = $lab['nombre'];
                    continue;
                }

                $insert = $pdo->prepare("
                    INSERT INTO estudio_lab (estudio, valor, laboratorio_id)
                    VALUES (:estudio, :valor, :laboratorio_id)
                ");
                $insert->execute([
                    ':estudio' => $estudio,
                    ':valor' => $valorLab,
                    ':laboratorio_id' => $labId
                ]);

                $cargados[] = $lab['nombre'];

            } catch (PDOException $e) {
                $sinPrecio[] = $lab['nombre'];
            }
        }

        if ($cargados) {

            $swalGuardado = true;
            $partes = [count($cargados) . ' laboratorio(s): ' . implode(', ', $cargados)];

            if ($saltadosDuplicado) {
                $partes[] = 'Ya existía en: ' . implode(', ', $saltadosDuplicado);
            }

            if ($sinPrecio) {
                $partes[] = 'Sin precio (no cargado): ' . implode(', ', $sinPrecio);
            }

            $swalMensaje = implode(' | ', $partes);

            // limpiar
            $estudio = '';
            $valoresPorLab = [];

        } else {
            $swalError = "No se cargó en ningún laboratorio. " .
                ($saltadosDuplicado ? "Ya existía en: " . implode(', ', $saltadosDuplicado) . ". " : "") .
                ($sinPrecio ? "Faltó precio en: " . implode(', ', $sinPrecio) . "." : "");
        }

    } else {

        // =========================
        // CARGA A UN SOLO LABORATORIO
        // =========================
        $valor = trim($_POST['valor'] ?? '');
        $laboratorio_id = trim($_POST['laboratorio_id'] ?? '');

        // ✅ soporta coma o punto
        $valor = str_replace(',', '.', $valor);

        if ($laboratorio_id === '') {

            $swalError = "Seleccioná un laboratorio.";

        } else {

            try {

                // VALIDAR DUPLICADO (por laboratorio)
                $stmt = $pdo->prepare("
                    SELECT id
                    FROM estudio_lab
                    WHERE estudio = :estudio AND laboratorio_id = :laboratorio_id
                    LIMIT 1
                ");
                $stmt->execute([':estudio' => $estudio, ':laboratorio_id' => $laboratorio_id]);

                if ($stmt->rowCount() == 0) {

                    $insert = $pdo->prepare("
                        INSERT INTO estudio_lab (estudio, valor, laboratorio_id)
                        VALUES (:estudio, :valor, :laboratorio_id)
                    ");

                    $insert->execute([
                        ':estudio' => $estudio,
                        ':valor' => $valor,
                        ':laboratorio_id' => $laboratorio_id
                    ]);

                    $swalGuardado = true;
                    $swalMensaje = 'Se registró correctamente';

                    // limpiar campos
                    $estudio = '';
                    $valor = '';

                } else {
                    $swalError = "El estudio ya se encuentra registrado para ese laboratorio.";
                }

            } catch (PDOException $e) {
                $swalError = "Error al guardar el estudio.";
            }
        }
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

                <form method="POST" id="formNuevoEstudio"
                    action="./<?= ($v != '' ? 'index_clean.php' : '') ?>?seccion=estudio_lab_new&v=<?= $v ?>&nc=<?= $rand ?>">

                    <input type="hidden" name="modo" id="modo" value="<?= htmlspecialchars($modo) ?>">

                    <div class="row">

                        <!-- MODO DE CARGA -->
                        <div class="col-md-4 form-group">
                            <label>Laboratorio <span class="text-danger">*</span></label>
                            <select id="selector_modo" class="form-control">
                                <option value="__TODOS__" <?= ($modo === 'todos') ? 'selected' : '' ?>>
                                    🔁 Todos los laboratorios (precio individual)
                                </option>
                                <?php foreach ($laboratorios as $lab): ?>
                                    <option value="<?= $lab['id'] ?>" <?= ($modo !== 'todos' && (string) $laboratorio_id === (string) $lab['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($lab['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" name="laboratorio_id" id="laboratorio_id_input" value="<?= htmlspecialchars($laboratorio_id) ?>">
                        </div>

                        <!-- ESTUDIO -->
                        <div class="col-md-5 form-group">
                            <label>Estudio <span class="text-danger">*</span></label>
                            <input type="text"
                                   name="estudio"
                                   class="form-control"
                                   value="<?= htmlspecialchars($estudio) ?>"
                                   required>
                        </div>

                        <!-- VALOR (modo individual) -->
                        <div class="col-md-3 form-group" id="bloque_valor_individual">
                            <label>Valor <span class="text-danger">*</span></label>
                            <input type="number"
                                   step="0.01"
                                   min="0"
                                   name="valor"
                                   id="input_valor_individual"
                                   class="form-control"
                                   value="<?= htmlspecialchars($valor) ?>"
                                   placeholder="Ej: 1500.50">
                        </div>

                    </div>

                    <!-- VALORES (modo todos los laboratorios) -->
                    <div class="row" id="bloque_valores_todos" style="display:none;">
                        <div class="col-12">
                            <label>Precio por laboratorio</label>
                            <small class="form-text text-muted mt-0 mb-2">
                                Dejá vacío el precio de un laboratorio si no querés cargarle este estudio.
                            </small>
                        </div>

                        <?php foreach ($laboratorios as $lab): ?>
                            <div class="col-md-3 form-group">
                                <label><?= htmlspecialchars($lab['nombre']) ?></label>
                                <input type="number"
                                       step="0.01"
                                       min="0"
                                       name="valores[<?= $lab['id'] ?>]"
                                       class="form-control input_valor_lab"
                                       value="<?= htmlspecialchars($valoresPorLab[$lab['id']] ?? '') ?>"
                                       placeholder="Ej: 1500.50">
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- BOTONES -->
                    <div class="text-right mt-3">

                        <?php if ($v != ''): ?>
                            <a href="./<?= $_SESSION["volver"] ?>&nc=<?= $rand ?>" class="btn btn-secondary">
                                Volver al turno
                            </a>
                        <?php else: ?>
                            <a href="./?seccion=estudios_laboratorio&lab_id=<?= urlencode($laboratorio_id) ?>&nc=<?= $rand ?>" class="btn btn-secondary">
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

<script>
    (function () {

        var selector = document.getElementById('selector_modo');
        var modoInput = document.getElementById('modo');
        var labIdInput = document.getElementById('laboratorio_id_input');
        var bloqueIndividual = document.getElementById('bloque_valor_individual');
        var inputIndividual = document.getElementById('input_valor_individual');
        var bloqueTodos = document.getElementById('bloque_valores_todos');

        function aplicarModo() {

            if (selector.value === '__TODOS__') {
                modoInput.value = 'todos';
                labIdInput.value = '';
                bloqueIndividual.style.display = 'none';
                inputIndividual.required = false;
                bloqueTodos.style.display = 'flex';
            } else {
                modoInput.value = 'individual';
                labIdInput.value = selector.value;
                bloqueIndividual.style.display = '';
                inputIndividual.required = true;
                bloqueTodos.style.display = 'none';
            }
        }

        selector.addEventListener('change', aplicarModo);
        aplicarModo();

        document.getElementById('formNuevoEstudio').addEventListener('submit', function (e) {

            if (modoInput.value === 'todos') {

                var inputs = document.querySelectorAll('.input_valor_lab');
                var algunoCompleto = Array.prototype.some.call(inputs, function (i) {
                    return i.value.trim() !== '';
                });

                if (!algunoCompleto) {
                    e.preventDefault();
                    Swal.fire('Atención', 'Cargá el precio de al menos un laboratorio', 'warning');
                }
            }
        });

    })();
</script>

<!-- SWEET ALERT -->
<?php if ($swalGuardado || $swalError): ?>
<script>
document.addEventListener("DOMContentLoaded", function () {

    <?php if ($swalGuardado): ?>
        Swal.fire({
            icon: 'success',
            title: 'Estudio guardado',
            text: '<?= addslashes($swalMensaje) ?>',
            confirmButtonColor: '#28a745'
        }).then(() => {
            window.location.href = './?seccion=estudios_laboratorio&lab_id=<?= urlencode($laboratorio_id) ?>&nc=<?= $rand ?>';
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
