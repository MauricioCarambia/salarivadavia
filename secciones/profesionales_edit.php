<?php
require_once __DIR__.'/../inc/db.php';

$id = $_GET['id'] ?? 0;
$swalGuardado = false;
$swalError = false;

// Obtener profesional existente
$stmt = $pdo->prepare("SELECT * FROM profesionales WHERE Id=?");
$stmt->execute([$id]);
$rArray = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$rArray) {
    echo "<div class='alert alert-danger'>Profesional no encontrado.</div>";
    return;
}

// Valores por defecto del formulario (cargar desde POST si hay submit, sino de la base)
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
    'contrasenia' => $_POST['contrasenia'] ?? $rArray['contrasenia'],
    'sexo' => $_POST['sexo'] ?? $rArray['sexo'],
    'vacaciones_desde' => $_POST['vacaciones_desde'] ?? $rArray['vacaciones_desde'],
    'vacaciones_hasta' => $_POST['vacaciones_hasta'] ?? $rArray['vacaciones_hasta'],
    'comentario' => $_POST['comentario'] ?? $rArray['comentario'],
];

// Horarios existentes
$stmt = $pdo->prepare("SELECT * FROM profesionales_horarios WHERE profesional_id=?");
$stmt->execute([$id]);
$horarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

$dia   = isset($_POST['dia']) ? (array)$_POST['dia'] : array_column($horarios,'dia');
$desde = isset($_POST['desde']) ? (array)$_POST['desde'] : array_column($horarios,'hora_inicio');
$hasta = isset($_POST['hasta']) ? (array)$_POST['hasta'] : array_column($horarios,'hora_fin');

// Días anulados existentes
$stmt = $pdo->prepare("SELECT fecha FROM dias_anulados WHERE profesional_id=? AND fecha>=CURDATE()");
$stmt->execute([$id]);
$dias_anulados = isset($_POST['dias_anulados']) ? array_map('trim', explode(',', $_POST['dias_anulados'])) : $stmt->fetchAll(PDO::FETCH_COLUMN);

$firma = $_FILES['firma'] ?? null;

// Especialidades
$especialidades = $pdo->query("SELECT * FROM especialidades")->fetchAll(PDO::FETCH_ASSOC);

$nombresDias = ['0'=>'Domingo','1'=>'Lunes','2'=>'Martes','3'=>'Miércoles','4'=>'Jueves','5'=>'Viernes','6'=>'Sábado'];
$contador = 1000;

