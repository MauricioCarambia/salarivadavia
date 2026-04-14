<?php
require_once __DIR__ . '/../inc/db.php';

$rand = $rand ?? rand();
$swalGuardado = false;
$swalError = false;

// Valores por defecto
$campos = [
    'documento' => '', 'nombre' => '', 'apellido' => '', 'domicilio' => '',
    'provincia' => '', 'localidad' => '', 'celular' => '', 'fijo' => '',
    'email' => '', 'tipo_documento' => '', 'nacimiento' => '', 'nro_afiliado' => '',
    'obra_social_id' => '', 'obra_social_plan' => '', 'obra_social_numero' => '',
    'sexo' => '', 'historia_clinica' => '', 'nota' => ''
];

// Rellenar desde POST
foreach ($campos as $key => $val) {
    $campos[$key] = $_POST[$key] ?? '';
}

// Guardar paciente
if (isset($_POST['guardar'])) {
    $stmt = $pdo->prepare("SELECT Id FROM pacientes WHERE documento = :documento");
    $stmt->execute(['documento' => $campos['documento']]);

    if ($stmt->rowCount() == 0) {
        $sql = "INSERT INTO pacientes (
            nombre, apellido, domicilio, provincia, localidad,
            celular, fijo, email, tipo_documento, documento,
            nacimiento, nro_afiliado,
            obra_social_id, obra_social_plan, obra_social_numero,
            sexo, historia_clinica, nota
        ) VALUES (
            :nombre, :apellido, :domicilio, :provincia, :localidad,
            :celular, :fijo, :email, :tipo_documento, :documento,
            :nacimiento, :nro_afiliado,
            :obra_social_id, :obra_social_plan, :obra_social_numero,
            :sexo, :historia_clinica, :nota
        )";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':nombre' => $campos['nombre'],
            ':apellido' => $campos['apellido'],
            ':domicilio' => $campos['domicilio'],
            ':provincia' => $campos['provincia'],
            ':localidad' => $campos['localidad'],
            ':celular' => $campos['celular'],
            ':fijo' => $campos['fijo'],
            ':email' => $campos['email'],
            ':tipo_documento' => $campos['tipo_documento'],
            ':documento' => $campos['documento'],
            ':nacimiento' => $campos['nacimiento'],
            ':nro_afiliado' => $campos['nro_afiliado'],
            ':obra_social_id' => $campos['obra_social_id'],
            ':obra_social_plan' => $campos['obra_social_plan'],
            ':obra_social_numero' => $campos['obra_social_numero'],
            ':sexo' => $campos['sexo'],
            ':historia_clinica' => $campos['historia_clinica'],
            ':nota' => $campos['nota']
        ]);

        $swalGuardado = true;
        $campos = array_map(fn($v) => '', $campos);

    } else {
        $swalError = "El documento {$campos['documento']} ya se encuentra registrado.";
    }
}

// Provincias
$provincias = [
    "Ciudad Autónoma de Buenos Aires", "Buenos Aires", "Catamarca", "Chaco", "Chubut", "Córdoba", 
    "Corrientes", "Entre Ríos", "Formosa", "Jujuy", "La Pampa", "La Rioja", "Mendoza",
    "Misiones", "Neuquén", "Río Negro", "Salta", "San Juan", "San Luis", "Santa Cruz",
    "Santa Fe", "Santiago del Estero", "Tierra del Fuego", "Tucumán"
];
?>

