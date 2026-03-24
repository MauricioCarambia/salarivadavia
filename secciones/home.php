<?php
// home.php - contenido de la sección principal
// No se incluyen <html>, <head>, <body> ni librerías; todo viene desde index.php

$dias = ["Domingo","Lunes","Martes","Miércoles","Jueves","Viernes","Sábado"];
$meses = ["Enero","Febrero","Marzo","Abril","Mayo","Junio","Julio","Agosto",
"Septiembre","Octubre","Noviembre","Diciembre"];
?>

<!-- Notas a Tener en Cuenta -->
<div class="row mb-3">
    <div class="col-12">
        <div class="card card-info card-outline">
            <div class="card-header"><h3 class="card-title">Notas a Tener en Cuenta</h3></div>
            <div class="card-body table-responsive">
                <table class="table table-striped table-bordered datatable" style="width:100%">
                    <thead class="thead-dark">
                        <tr>
                            <th>#</th>
                            <th>Profesional</th>
                            <th>Especialidad</th>
                            <th>Nota</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $consultaC = "SELECT pr.apellido, pr.nombre, e.especialidad, pr.comentario
                                  FROM profesionales pr
                                  LEFT JOIN especialidades e ON pr.especialidad_id = e.Id
                                  WHERE pr.comentario IS NOT NULL AND pr.comentario != ''";
                    $stmtC = $conexion->prepare($consultaC);
                    $stmtC->execute();
                    $contNotas = 0;
                    while($row = $stmtC->fetch(PDO::FETCH_ASSOC)){
                        $contNotas++;
                        echo "<tr>
                        <td>{$contNotas}</td>
                        <td>" . htmlspecialchars($row['apellido'] . ' ' . $row['nombre']) . "</td>
                        <td>" . htmlspecialchars($row['especialidad']) . "</td>
                        <td>" . htmlspecialchars($row['comentario']) . "</td>
                        </tr>";
                    }
                    ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Últimos Turnos -->
<div class="row mb-3">
    <div class="col-12">
        <div class="card card-primary card-outline">
            <div class="card-header"><h3 class="card-title">Últimos Turnos</h3></div>
            <div class="card-body table-responsive">
                <table class="table table-striped table-bordered datatable" style="width:100%">
                    <thead class="thead-dark">
                        <tr>
                            <th>#</th>
                            <th>Paciente</th>
                            <th>Documento</th>
                            <th>Profesional</th>
                            <th>Horario</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $consulta = "SELECT t.*, p.nombre AS pacienteNombre, p.apellido AS pacienteApellido, p.documento,
                                         pr.nombre AS profesionalNombre, pr.apellido AS profesionalApellido
                                 FROM turnos t
                                 LEFT JOIN pacientes p ON p.Id = t.paciente_id
                                 LEFT JOIN profesionales pr ON pr.Id = t.profesional_id
                                 ORDER BY t.Id DESC LIMIT 25";
                    $stmt = $conexion->prepare($consulta);
                    $stmt->execute();
                    $contTurnos = 0;
                    while($r = $stmt->fetch(PDO::FETCH_ASSOC)){
                        $contTurnos++;
                        $hora = date('H:i \h\s - d/m/Y', strtotime($r['fecha']));
                        echo "<tr>
                            <td>{$contTurnos}</td>
                            <td>" . htmlspecialchars($r['pacienteApellido'] . ' ' . $r['pacienteNombre']) . "</td>
                            <td>" . htmlspecialchars($r['documento']) . "</td>
                            <td>" . htmlspecialchars($r['profesionalApellido'] . ' ' . $r['profesionalNombre']) . "</td>
                            <td>{$hora}</td>
                        </tr>";
                    }
                    ?>
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