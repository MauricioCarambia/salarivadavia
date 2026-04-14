<?php
require_once __DIR__ . '/../inc/db.php'; // $pdo = instancia PDO
$rand = rand(1000, 9999); // valor aleatorio

$id = $_GET['id'] ?? null;
$swalGuardado = false;
$swalError = false;

// Provincias
$provincias = [
    "Ciudad Autónoma de Buenos Aires","Buenos Aires","Catamarca","Chaco",
    "Chubut","Córdoba","Corrientes","Entre Ríos","Formosa","Jujuy",
    "La Pampa","La Rioja","Mendoza","Misiones","Neuquén","Río Negro",
    "Salta","San Juan","San Luis","Santa Cruz","Santa Fe",
    "Santiago del Estero","Tierra del Fuego","Tucumán"
];

// Obtenemos los datos del paciente
$stmt = $pdo->prepare("SELECT * FROM pacientes WHERE Id = :id");
$stmt->execute([':id' => $id]);
$rArray = $stmt->fetch(PDO::FETCH_ASSOC);

// Si no existe el paciente, redirigimos
if (!$rArray) {
    echo "<script>window.location.href='./?seccion=pacientes&nc=$rand';</script>";
    exit;
}

// Guardar cambios
if ($id && isset($_POST['guardar'])) {
    $campos = [
        'documento' => $_POST['documento'] ?? '',
        'nombre' => $_POST['nombre'] ?? '',
        'apellido' => $_POST['apellido'] ?? '',
        'domicilio' => $_POST['domicilio'] ?? '',
        'provincia' => $_POST['provincia'] ?? '',
        'localidad' => $_POST['localidad'] ?? '',
        'celular' => $_POST['celular'] ?? '',
        'fijo' => $_POST['fijo'] ?? '',
        'email' => $_POST['email'] ?? '',
        'tipo_documento' => $_POST['tipo_documento'] ?? '',
        'nacimiento' => $_POST['nacimiento'] ?? null,
        'nro_afiliado' => $_POST['nro_afiliado'] ?? '',
        'obra_social_id' => $_POST['obra_social_id'] ?: null,
        'obra_social_plan' => $_POST['obra_social_plan'] ?? '',
        'obra_social_numero' => $_POST['obra_social_numero'] ?? '',
        'sexo' => $_POST['sexo'] ?? '',
        'historia_clinica' => $_POST['historia_clinica'] ?? '',
        'nota' => $_POST['nota'] ?? ''
    ];

    // Verificar si el documento ya existe en otro paciente
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM pacientes WHERE documento = :documento AND Id != :id");
    $stmt->execute([':documento' => $campos['documento'], ':id' => $id]);
    $cant = $stmt->fetchColumn();

    if ($cant == 0) {
        $sql = "UPDATE pacientes SET
                    nombre = :nombre,
                    apellido = :apellido,
                    domicilio = :domicilio,
                    provincia = :provincia,
                    localidad = :localidad,
                    celular = :celular,
                    fijo = :fijo,
                    email = :email,
                    tipo_documento = :tipo_documento,
                    documento = :documento,
                    nacimiento = :nacimiento,
                    nro_afiliado = :nro_afiliado,
                    obra_social_id = :obra_social_id,
                    obra_social_plan = :obra_social_plan,
                    obra_social_numero = :obra_social_numero,
                    sexo = :sexo,
                    historia_clinica = :historia_clinica,
                    nota = :nota
                WHERE Id = :id";

        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge($campos, [':id' => $id]));

        $swalGuardado = true;
        $rArray = $campos; // Actualizamos rArray para que el formulario muestre lo editado
    } else {
        $swalError = "El documento {$campos['documento']} ya se encuentra registrado.";
    }
}
?>

