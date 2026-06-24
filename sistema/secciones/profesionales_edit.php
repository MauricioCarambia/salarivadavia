<?php
require_once __DIR__ . '/../inc/db.php';

$id = $_GET['id'] ?? 0;
$id = (int)$id;
$swalGuardado = false;
$swalError = false;

// 1. Obtener profesional existente
$stmt = $pdo->prepare("SELECT * FROM profesionales WHERE Id=?");
$stmt->execute([$id]);
$rArray = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$rArray) {
    echo "<div class='alert alert-danger'>Profesional no encontrado.</div>";
    return;
}

// 2. Procesar valores del formulario (Prioriza POST, si no usa DB)
$campos = [
    'documento' => $_POST['documento'] ?? $rArray['documento'],
    'nombre' => $_POST['nombre'] ?? $rArray['nombre'],
    'apellido' => $_POST['apellido'] ?? $rArray['apellido'],
    'provincia' => $_POST['provincia'] ?? $rArray['provincia'],
    'localidad' => $_POST['localidad'] ?? $rArray['localidad'],
    'celular' => $_POST['celular'] ?? $rArray['celular'],
    'fijo' => $_POST['fijo'] ?? $rArray['fijo'],
    'email' => $_POST['email'] ?? $rArray['email'],
    'tipo_documento' => $_POST['tipo_documento'] ?? $rArray['tipo_documento'],
    'nacimiento' => $_POST['nacimiento'] ?? $rArray['nacimiento'],
    'especialidad_id' => $_POST['especialidad_id'] ?? $rArray['especialidad_id'],
    'matricula_nacional' => $_POST['matricula_nacional'] ?? $rArray['matricula_nacional'],
    'matricula_provincial' => $_POST['matricula_provincial'] ?? $rArray['matricula_provincial'],
    'porcentaje' => $_POST['porcentaje'] ?? $rArray['porcentaje'],
    'duracion_turnos' => $_POST['duracion_turnos'] ?? $rArray['duracion_turnos'],
    'usuario' => $_POST['usuario'] ?? $rArray['usuario'],
    'contrasenia' => $_POST['contrasenia'] ?? '',
    'sexo' => $_POST['sexo'] ?? $rArray['sexo'],
    'vacaciones_desde' => $_POST['vacaciones_desde'] ?? $rArray['vacaciones_desde'],
    'vacaciones_hasta' => $_POST['vacaciones_hasta'] ?? $rArray['vacaciones_hasta'],
    'comentario' => $_POST['comentario'] ?? $rArray['comentario'],
];

// 3. Cargar datos para la vista
$stmt = $pdo->prepare("SELECT * FROM profesionales_horarios WHERE profesional_id=?");
$stmt->execute([$id]);
$horarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

$dia   = isset($_POST['dia']) ? (array)$_POST['dia'] : array_column($horarios, 'dia');
$desde = isset($_POST['desde']) ? (array)$_POST['desde'] : array_column($horarios, 'hora_inicio');
$hasta = isset($_POST['hasta']) ? (array)$_POST['hasta'] : array_column($horarios, 'hora_fin');

$stmt = $pdo->prepare("SELECT fecha FROM dias_anulados WHERE profesional_id=? ORDER BY fecha ASC");
$stmt->execute([$id]);
$dias_anulados_db = $stmt->fetchAll(PDO::FETCH_COLUMN);

$firma = $_FILES['firma'] ?? null;
$firmaDibujada = $_POST['firma_dibujada'] ?? '';
$especialidades = $pdo->query("SELECT * FROM especialidades")->fetchAll(PDO::FETCH_ASSOC);
$nombresDias = ['0' => 'Domingo', '1' => 'Lunes', '2' => 'Martes', '3' => 'Miércoles', '4' => 'Jueves', '5' => 'Viernes', '6' => 'Sábado'];
$contador = 1000;