// Guardar cambios
if (isset($_POST['guardar'])) {

    try {

        $pdo->beginTransaction();

        // Validar documento único
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM profesionales WHERE documento=? AND Id<>?");
        $stmt->execute([$campos['documento'],$id]);
        if ($stmt->fetchColumn() > 0) throw new Exception("El documento {$campos['documento']} ya se encuentra registrado.");

        // Subida de firma
        $rutaImagen = $rArray['firma'];
        if (!empty($firma['name'])) {
            $directorio = 'imagenes/';
            if (!is_dir($directorio)) mkdir($directorio,0777,true);
            $ext = pathinfo($firma['name'], PATHINFO_EXTENSION);
            $nombreArchivo = uniqid().'_'.basename($firma['name']);
            $rutaImagen = $directorio.$nombreArchivo;
            if (!move_uploaded_file($firma['tmp_name'],$rutaImagen)) throw new Exception("Error al subir la firma.");
        }

        // Actualizar profesional
        $sql = "UPDATE profesionales SET
            nombre=?, apellido=?, provincia=?, localidad=?, celular=?, fijo=?, email=?,
            tipo_documento=?, documento=?, nacimiento=?, especialidad_id=?, matricula_nacional=?,
            matricula_provincial=?, porcentaje=?, duracion_turnos=?, usuario=?, contrasenia=?,
            sexo=?, vacaciones_desde=?, vacaciones_hasta=?, comentario=?, firma=?
            WHERE Id=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $campos['nombre'],$campos['apellido'],$campos['provincia'],$campos['localidad'],$campos['celular'],$campos['fijo'],$campos['email'],
            $campos['tipo_documento'],$campos['documento'],$campos['nacimiento'],$campos['especialidad_id'],$campos['matricula_nacional'],
            $campos['matricula_provincial'],$campos['porcentaje'],$campos['duracion_turnos'],$campos['usuario'],$campos['contrasenia'],
            $campos['sexo'],$campos['vacaciones_desde'],$campos['vacaciones_hasta'],$campos['comentario'],$rutaImagen,$id
        ]);

        // Horarios
        $pdo->prepare("DELETE FROM profesionales_horarios WHERE profesional_id=?")->execute([$id]);
        $stmtHorario = $pdo->prepare("INSERT INTO profesionales_horarios (profesional_id,dia,hora_inicio,hora_fin) VALUES (?,?,?,?)");
        foreach ($dia as $k=>$d) {
            if (!empty($d) && isset($desde[$k],$hasta[$k])) {
                $stmtHorario->execute([$id,$d,$desde[$k],$hasta[$k]]);
            }
        }

        // Días anulados
        $pdo->prepare("DELETE FROM dias_anulados WHERE profesional_id=? AND fecha>=CURDATE()")->execute([$id]);
        $stmtAnulado = $pdo->prepare("INSERT INTO dias_anulados (profesional_id,fecha) VALUES (?,?)");
        foreach ($dias_anulados as $fecha) {
            $stmtAnulado->execute([$id,$fecha]);
        }

        $pdo->commit();
        $swalGuardado = true;

    } catch(Exception $e) {
        $pdo->rollBack();
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
            <form method="POST" enctype="multipart/form-data">

                <!-- Nombre y Apellido -->
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
                            <?php foreach(['DNI','LE','LC','CI'] as $tipo): ?>
                                <option value="<?= $tipo ?>" <?= $campos['tipo_documento']==$tipo?'selected':'' ?>><?= $tipo ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group col-md-8">
                        <label>Documento <span class="text-danger">*</span></label>
                        <input type="number" name="documento" class="form-control" value="<?= htmlspecialchars($campos['documento']) ?>" required>
                    </div>
                </div>

                <!-- Provincia, localidad -->
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

                <!-- Celular, Fijo, Email -->
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

                <!-- Fecha de nacimiento -->
                <div class="form-group col-md-4">
                    <label>Fecha de nacimiento</label>
                    <input type="date" name="nacimiento" class="form-control" value="<?= htmlspecialchars($campos['nacimiento']) ?>">
                </div>

                <!-- Especialidad y duración turnos -->
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Especialidad <span class="text-danger">*</span></label>
                        <select class="form-control" name="especialidad_id" required>
                            <option value="">Seleccionar</option>
                            <?php foreach($especialidades as $esp): ?>
                                <option value="<?= $esp['Id'] ?>" <?= $campos['especialidad_id']==$esp['Id']?'selected':'' ?>><?= htmlspecialchars($esp['especialidad']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group col-md-6">
                        <label>Duración de los turnos <span class="text-danger">*</span></label>
                        <select class="form-control" name="duracion_turnos" required>
                            <option value="">Seleccionar</option>
                            <?php foreach([5,10,15,20,30,40,45,50,60] as $min): ?>
                                <option value="<?= $min ?>" <?= $campos['duracion_turnos']==$min?'selected':'' ?>><?= $min ?> minutos</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Matrículas y porcentaje -->
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label>Matrícula nacional</label>
                        <input type="text" name="matricula_nacional" class="form-control" value="<?= htmlspecialchars($campos['matricula_nacional']) ?>">
                    </div>
                    <div class="form-group col-md-4">
                        <label>Matrícula provincial</label>
                        <input type="text" name="matricula_provincial" class="form-control" value="<?= htmlspecialchars($campos['matricula_provincial']) ?>">
                    </div>
                    <div class="form-group col-md-4">
                        <label>Porcentaje %</label>
                        <input type="number" min="0" step="0.01" name="porcentaje" class="form-control" value="<?= htmlspecialchars($campos['porcentaje']) ?>">
                    </div>
                </div>

                <!-- Usuario / Contraseña -->
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Usuario</label>
                        <input type="text" name="usuario" class="form-control" value="<?= htmlspecialchars($campos['usuario']) ?>">
                    </div>
                    <div class="form-group col-md-6">
                        <label>Contraseña</label>
                        <input type="text" name="contrasenia" class="form-control" value="<?= htmlspecialchars($campos['contrasenia']) ?>">
                    </div>
                </div>

                <!-- Sexo -->
                <div class="form-group col-md-6">
                    <label>Sexo</label>
                    <select class="form-control" name="sexo">
                        <option value="">Seleccionar</option>
                        <option value="Masculino" <?= $campos['sexo']=='Masculino'?'selected':'' ?>>Masculino</option>
                        <option value="Femenino" <?= $campos['sexo']=='Femenino'?'selected':'' ?>>Femenino</option>
                    </select>
                </div>

                <!-- Horarios -->
                <div class="form-group">
                    <label>Horarios de atención
                        <button id="addDia" class="btn btn-info btn-sm" type="button"><i class="fa fa-plus"></i></button>
                    </label>
                    <div id="dias">
                        <?php foreach((array)$dia as $k=>$v): ?>
                        <div class="dia<?= $contador ?> d-flex align-items-center mb-2 flex-wrap">
                            <select class="form-control mr-2 col-md-4" name="dia[]" required>
                                <option value="">Seleccionar</option>
                                <?php foreach($nombresDias as $num=>$nombre): ?>
                                    <option value="<?= $num ?>" <?= $v==$num?'selected':'' ?>><?= $nombre ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="time" class="form-control mr-2 col-md-2" name="desde[]" value="<?= $desde[$k] ?? '' ?>" required>
                            <input type="time" class="form-control mr-2 col-md-2" name="hasta[]" value="<?= $hasta[$k] ?? '' ?>" required>
                            <button type="button" class="btn btn-danger" onclick="remover(<?= $contador ?>)"><i class="fa fa-times"></i></button>
                        </div>
                        <?php $contador++; endforeach; ?>
                    </div>
                </div>

                <!-- Días anulados -->
                <div class="form-group">
                    <label>Días anulados</label>
                    <input type="text" name="dias_anulados" id="dias_anulados" class="form-control" placeholder="YYYY-MM-DD, separados por coma" value="<?= implode(',',$dias_anulados) ?>">
                </div>

                <!-- Vacaciones -->
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Vacaciones desde</label>
                        <input type="date" name="vacaciones_desde" class="form-control" value="<?= htmlspecialchars($campos['vacaciones_desde']) ?>">
                    </div>
                    <div class="form-group col-md-6">
                        <label>Vacaciones hasta</label>
                        <input type="date" name="vacaciones_hasta" class="form-control" value="<?= htmlspecialchars($campos['vacaciones_hasta']) ?>">
                    </div>
                </div>

                <!-- Comentario -->
                <div class="form-group">
                    <label>Comentario</label>
                    <input type="text" name="comentario" class="form-control" value="<?= htmlspecialchars($campos['comentario']) ?>">
                </div>

                <!-- Firma -->
                <div class="form-group col-md-6">
                    <label>Firma</label>
                    <input type="file" class="form-control" accept="image/*" name="firma">
                    <?php if($rArray['firma']): ?>
                        <img src="<?= $rArray['firma'] ?>" class="img-fluid mt-2" style="max-height:100px;">
                    <?php endif; ?>
                </div>

                <div class="form-group text-right">
                    <a href="./?seccion=profesionales" class="btn btn-secondary">Volver</a>
                    <button type="submit" name="guardar" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function(){

    // Contador para los horarios
    var contador = <?= $contador ?>;

    $('#addDia').click(function(){
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

    window.remover = function(id){ 
        $('.dia'+id).remove(); 
    }

    // MOSTRAR SWEETALERT SOLO SI HAY GUARDADO O ERROR
    <?php if($swalGuardado): ?>
        Swal.fire({
            icon: 'success',
            title: 'Profesional Actualizado',
            text: 'Profesional actualizado correctamente.',
            confirmButtonText: 'OK'
             }).then(() => {
            window.location.href = './?seccion=profesionales&nc=<?= $rand ?>';
        });
        
    <?php endif; ?>

    <?php if($swalError): ?>
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: '<?= addslashes($swalError) ?>',
            confirmButtonText: 'OK'
        });
    <?php endif; ?>

});
</script>