<div class="content">
    <div class="card card-info card-outline">
        <div class="card-header">
            <h3 class="card-title">Alta de paciente</h3>
        </div>
        <div class="card-body">
            <form method="POST" class="form-horizontal">
                <!-- Nombre y Apellido -->
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Nombre <span class="text-danger">*</span></label>
                        <input type="text" name="nombre" class="form-control" placeholder="Nombre" value="<?= htmlspecialchars($campos['nombre']) ?>" required>
                    </div>
                    <div class="form-group col-md-6">
                        <label>Apellido <span class="text-danger">*</span></label>
                        <input type="text" name="apellido" class="form-control" placeholder="Apellido" value="<?= htmlspecialchars($campos['apellido']) ?>" required>
                    </div>
                </div>

                <!-- Documento y Nro de socio -->
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label>Tipo de documento <span class="text-danger">*</span></label>
                        <select class="form-control" name="tipo_documento" required>
                            <option value="">Seleccionar</option>
                            <?php foreach (['DNI','LE','LC','CI'] as $tipo): ?>
                                <option value="<?= $tipo ?>" <?= $campos['tipo_documento']==$tipo?'selected':'' ?>><?= $tipo ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group col-md-4">
                        <label>Documento <span class="text-danger">*</span></label>
                        <input type="number" name="documento" class="form-control" placeholder="Solo números" value="<?= htmlspecialchars($campos['documento']) ?>" required>
                    </div>
                    <div class="form-group col-md-4">
                        <label>Nro de socio</label>
                        <input type="text" name="nro_afiliado" class="form-control" value="<?= htmlspecialchars($campos['nro_afiliado']) ?>">
                    </div>
                </div>

                <!-- Fecha nacimiento y sexo -->
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Fecha de nacimiento</label>
                        <input type="date" name="nacimiento" class="form-control" value="<?= $campos['nacimiento'] ?>">
                    </div>
                    <div class="form-group col-md-6">
                        <label>Sexo</label>
                        <select class="form-control" name="sexo">
                            <option value="">Seleccionar</option>
                            <?php foreach(['Masculino','Femenino'] as $s): ?>
                                <option value="<?= $s ?>" <?= $campos['sexo']==$s?'selected':'' ?>><?= $s ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Celular, Fijo y Email -->
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label>Celular</label>
                        <input type="text" name="celular" class="form-control" value="<?= htmlspecialchars($campos['celular']) ?>">
                    </div>
                    <div class="form-group col-md-4">
                        <label>Teléfono fijo</label>
                        <input type="text" name="fijo" class="form-control" value="<?= htmlspecialchars($campos['fijo']) ?>">
                    </div>
                    <div class="form-group col-md-4">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($campos['email']) ?>">
                    </div>
                </div>

                <!-- Provincia, Localidad, Domicilio -->
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label>Provincia</label>
                        <select class="form-control" name="provincia">
                            <option value="">Seleccionar</option>
                            <?php foreach ($provincias as $prov): ?>
                                <option value="<?= $prov ?>" <?= $campos['provincia']==$prov?'selected':'' ?>><?= $prov ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group col-md-4">
                        <label>Localidad</label>
                        <input type="text" name="localidad" class="form-control" value="<?= htmlspecialchars($campos['localidad']) ?>">
                    </div>
                    <div class="form-group col-md-4">
                        <label>Domicilio</label>
                        <input type="text" name="domicilio" class="form-control" value="<?= htmlspecialchars($campos['domicilio']) ?>">
                    </div>
                </div>

                <!-- Obra social -->
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label>Obra social</label>
                        <select class="form-control" name="obra_social_id">
                            <option value="">Seleccionar</option>
                            <?php foreach ($obras as $obra): ?>
                                <option value="<?= $obra['Id'] ?>" <?= $campos['obra_social_id']==$obra['Id']?'selected':'' ?>><?= htmlspecialchars($obra['obra_social']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group col-md-4">
                        <label>Plan</label>
                        <input type="text" name="obra_social_plan" class="form-control" value="<?= htmlspecialchars($campos['obra_social_plan']) ?>">
                    </div>
                    <div class="form-group col-md-4">
                        <label>Número</label>
                        <input type="text" name="obra_social_numero" class="form-control" value="<?= htmlspecialchars($campos['obra_social_numero']) ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Historia clínica</label>
                    <input type="text" name="historia_clinica" class="form-control" value="<?= htmlspecialchars($campos['historia_clinica']) ?>">
                </div>

                <div class="form-group">
                    <label>Comentario</label>
                    <input type="text" name="nota" class="form-control" value="<?= htmlspecialchars($campos['nota']) ?>">
                </div>

                  <div class="form-group text-right">
                    <a href="./?seccion=pacientes&nc=<?= $rand ?>" class="btn btn-secondary">Volver</a>
                    <button type="submit" name="guardar" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($swalGuardado || $swalError): ?>
<script>
document.addEventListener("DOMContentLoaded", function() {
    <?php if ($swalGuardado): ?>
    Swal.fire({
        icon: 'success',
        title: '¡Paciente guardado!',
        text: 'El paciente se registró correctamente.',
        confirmButtonText: 'Aceptar'
    }).then(() => {
        window.location.href = './?seccion=turnos&nc=<?= $rand ?>';
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