// 4. Lógica de Guardado Unificada
if (isset($_POST['guardar'])) {
    try {
        $pdo->beginTransaction();

        // Validar documento duplicado
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM profesionales WHERE documento=? AND Id<>?");
        $stmt->execute([$campos['documento'], $id]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("El documento {$campos['documento']} ya se encuentra registrado.");
        }

        // Procesar Firma: prioriza la dibujada en el momento, si no hay usa el archivo subido
        $rutaImagen = $rArray['firma'];
        $directorio = 'imagenes/';
        if (!is_dir($directorio)) mkdir($directorio, 0777, true);

        if (!empty($firmaDibujada) && preg_match('/^data:image\/png;base64,/', $firmaDibujada)) {

            $datos = explode(',', $firmaDibujada, 2)[1] ?? '';
            $binario = base64_decode($datos);

            if ($binario === false) {
                throw new Exception("No se pudo procesar la firma dibujada.");
            }

            $nombreArchivo = uniqid() . '_firma.png';
            $rutaImagen = $directorio . $nombreArchivo;
            file_put_contents($rutaImagen, $binario);

        } elseif (!empty($firma['name'])) {

            $nombreArchivo = uniqid() . '_' . basename($firma['name']);
            $rutaImagen = $directorio . $nombreArchivo;
            if (!move_uploaded_file($firma['tmp_name'], $rutaImagen)) {
                throw new Exception("Error al subir la firma.");
            }
        }

        // Update Profesional
        $sql = "UPDATE profesionales SET nombre=?, apellido=?, provincia=?, localidad=?, celular=?, fijo=?, email=?, tipo_documento=?, documento=?, nacimiento=?, especialidad_id=?, matricula_nacional=?, matricula_provincial=?, porcentaje=?, duracion_turnos=?, usuario=?, contrasenia=?, sexo=?, vacaciones_desde=?, vacaciones_hasta=?, comentario=?, firma=? WHERE Id=?";
        $stmt = $pdo->prepare($sql);
                $contraseniaFinal = $campos['contrasenia'] !== ''
            ? password_hash($campos['contrasenia'], PASSWORD_DEFAULT)
            : $rArray['contrasenia'];

        $stmt->execute([$campos['nombre'], $campos['apellido'], $campos['provincia'], $campos['localidad'], $campos['celular'], $campos['fijo'], $campos['email'], $campos['tipo_documento'], $campos['documento'], $campos['nacimiento'], $campos['especialidad_id'], $campos['matricula_nacional'], $campos['matricula_provincial'], $campos['porcentaje'], $campos['duracion_turnos'], $campos['usuario'], $contraseniaFinal, $campos['sexo'], $campos['vacaciones_desde'], $campos['vacaciones_hasta'], $campos['comentario'], $rutaImagen, $id]);

        // Update Horarios (Borrar e insertar de nuevo)
        $pdo->prepare("DELETE FROM profesionales_horarios WHERE profesional_id=?")->execute([$id]);
        $stmtHorario = $pdo->prepare("INSERT INTO profesionales_horarios (profesional_id,dia,hora_inicio,hora_fin) VALUES (?,?,?,?)");
        foreach ($dia as $k => $d) {
            if ($d !== "" && isset($desde[$k], $hasta[$k])) {
                $stmtHorario->execute([$id, $d, $desde[$k], $hasta[$k]]);
            }
        }

        // Update Días Anulados (Solo si hubo cambios en el cliente)
        if (isset($_POST['confirmar_cambio_anulados']) && $_POST['confirmar_cambio_anulados'] == '1') {
            $fechas_a_mantener = isset($_POST['fechas_finales']) ? $_POST['fechas_finales'] : [];
            $pdo->prepare("DELETE FROM dias_anulados WHERE profesional_id=?")->execute([$id]);

            $stmtAnulado = $pdo->prepare("INSERT INTO dias_anulados (profesional_id, fecha) VALUES (?,?)");

            // Reinsertar sobrevivientes
            foreach ($fechas_a_mantener as $f) {
                $stmtAnulado->execute([$id, $f]);
            }

            // Procesar nuevos rangos
            if (isset($_POST['rangos_anulados'])) {
                $diasAtencion = array_unique(array_filter($dia, 'strlen'));
                foreach ($_POST['rangos_anulados'] as $rango) {
                    list($fInicio, $fFin) = explode('|', $rango);
                    $begin = new DateTime($fInicio);
                    $end = (new DateTime($fFin))->modify('+1 day');
                    $period = new DatePeriod($begin, new DateInterval('P1D'), $end);

                    foreach ($period as $dt) {
                        $fecha_formato = $dt->format("Y-m-d");
                        if (in_array($dt->format("w"), $diasAtencion) && !in_array($fecha_formato, $fechas_a_mantener)) {
                            $stmtAnulado->execute([$id, $fecha_formato]);
                            $fechas_a_mantener[] = $fecha_formato;
                        }
                    }
                }
            }
        }

        $pdo->commit();
        $swalGuardado = true;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $swalError = $e->getMessage();
    }
}
?>

