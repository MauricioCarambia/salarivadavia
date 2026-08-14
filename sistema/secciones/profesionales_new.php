<?php
require_once __DIR__ . '/../inc/db.php';

$rand = $rand ?? rand();
$swalGuardado = false;
$swalError = false;

// Valores por defecto del formulario
$campos = [
    'documento' => '',
    'nombre' => '',
    'apellido' => '',
    'provincia' => '',
    'localidad' => '',
    'celular' => '',
    'fijo' => '',
    'email' => '',
    'tipo_documento' => '',
    'nacimiento' => '',
    'especialidad_id' => '',
    'matricula_nacional' => '',
    'matricula_provincial' => '',
    'porcentaje' => '',
    'duracion_turnos' => '',
    'usuario' => '',
    'contrasenia' => '',
    'sexo' => '',
    'vacaciones_desde' => '',
    'vacaciones_hasta' => '',
    'comentario' => ''
];

$dia = $desde = $hasta = [];
$firma = null;

// Rellenar desde POST
foreach ($campos as $key => $val) {
    $campos[$key] = $_POST[$key] ?? '';
}
$dia = $_POST['dia'] ?? [];
$desde = $_POST['desde'] ?? [];
$hasta = $_POST['hasta'] ?? [];
$firma = $_FILES['firma'] ?? null;
$firmaDibujada = $_POST['firma_dibujada'] ?? '';

// Guardar profesional
if (isset($_POST['guardar'])) {

    // Validar documento existente
    $stmt = $pdo->prepare("SELECT Id FROM profesionales WHERE documento = :documento");
    $stmt->execute([':documento' => $campos['documento']]);

    if ($stmt->rowCount() === 0) {

        // Firma: prioriza la dibujada en el momento, si no hay usa el archivo subido
        $rutaImagen = '';
        $directorioSubida = 'imagenes/';
        if (!is_dir($directorioSubida)) {
            mkdir($directorioSubida, 0777, true);
        }

        if (!empty($firmaDibujada) && preg_match('/^data:image\/png;base64,/', $firmaDibujada)) {

            $datos = explode(',', $firmaDibujada, 2)[1] ?? '';
            $binario = base64_decode($datos);

            if ($binario === false) {
                $swalError = 'No se pudo procesar la firma dibujada.';
            } else {
                $nombreArchivo = uniqid() . '_firma.png';
                $rutaImagen = $directorioSubida . $nombreArchivo;
                file_put_contents($rutaImagen, $binario);
            }

        } elseif (!empty($firma['name'])) {

            $ext = pathinfo($firma['name'], PATHINFO_EXTENSION);
            $nombreArchivo = uniqid() . '.' . $ext;
            $rutaImagen = $directorioSubida . $nombreArchivo;

            if (!move_uploaded_file($firma['tmp_name'], $rutaImagen)) {
                $swalError = 'Error al subir la imagen de firma.';
            }
        }

        if (!$swalError) {
            // Insert profesional
            $sql = "INSERT INTO profesionales
                (nombre, apellido, provincia, localidad, celular, fijo, email,
                tipo_documento, documento, nacimiento, especialidad_id, matricula_nacional,
                matricula_provincial, porcentaje, duracion_turnos, usuario, contrasenia,
                sexo, vacaciones_desde, vacaciones_hasta, comentario, firma)
                VALUES
                (:nombre, :apellido, :provincia, :localidad, :celular, :fijo, :email,
                :tipo_documento, :documento, :nacimiento, :especialidad_id, :matricula_nacional,
                :matricula_provincial, :porcentaje, :duracion_turnos, :usuario, :contrasenia,
                :sexo, :vacaciones_desde, :vacaciones_hasta, :comentario, :firma)";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':nombre' => $campos['nombre'],
                ':apellido' => $campos['apellido'],
                ':provincia' => $campos['provincia'],
                ':localidad' => $campos['localidad'],
                ':celular' => $campos['celular'],
                ':fijo' => $campos['fijo'],
                ':email' => $campos['email'],
                ':tipo_documento' => $campos['tipo_documento'],
                ':documento' => $campos['documento'],
                ':nacimiento' => $campos['nacimiento'],
                ':especialidad_id' => $campos['especialidad_id'],
                ':matricula_nacional' => $campos['matricula_nacional'],
                ':matricula_provincial' => $campos['matricula_provincial'],
                ':porcentaje' => $campos['porcentaje'],
                ':duracion_turnos' => $campos['duracion_turnos'],
                ':usuario' => $campos['usuario'],
                ':contrasenia' => password_hash($campos['contrasenia'], PASSWORD_DEFAULT),
                ':sexo' => $campos['sexo'],
                ':vacaciones_desde' => $campos['vacaciones_desde'] ?: null,
                ':vacaciones_hasta' => $campos['vacaciones_hasta'] ?: null,
                ':comentario' => $campos['comentario'],
                ':firma' => $rutaImagen
            ]);

            $last_id = $pdo->lastInsertId();

            // Insert horarios
            $stmtHorario = $pdo->prepare("INSERT INTO profesionales_horarios
                (profesional_id, dia, hora_inicio, hora_fin) VALUES (?, ?, ?, ?)");
            foreach ($dia as $k => $v) {
                if (!empty($v) && isset($desde[$k], $hasta[$k])) {
                    $stmtHorario->execute([$last_id, $v, $desde[$k], $hasta[$k]]);
                }
            }

            $swalGuardado = true;
            $campos = array_map(fn($v) => '', $campos);
            $dia = $desde = $hasta = [];
        }

    } else {
        $swalError = "El documento {$campos['documento']} ya se encuentra registrado.";
    }
}

