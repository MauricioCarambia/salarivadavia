<?php
require_once __DIR__ . '/../inc/db.php';
$rand = rand(1000,9999);
?>

<div class="row mb-3">
    <div class="col-12">
        <div class="card card-info card-outline">
            <div class="card-header">
                <h3 class="card-title">
                    Profesionales
                    <!--<a href="./?seccion=turnos_new&nc=<?php echo $rand; ?>" class="btn btn-info btn-sm"><i class="fa fa-plus"></i></a>-->
                    <a href="./?seccion=turnos_dia&nc=<?php echo $rand; ?>" class="btn btn-success btn-sm">
                        <i class="fa fa-eye"></i> Ver turnos del día
                    </a>
                </h3>
            </div>
            <div class="card-body table-responsive">
                <table class="table table-striped table-bordered table-hover datatable" style="width:100%">
                    <thead class="thead-dark">
                        <tr>
                            <th>Acción</th>
                            <th>Especialidad</th>
                            <th>Profesional</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sqlProfesionales = "
                            SELECT  p.Id,
                                    p.apellido,
                                    p.nombre,
                                    e.especialidad
                            FROM profesionales AS p
                            LEFT JOIN especialidades AS e ON e.Id = p.especialidad_id
                            ORDER BY e.especialidad, p.apellido, p.nombre
                        ";
                        $stmt = $conexion->prepare($sqlProfesionales);
                        $stmt->execute();
                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)):
                        ?>
                        <tr>
                            <td>
                                <a href="./?seccion=turnos_calendario&id=<?= $row['Id'] ?>&nc=<?= $rand ?>" 
                                   class="btn btn-success btn-sm rounded-circle">
                                    <i class="fa fa-calendar"></i>
                                </a>
                            </td>
                            <td><?= htmlspecialchars($row["especialidad"]) ?></td>
                            <td><?= htmlspecialchars($row["apellido"]." ".$row["nombre"]) ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener("DOMContentLoaded", function () {

    // Inicializar DataTable usando la función global definida en index.php
    $('.datatable').each(function () {
        initDataTable($(this));
    });
});
</script>