<div class="content">
    <div class="card card-info card-outline">
        <div class="card-header">
            <h3 class="card-title">Editar profesional</h3>
        </div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data" id="formProfesional">
                <!-- Inputs de datos personales -->
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Nombre <span class="text-danger">*</span></label>
                        <input type="text" name="nombre" class="form-control" value="<?= htmlspecialchars($campos['nombre']) ?>" required>
                    </div>
                    <div class="form-group col-md-6">
                        <label>Apellido <span class="text-danger">*</span></label>
                        <input type="text" name="apellido" class="form-control" value="<?= htmlspecialchars($campos['apellido']) ?>" required>
                    </div>
                </div>

                <!-- Documento -->
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label>Tipo de documento</label>
                        <select class="form-control" name="tipo_documento">
                            <option value="">Seleccionar</option>
                            <?php foreach (['DNI', 'LE', 'LC', 'CI'] as $tipo): ?>
                                <option value="<?= $tipo ?>" <?= $campos['tipo_documento'] == $tipo ? 'selected' : '' ?>><?= $tipo ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group col-md-8">
                        <label>Documento <span class="text-danger">*</span></label>
                        <input type="number" name="documento" class="form-control" value="<?= htmlspecialchars($campos['documento']) ?>" required>
                    </div>
                </div>

                <!-- Provincia / Localidad -->
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Provincia</label>
                        <input type="text" name="provincia" class="form-control" value="<?= htmlspecialchars($campos['provincia']) ?>">
                    </div>
                    <div class="form-group col-md-6">
                        <label>Localidad</label>
                        <input type="text" name="localidad" class="form-control" value="<?= htmlspecialchars($campos['localidad']) ?>">
                    </div>
                </div>

                <!-- Contacto -->
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label>Celular</label>
                        <input type="text" name="celular" class="form-control" value="<?= htmlspecialchars($campos['celular']) ?>">
                    </div>
                    <div class="form-group col-md-4">
                        <label>Fijo</label>
                        <input type="text" name="fijo" class="form-control" value="<?= htmlspecialchars($campos['fijo']) ?>">
                    </div>
                    <div class="form-group col-md-4">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($campos['email']) ?>">
                    </div>
                </div>

                <!-- Otros datos (Especialidad, Matrícula, etc) -->
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label>Fecha de nacimiento</label>
                        <input type="date" name="nacimiento" class="form-control" value="<?= htmlspecialchars($campos['nacimiento']) ?>">
                    </div>
                    <div class="form-group col-md-4">
                        <label>Sexo</label>
                        <select class="form-control" name="sexo">
                            <option value="">Seleccionar</option>
                            <option value="Masculino" <?= $campos['sexo'] == 'Masculino' ? 'selected' : '' ?>>Masculino</option>
                            <option value="Femenino" <?= $campos['sexo'] == 'Femenino' ? 'selected' : '' ?>>Femenino</option>
                        </select>
                    </div>
                    <div class="form-group col-md-4">
                        <label>Especialidad <span class="text-danger">*</span></label>
                        <select class="form-control" name="especialidad_id" required>
                            <option value="">Seleccionar</option>
                            <?php foreach ($especialidades as $esp): ?>
                                <option value="<?= $esp['Id'] ?>" <?= $campos['especialidad_id'] == $esp['Id'] ? 'selected' : '' ?>><?= htmlspecialchars($esp['especialidad']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label>Matrícula Nacional</label>
                        <input type="text" name="matricula_nacional" class="form-control" value="<?= htmlspecialchars($campos['matricula_nacional']) ?>">
                    </div>
                    <div class="form-group col-md-4">
                        <label>Matrícula Provincial</label>
                        <input type="text" name="matricula_provincial" class="form-control" value="<?= htmlspecialchars($campos['matricula_provincial']) ?>">
                    </div>
                    <div class="form-group col-md-4">
                        <label>Duración turnos <span class="text-danger">*</span></label>
                        <select class="form-control" name="duracion_turnos" required>
                            <?php foreach ([5, 10, 15, 20, 30, 45, 60] as $min): ?>
                                <option value="<?= $min ?>" <?= $campos['duracion_turnos'] == $min ? 'selected' : '' ?>><?= $min ?> min</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Usuario</label>
                        <input type="text" name="usuario" class="form-control" value="<?= htmlspecialchars($campos['usuario']) ?>">
                    </div>
                    <div class="form-group col-md-6">
                        <label>Contraseña</label>
                        <input type="password" name="contrasenia" class="form-control" placeholder="Dejar en blanco para no modificarla" value="<?= htmlspecialchars($campos['contrasenia']) ?>" autocomplete="new-password">
                    </div>
                </div>

                <!-- Horarios -->
                <div class="form-group">
                    <label>Horarios de atención <button id="addDia" class="btn btn-info btn-sm" type="button"><i class="fa fa-plus"></i></button></label>
                    <div id="dias">
                        <?php foreach ((array)$dia as $k => $v): ?>
                            <div class="dia<?= $contador ?> d-flex align-items-center mb-2 flex-wrap">
                                <select class="form-control mr-2 col-md-4 sel-dia" name="dia[]" required>
                                    <option value="">Seleccionar</option>
                                    <?php foreach ($nombresDias as $num => $nombre): ?>
                                        <option value="<?= $num ?>" <?= $v == (string)$num ? 'selected' : '' ?>><?= $nombre ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="time" class="form-control mr-2 col-md-2" name="desde[]" value="<?= $desde[$k] ?? '' ?>" required>
                                <input type="time" class="form-control mr-2 col-md-2" name="hasta[]" value="<?= $hasta[$k] ?? '' ?>" required>
                                <button type="button" class="btn btn-danger" onclick="remover(<?= $contador ?>)"><i class="fa fa-times"></i></button>
                            </div>
                        <?php $contador++;
                        endforeach; ?>
                    </div>
                </div>

                <div class="form-group border p-3">
                    <label><i class="fa fa-calendar-times"></i> Días Vacaciones / Anulados</label>
                    <p class="small text-muted">Estos días el profesional no aparecerá disponible en la agenda.</p>

                    <input type="hidden" name="confirmar_cambio_anulados" id="confirmar_cambio_anulados" value="0">

                    <div class="row mb-3">
                        <div class="col-md-4">
                            <small>Desde:</small>
                            <input type="text" id="anular_desde" class="form-control form-control-sm" placeholder="Fecha inicio">
                        </div>
                        <div class="col-md-4">
                            <small>Hasta:</small>
                            <input type="text" id="anular_hasta" class="form-control form-control-sm" placeholder="Fecha fin">
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="button" id="addAnulado" class="btn btn-info btn-sm btn-block"><i class="fa fa-plus"></i> Bloquear Rango</button>
                        </div>
                    </div>

                    <div style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; border-radius: 5px; background: #fff;">
                        <ul id="listaAnulados" class="list-group list-group-flush">
                            <?php if (!empty($dias_anulados_db)): ?>
                                <?php foreach ($dias_anulados_db as $f_anulada):
                                    $fecha_f = date("d/m/Y", strtotime($f_anulada));
                                    $dia_nombre = $nombresDias[date("w", strtotime($f_anulada))];
                                ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center py-1">
                                        <span class="small"><strong><?= $dia_nombre ?></strong> <?= $fecha_f ?></span>
                                        <input type="hidden" name="fechas_finales[]" value="<?= $f_anulada ?>">
                                        <button type="button" class="btn btn-link text-danger btn-sm p-0" onclick="borrarFecha(this)"><i class="fa fa-trash"></i></button>
                                    </li>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <li class="list-group-item text-muted small text-center" id="sin-bloqueos">No hay días bloqueados.</li>
                            <?php endif; ?>
                        </ul>
                    </div>

                    <?php if (!empty($dias_anulados_db)): ?>
                        <button type="button" class="btn btn-outline-danger btn-xs mt-2" id="btnLimpiarAnulados">Limpiar toda la lista</button>
                    <?php endif; ?>
                </div>

                <!-- Firma y Guardar -->
                <div class="form-group mt-3">
                    <label>Comentario</label>
                    <textarea name="comentario" class="form-control" rows="2"><?= htmlspecialchars($campos['comentario']) ?></textarea>
                </div>
                <div class="form-group">
                    <label>Firma</label>

                    <?php if ($rArray['firma']): ?>
                        <div class="mb-2">
                            <small class="text-muted d-block">Firma actual:</small>
                            <img src="<?= $rArray['firma'] ?>" class="img-fluid border" style="max-height:80px;">
                        </div>
                    <?php endif; ?>

                    <ul class="nav nav-tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" data-toggle="tab" href="#tabDibujarFirma" role="tab">
                                <i class="fa fa-pen"></i> Dibujar nueva firma
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="tab" href="#tabSubirFirma" role="tab">
                                <i class="fa fa-upload"></i> Subir imagen
                            </a>
                        </li>
                    </ul>

                    <div class="tab-content border border-top-0 p-3">

                        <div class="tab-pane fade show active" id="tabDibujarFirma" role="tabpanel">
                            <p class="small text-muted mb-2">
                                Pedile al profesional que firme en pantalla con el mouse o el dedo (celular/tablet).
                                Si dejás esto en blanco, se conserva la firma actual.
                            </p>
                            <canvas id="firmaCanvas" width="500" height="180"
                                style="border:1px solid #ccc; background:#fff; max-width:100%; touch-action:none; cursor:crosshair;"></canvas>
                            <div class="mt-2">
                                <button type="button" class="btn btn-secondary btn-sm" id="btnLimpiarFirma">
                                    <i class="fa fa-eraser"></i> Limpiar
                                </button>
                            </div>
                            <input type="hidden" name="firma_dibujada" id="firma_dibujada">
                        </div>

                        <div class="tab-pane fade" id="tabSubirFirma" role="tabpanel">
                            <input type="file" class="form-control" name="firma" accept="image/*">
                        </div>

                    </div>
                </div>
                <div class="form-group text-right">
                    <a href="./?seccion=profesionales" class="btn btn-secondary">Volver</a>
                    <button type="submit" name="guardar" class="btn btn-primary px-4">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        var contador = <?= $contador ?>;

        $('#addDia').click(function() {
            var html = `<div class="dia${contador} d-flex align-items-center mb-2">
            <select class="form-control mr-2 col-md-4 sel-dia" name="dia[]" required>
                <option value="">Seleccionar</option>
                <option value="1">Lunes</option><option value="2">Martes</option>
                <option value="3">Miércoles</option><option value="4">Jueves</option>
                <option value="5">Viernes</option><option value="6">Sábado</option>
                <option value="0">Domingo</option>
            </select>
            <input type="time" class="form-control mr-2 col-md-2" name="desde[]" required>
            <input type="time" class="form-control mr-2 col-md-2" name="hasta[]" required>
            <button type="button" class="btn btn-danger" onclick="remover(${contador})"><i class="fa fa-times"></i></button>
        </div>`;
            $('#dias').append(html);
            contador++;
        });

        window.remover = function(id) {
            $('.dia' + id).remove();
        }

        // =========================
        // FIRMA DIBUJADA (mouse / touch)
        // =========================
        (function () {
            var canvas = document.getElementById('firmaCanvas');
            var ctx = canvas.getContext('2d');
            var dibujando = false;
            var hayTrazo = false;

            ctx.lineWidth = 2;
            ctx.lineCap = 'round';
            ctx.strokeStyle = '#000';

            function posicion(e) {
                var rect = canvas.getBoundingClientRect();
                var escalaX = canvas.width / rect.width;
                var escalaY = canvas.height / rect.height;
                var clientX = e.touches ? e.touches[0].clientX : e.clientX;
                var clientY = e.touches ? e.touches[0].clientY : e.clientY;
                return {
                    x: (clientX - rect.left) * escalaX,
                    y: (clientY - rect.top) * escalaY
                };
            }

            function empezar(e) {
                e.preventDefault();
                dibujando = true;
                var p = posicion(e);
                ctx.beginPath();
                ctx.moveTo(p.x, p.y);
            }

            function mover(e) {
                if (!dibujando) return;
                e.preventDefault();
                var p = posicion(e);
                ctx.lineTo(p.x, p.y);
                ctx.stroke();
                hayTrazo = true;
            }

            function terminar() {
                dibujando = false;
            }

            canvas.addEventListener('mousedown', empezar);
            canvas.addEventListener('mousemove', mover);
            canvas.addEventListener('mouseup', terminar);
            canvas.addEventListener('mouseleave', terminar);

            canvas.addEventListener('touchstart', empezar);
            canvas.addEventListener('touchmove', mover);
            canvas.addEventListener('touchend', terminar);

            $('#btnLimpiarFirma').click(function () {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                hayTrazo = false;
            });

            $('#formProfesional').on('submit', function () {
                $('#firma_dibujada').val(hayTrazo ? canvas.toDataURL('image/png') : '');
            });
        })();

        flatpickr("#anular_desde", {
            dateFormat: "Y-m-d"
        });
        flatpickr("#anular_hasta", {
            dateFormat: "Y-m-d"
        });

        window.borrarFecha = function(btn) {
            $(btn).closest('li').remove();
            $('#confirmar_cambio_anulados').val('1'); // Avisamos que hubo cambios
            if ($('#listaAnulados li').length === 0) {
                $('#listaAnulados').append('<li class="list-group-item text-muted small text-center" id="sin-bloqueos">No hay días bloqueados.</li>');
            }
        };

        // Agregar rango nuevo
        $('#addAnulado').click(function() {
            var desde = $('#anular_desde').val();
            var hasta = $('#anular_hasta').val();

            if (!desde || !hasta) {
                return Swal.fire('Atención', 'Debes seleccionar fecha de inicio y fin', 'warning');
            }

            $('#confirmar_cambio_anulados').val('1');
            $('#sin-bloqueos').remove();

            var item = `<li class="list-group-item d-flex justify-content-between align-items-center py-1 list-group-item-warning">
            <span class="small"><strong>Nuevo Bloqueo:</strong> ${desde} al ${hasta}</span>
            <input type="hidden" name="rangos_anulados[]" value="${desde}|${hasta}">
            <button type="button" class="btn btn-link text-danger btn-sm p-0" onclick="this.parentElement.remove()"><i class="fa fa-times"></i></button>
        </li>`;

            $('#listaAnulados').prepend(item);
            $('#anular_desde, #anular_hasta').val('');
        });

        $('#btnLimpiarAnulados').click(function() {
            Swal.fire({
                title: '¿Limpiar lista?',
                text: "Se eliminarán todos los bloqueos actuales al guardar los cambios.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sí, limpiar todo',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    // 1. Activamos la bandera de cambio para el backend PHP
                    $('#confirmar_cambio_anulados').val('1');

                    // 2. Vaciamos la lista visualmente
                    $('#listaAnulados').empty().append(
                        '<li class="list-group-item text-muted small text-center" id="sin-bloqueos">' +
                        'La lista se vaciará definitivamente al hacer clic en "Guardar Cambios".' +
                        '</li>'
                    );

                    // 3. Ocultamos el botón rojo (como se ve en la imagen image_f3cf32.png)
                    $(this).fadeOut();
                }
            });
        });
        <?php if ($swalGuardado): ?>
            Swal.fire({
                icon: 'success',
                title: '¡Guardado!',
                text: 'Los datos del profesional se actualizaron correctamente.',
                confirmButtonText: 'Aceptar'
            }).then(() => {
                window.location.href = './?seccion=profesionales';
            });
        <?php endif; ?>

        <?php if ($swalError): ?>
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: '<?= addslashes($swalError) ?>',
                confirmButtonText: 'Cerrar'
            });
        <?php endif; ?>
    });
</script>