// Especialidades
$especialidades = $pdo->query("SELECT * FROM especialidades")->fetchAll(PDO::FETCH_ASSOC);

$nombresDias = ['0' => 'Domingo', '1' => 'Lunes', '2' => 'Martes', '3' => 'Miércoles', '4' => 'Jueves', '5' => 'Viernes', '6' => 'Sábado'];
$contador = 1000; // para IDs de filas de horarios
$provincias = [
    "Ciudad Autonoma de Buenos Aires",
    "Buenos Aires",
    "Catamarca",
    "Chaco",
    "Chubut",
    "Córdoba",
    "Corrientes",
    "Entre Ríos",
    "Formosa",
    "Jujuy",
    "La Pampa",
    "La Rioja",
    "Mendoza",
    "Misiones",
    "Neuquén",
    "Río Negro",
    "Salta",
    "San Juan",
    "San Luis",
    "Santa Cruz",
    "Santa Fe",
    "Santiago del Estero",
    "Tierra del Fuego",
    "Tucumán"
];
?>

<div class="content">
    <div class="card card-info card-outline">
        <div class="card-header">
            <h3 class="card-title">Alta de profesional</h3>
        </div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data">
                <!-- Nombre y Apellido -->
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Nombre <span class="text-danger">*</span></label>
                        <input type="text" name="nombre" class="form-control"
                            value="<?= htmlspecialchars($campos['nombre']) ?>" required>
                    </div>
                    <div class="form-group col-md-6">
                        <label>Apellido <span class="text-danger">*</span></label>
                        <input type="text" name="apellido" class="form-control"
                            value="<?= htmlspecialchars($campos['apellido']) ?>" required>
                    </div>
                </div>

                <!-- Documento -->
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label>Tipo de documento</label>
                        <select class="form-control" name="tipo_documento">
                            <option value="">Seleccionar</option>
                            <?php foreach (['DNI', 'LE', 'LC', 'CI'] as $tipo): ?>
                                <option value="<?= $tipo ?>" <?= $campos['tipo_documento'] == $tipo ? 'selected' : '' ?>>
                                    <?= $tipo ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group col-md-8">
                        <label>Documento <span class="text-danger">*</span></label>
                        <input type="number" name="documento" class="form-control"
                            value="<?= htmlspecialchars($campos['documento']) ?>" required>
                    </div>
                </div>

                <!-- Provincia, localidad -->
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Provincia</label>
                        <select name="provincia" class="form-control">
                            <option value="">Seleccionar provincia</option>
                            <?php foreach ($provincias as $prov): ?>
                                <option value="<?= $prov ?>" <?= $campos['provincia'] == $prov ? 'selected' : '' ?>>
                                    <?= $prov ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group col-md-6">
                        <label>Localidad</label>
                        <input type="text" name="localidad" class="form-control"
                            value="<?= htmlspecialchars($campos['localidad']) ?>">
                    </div>
                </div>

                <!-- Celular, Fijo, Email -->
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label>Celular</label>
                        <input type="text" name="celular" class="form-control"
                            value="<?= htmlspecialchars($campos['celular']) ?>">
                    </div>
                    <div class="form-group col-md-4">
                        <label>Fijo</label>
                        <input type="text" name="fijo" class="form-control"
                            value="<?= htmlspecialchars($campos['fijo']) ?>">
                    </div>
                    <div class="form-group col-md-4">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control"
                            value="<?= htmlspecialchars($campos['email']) ?>">
                    </div>
                </div>

                <!-- Fecha de nacimiento -->
                <div class="form-group col-md-4">
                    <label>Fecha de nacimiento</label>
                    <input type="date" name="nacimiento" class="form-control"
                        value="<?= htmlspecialchars($campos['nacimiento']) ?>">
                </div>

                <!-- Especialidad -->
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Especialidad <span class="text-danger">*</span></label>
                        <select class="form-control" name="especialidad_id" required>
                            <option value="">Seleccionar</option>
                            <?php foreach ($especialidades as $esp): ?>
                                <option value="<?= $esp['Id'] ?>" <?= $campos['especialidad_id'] == $esp['Id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($esp['especialidad']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group col-md-6">
                        <label>Duración de los turnos <span class="text-danger">*</span></label>
                        <select class="form-control" name="duracion_turnos" required>
                            <option value="">Seleccionar</option>
                            <?php foreach ([5, 10, 15, 20, 30, 40, 45, 50, 60] as $min): ?>
                                <option value="<?= $min ?>" <?= $campos['duracion_turnos'] == $min ? 'selected' : '' ?>>
                                    <?= $min ?>
                                    minutos
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Matrículas y porcentaje -->
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label>Matrícula nacional</label>
                        <input type="text" name="matricula_nacional" class="form-control"
                            value="<?= htmlspecialchars($campos['matricula_nacional']) ?>">
                    </div>
                    <div class="form-group col-md-4">
                        <label>Matrícula provincial</label>
                        <input type="text" name="matricula_provincial" class="form-control"
                            value="<?= htmlspecialchars($campos['matricula_provincial']) ?>">
                    </div>
                    <div class="form-group col-md-4">
                        <label>Porcentaje %</label>
                        <input type="number" min="0" step="0.01" name="porcentaje" class="form-control"
                            value="<?= htmlspecialchars($campos['porcentaje']) ?>">
                    </div>
                </div>

                <!-- Usuario y contraseña -->
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Usuario</label>
                        <input type="text" name="usuario" class="form-control"
                            value="<?= htmlspecialchars($campos['usuario']) ?>">
                    </div>
                    <div class="form-group col-md-6">
                        <label>Contraseña</label>
                        <input type="password" name="contrasenia" class="form-control" autocomplete="new-password"
                            value="<?= htmlspecialchars($campos['contrasenia']) ?>">
                    </div>
                </div>

                <!-- Sexo -->
                <div class="form-group">
                    <label>Sexo</label>
                    <select class="form-control col-md-6" name="sexo">
                        <option value="">Seleccionar</option>
                        <option value="Masculino" <?= $campos['sexo'] == 'Masculino' ? 'selected' : '' ?>>Masculino
                        </option>
                        <option value="Femenino" <?= $campos['sexo'] == 'Femenino' ? 'selected' : '' ?>>Femenino</option>
                    </select>
                </div>

                <!-- Horarios -->
                <div class="form-group">
                    <label>Horarios de atención
                        <button id="addDia" class="btn btn-info btn-sm" type="button"><i
                                class="fa fa-plus"></i></button>
                    </label>
                    <div id="dias">
                        <?php foreach ((array) $dia as $k => $v): ?>
                            <div class="dia<?= $contador ?> d-flex align-items-center mb-2 flex-wrap">
                                <select class="form-control mr-2 " name="dia[]" required>
                                    <option value="">Seleccionar</option>
                                    <?php foreach ($nombresDias as $num => $nombre): ?>
                                        <option value="<?= $num ?>" <?= $v == $num ? 'selected' : '' ?>><?= $nombre ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="time" class="form-control mr-2" name="desde[]" value="<?= $desde[$k] ?? '' ?>"
                                    required>
                                <input type="time" class="form-control mr-2" name="hasta[]" value="<?= $hasta[$k] ?? '' ?>"
                                    required>
                                <button type="button" class="btn btn-danger" onclick="remover(<?= $contador ?>)"><i
                                        class="fa fa-times"></i></button>
                            </div>
                            <?php $contador++; endforeach; ?>
                    </div>
                </div>

               

                <!-- Comentario -->
                <div class="form-group">
                    <label>Comentario</label>
                    <input type="text" name="comentario" class="form-control"
                        value="<?= htmlspecialchars($campos['comentario']) ?>">
                </div>

                <!-- Firma -->
                <div class="form-group">
                    <label>Firma</label>

                    <ul class="nav nav-tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" data-toggle="tab" href="#tabDibujarFirma" role="tab">
                                <i class="fa fa-pen"></i> Dibujar firma
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
                            <input type="file" class="form-control" accept="image/*" name="firma">
                        </div>

                    </div>
                </div>

                <div class="form-group text-right">
                    <a href="./?seccion=profesionales&nc=<?= $rand ?>" class="btn btn-secondary">Volver</a>
                    <button type="submit" name="guardar" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    $(document).ready(function () {
        var contador = <?= $contador ?>;

        // Agregar nuevo horario
        $('#addDia').click(function () {
            var html = `
        <div class="dia${contador} d-flex align-items-center mb-2 ">
            <select class="form-control mr-2 col-md-4" name="dia[]" required>
                <option value="">Seleccionar</option>
                <option value="1">Lunes</option>
                <option value="2">Martes</option>
                <option value="3">Miércoles</option>
                <option value="4">Jueves</option>
                <option value="5">Viernes</option>
                <option value="6">Sábado</option>
                <option value="0">Domingo</option>
            </select>
            <input type="time" class="form-control mr-2 col-md-2" name="desde[]" required>
            <input type="time" class="form-control mr-2 col-md-2" name="hasta[]" required>
            <button type="button" class="btn btn-danger" onclick="remover(${contador})"><i class="fa fa-times"></i></button>
        </div>`;
            $('#dias').append(html);
            contador++;
        });

        // Remover fila
        window.remover = function (id) {
            $('.dia' + id).remove();
        };

        // =========================
        // FIRMA DIBUJADA (mouse / touch)
        // =========================
        (function () {
            var canvas = document.getElementById('firmaCanvas');
            var ctx = canvas.getContext('2d');
            var dibujando = false;
            var hayTrazo = false;

            function fondoBlanco() {
                // El canvas nace transparente (no blanco) aunque se vea blanco por el CSS.
                // Si se exporta así, el PDF (que convierte a JPEG) puede perder el trazo.
                ctx.fillStyle = '#fff';
                ctx.fillRect(0, 0, canvas.width, canvas.height);
            }
            fondoBlanco();

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

            function terminar(e) {
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
                fondoBlanco();
                hayTrazo = false;
            });

            $('form').on('submit', function () {
                $('#firma_dibujada').val(hayTrazo ? canvas.toDataURL('image/png') : '');
            });
        })();

        // Validación horarios
        $('form').submit(function (e) {
            var valid = true;
            $('#dias > div').each(function () {
                var desde = $(this).find('input[name="desde[]"]').val();
                var hasta = $(this).find('input[name="hasta[]"]').val();
                if (desde && hasta && desde >= hasta) {
                    valid = false;
                    Swal.fire({
                        icon: 'error',
                        title: 'Error en horarios',
                        text: 'La hora fin debe ser mayor que la hora inicio.'
                    });
                    return false; // rompe el each
                }
            });
            if (!valid) e.preventDefault();
        });

        // Mensajes de guardado o error
        <?php if ($swalGuardado): ?>
            Swal.fire({
                icon: 'success',
                title: '¡Profesional guardado!',
                confirmButtonText: 'Aceptar',
                customClass: { confirmButton: 'btn btn-primary' },
                buttonsStyling: false
            }).then(() => window.location.href = './?seccion=profesionales&nc=<?= $rand ?>');
        <?php elseif ($swalError): ?>
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: '<?= addslashes($swalError) ?>',
                confirmButtonText: 'Aceptar',
                customClass: { confirmButton: 'btn btn-danger' },
                buttonsStyling: false
            });
        <?php endif; ?>
    });
</script>