<div class="content">
    <div class="card card-info card-outline">
        <div class="card-header">
            <h3 class="card-title">Editar paciente</h3>
        </div>
        <div class="card-body">
            <form method="POST" class="form-horizontal">
                <!-- Nombre y Apellido -->
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Nombre <span class="text-danger">*</span></label>
                        <input type="text" name="nombre" class="form-control" placeholder="Nombre"
                               value="<?= htmlspecialchars($rArray['nombre']) ?>" required>
                    </div>
                    <div class="form-group col-md-6">
                        <label>Apellido <span class="text-danger">*</span></label>
                        <input type="text" name="apellido" class="form-control" placeholder="Apellido"
                               value="<?= htmlspecialchars($rArray['apellido']) ?>" required>
                    </div>
                </div>

                <!-- Documento y Nro de socio -->
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label>Tipo de documento <span class="text-danger">*</span></label>
                        <select class="form-control" name="tipo_documento" required>
                            <option value="">Seleccionar</option>
                            <?php foreach(['DNI','LE','LC','CI'] as $tipo): ?>
                                <option value="<?= $tipo ?>" <?= $rArray['tipo_documento']==$tipo?'selected':'' ?>><?= $tipo ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group col-md-4">
                        <label>Documento <span class="text-danger">*</span></label>
                        <input type="number" min="0" step="1" name="documento" class="form-control"
                               value="<?= htmlspecialchars($rArray['documento']) ?>" required>
                    </div>
                    <div class="form-group col-md-4">
                        <label>Nro de socio</label>
                        <input type="text" name="nro_afiliado" class="form-control"
                               value="<?= htmlspecialchars($rArray['nro_afiliado']) ?>">
                    </div>
                </div>

                <!-- Fecha nacimiento y sexo -->
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Fecha de nacimiento</label>
                        <input type="date" name="nacimiento" class="form-control" value="<?= htmlspecialchars($rArray['nacimiento']) ?>">
                    </div>
                    <div class="form-group col-md-6">
                        <label>Sexo</label>
                        <select class="form-control" name="sexo">
                            <option value="">Seleccionar</option>
                            <?php foreach(['Masculino','Femenino'] as $s): ?>
                                <option value="<?= $s ?>" <?= $rArray['sexo']==$s?'selected':'' ?>><?= $s ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Celular, Fijo y Email -->
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label>Celular</label>
                        <input type="text" name="celular" class="form-control" value="<?= htmlspecialchars($rArray['celular']) ?>">
                    </div>
                    <div class="form-group col-md-4">
                        <label>Teléfono fijo</label>
                        <input type="text" name="fijo" class="form-control" value="<?= htmlspecialchars($rArray['fijo']) ?>">
                    </div>
                    <div class="form-group col-md-4">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($rArray['email']) ?>">
                    </div>
                </div>

                <!-- Provincia, Localidad, Domicilio -->
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label>Provincia</label>
                        <select class="form-control" name="provincia">
                            <option value="">Seleccionar</option>
                            <?php foreach($provincias as $prov): ?>
                                <option value="<?= htmlspecialchars($prov) ?>" <?= $rArray['provincia']==$prov?'selected':'' ?>><?= htmlspecialchars($prov) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group col-md-4">
                        <label>Localidad</label>
                        <input type="text" name="localidad" class="form-control" value="<?= htmlspecialchars($rArray['localidad']) ?>">
                    </div>
                    <div class="form-group col-md-4">
                        <label>Domicilio</label>
                        <input type="text" name="domicilio" class="form-control" value="<?= htmlspecialchars($rArray['domicilio']) ?>">
                    </div>
                </div>

                <!-- Obra social -->
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label>Obra social</label>
                        <select class="form-control" name="obra_social_id">
                            <option value="">Seleccionar</option>
                            <?php
                            $stmt = $pdo->query("SELECT * FROM obras_sociales");
                            while($rOS = $stmt->fetch(PDO::FETCH_ASSOC)):
                                $selected = ($rOS['Id']==$rArray['obra_social_id'])?'selected':'';
                            ?>
                                <option value="<?= htmlspecialchars($rOS['Id']) ?>" <?= $selected ?>><?= htmlspecialchars($rOS['obra_social']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group col-md-4">
                        <label>Plan</label>
                        <input type="text" name="obra_social_plan" class="form-control" value="<?= htmlspecialchars($rArray['obra_social_plan']) ?>">
                    </div>
                    <div class="form-group col-md-4">
                        <label>Número</label>
                        <input type="text" name="obra_social_numero" class="form-control" value="<?= htmlspecialchars($rArray['obra_social_numero']) ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Historia clínica</label>
                    <input type="text" name="historia_clinica" class="form-control" value="<?= htmlspecialchars($rArray['historia_clinica']) ?>">
                </div>

                <div class="form-group">
                    <label>Comentario</label>
                    <input type="text" name="nota" class="form-control" value="<?= htmlspecialchars($rArray['nota']) ?>">
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
            title: '¡Paciente actualizado!',
            text: 'Los cambios se guardaron correctamente.',
            confirmButtonText: 'Aceptar'
        }).then(() => {
            window.location.href = './?seccion=pacientes&nc=<?= $rand ?>';
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