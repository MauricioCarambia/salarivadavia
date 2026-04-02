<?php
require_once __DIR__ . '/../inc/db.php';

$busqueda = trim($_GET['busqueda'] ?? '');
$rand = random_int(1000, 9999);

$pacientes = [];

/* ================= BUSQUEDA OPTIMIZADA ================= */
if ($busqueda !== '' && strlen($busqueda) >= 2) {

    $sql = $pdo->prepare("
        SELECT 
            Id,
            apellido,
            nombre,
            documento,
            nacimiento,
            celular,
            historia_clinica
        FROM pacientes
        WHERE
              apellido      LIKE ?
           OR nombre        LIKE ?
           OR documento     LIKE ?
           OR nro_afiliado  LIKE ?
        ORDER BY apellido ASC
        LIMIT 25
    ");

    $buscar = "%{$busqueda}%";

    // Pasamos 4 veces el mismo valor para cada columna
    $sql->execute([$buscar, $buscar, $buscar, $buscar]);
    $pacientes = $sql->fetchAll(PDO::FETCH_ASSOC);
}
?>

<div class="row mb-3">
    <div class="col-12">
        <div class="card card-primary card-outline">

            <h1 class="ml-3 mt-2">Historia clínica</h1>

            <!-- BUSCADOR -->
            <div class="card-body">
                <form id="formBusqueda" method="GET">
                    <input type="hidden" name="seccion" value="historia_pacientes">
                    <input type="hidden" name="nc" value="<?= $rand ?>">

                    <div class="input-group input-group-lg">
                        <input type="text" class="form-control" name="busqueda"
                            value="<?= htmlspecialchars($busqueda) ?>"
                            placeholder="Buscar paciente (apellido, nombre, DNI...)" required autofocus>

                        <div class="input-group-append">
                            <button class="btn btn-success">
                                <i class="fa fa-search"></i> Buscar
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <?php if ($busqueda !== ''): ?>

                <div class="card-body table-responsive">

                    <table id="tablaPacientes" class="table table-hover datatable">

                        <thead class="thead-light">
                            <tr>
                                <th width="60"></th>
                                <th>Paciente</th>
                                <th>Documento</th>
                                <th>Fecha Nac.</th>
                                <th>Celular</th>
                                <th>HC</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($pacientes as $p): ?>
                                <tr>

                                    <td>
                                        <a href="./?seccion=historia_clinica&id=<?= $p['Id'] ?>&nc=<?= $rand ?>"
                                            class="btn btn-info btn-sm rounded-circle">
                                            <i class="fa fa-eye"></i>
                                        </a>
                                    </td>

                                    <td><?= htmlspecialchars($p['apellido'] . ' ' . $p['nombre']) ?></td>
                                    <td><?= htmlspecialchars($p['documento']) ?></td>
                                    <td><?= $p['nacimiento'] ? date('d/m/Y', strtotime($p['nacimiento'])) : '-' ?></td>
                                    <td><?= htmlspecialchars($p['celular']) ?></td>
                                    <td>
                                        <span class="badge badge-secondary">
                                            <?= htmlspecialchars($p['historia_clinica']) ?>
                                        </span>
                                    </td>

                                </tr>
                            <?php endforeach; ?>

                        </tbody>
                    </table>

                    <?php if (empty($pacientes)): ?>
                        <div class="alert alert-warning text-center mt-3">
                            No se encontraron pacientes
                        </div>
                    <?php endif; ?>

                </div>

            <?php endif; ?>

        </div>
    </div>
</div>

<script>
    $(document).ready(function () {
        $('.datatable').each(function () {
            initDataTable($(this));
        });
    });
</script>