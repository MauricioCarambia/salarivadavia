<?php
require_once __DIR__ . '/../inc/db.php';
$rand = rand(1000, 9999);
?>

<div class="row mb-3">
    <div class="col-12">
        <div class="card card-info card-outline">

            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title">Turnos del día</h3>

                <!-- 🔎 FILTRO -->
                <select id="filtroProfesional" class="form-control form-control-sm" style="width:250px;">
                    <option value="">Todos los profesionales</option>
                </select>
            </div>

            <div class="card-body table-responsive">

                <table class="table table-hover" id="tablaTurnos">
                    <thead class="thead-dark">
                        <tr>
                            <th>Horario</th>
                            <th>Profesional</th>
                            <th>Paciente</th>
                            <th>Documento</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php
                        $sql = "SELECT 
    t.*, 
    p.documento, 
    p.nombre AS pacienteNombre, 
    p.apellido AS pacienteApellido, 
    pr.Id as profesional_id,
    pr.nombre AS profesionalNombre, 
    pr.apellido AS profesionalApellido
FROM turnos t
LEFT JOIN pacientes p ON p.Id = t.paciente_id
LEFT JOIN profesionales pr ON pr.Id = t.profesional_id
WHERE DATE(t.fecha) = CURDATE()
ORDER BY pr.apellido, pr.nombre, t.fecha ASC";

                        $stmt = $pdo->query($sql);
                        $turnos = $stmt->fetchAll(PDO::FETCH_ASSOC);

                        /* ==============================
   AGRUPAR
==============================*/
                        $agrupados = [];

                        foreach ($turnos as $t) {
                            $pid = $t['profesional_id'];

                            if (!isset($agrupados[$pid])) {
                                $agrupados[$pid] = [
                                    'nombre' => $t['profesionalApellido'] . ' ' . $t['profesionalNombre'],
                                    'total' => 0,
                                    'atendidos' => 0,
                                    'asistieron' => 0,
                                    'ausentes' => 0,
                                    'turnos' => []
                                ];
                            }

                            $agrupados[$pid]['turnos'][] = $t;
                            $agrupados[$pid]['total']++;

                            if ((int)$t['asistio'] === 1) {
                                $agrupados[$pid]['asistieron']++;

                                if ((int)$t['atendido'] === 1) {
                                    $agrupados[$pid]['atendidos']++;
                                }
                            } else {
                                $agrupados[$pid]['ausentes']++;
                            }
                        }

                        /* ==============================
   MOSTRAR
==============================*/
                        foreach ($agrupados as $pid => $grupo):

                            $nombre = $grupo['nombre'];
                            $total = $grupo['total'];

                            // 🎨 COLOR SEGÚN CANTIDAD
                            if ($total <= 5) $color = 'success';
                            elseif ($total <= 10) $color = 'warning';
                            else $color = 'danger';
                        ?>

                            <!-- 🔽 HEADER COLAPSABLE -->
                            <tr class="grupo-header" data-profesional="<?= $pid ?>" style="cursor:pointer; background:#343a40; color:white;">
                                <td colspan="5">
                                    <div">
                                        <strong><?= htmlspecialchars($nombre) ?></strong>
                                        <span class="badge badge-<?= $color ?>">
                                            <?= $total ?> turnos
                                        </span>

                                        <span class="badge badge-success ml-2">
                                            <?= $grupo['asistieron'] ?> asistieron
                                        </span>

                                        <span class="badge badge-primary ml-1">
                                            <?= $grupo['atendidos'] ?> atendidos
                                        </span>

                                        <span class="badge badge-danger ml-1">
                                            <?= $grupo['ausentes'] ?> ausentes
                                        </span>
            </div>
            </td>
            </tr>

            <?php foreach ($grupo['turnos'] as $r):

                                $horaTurno = strtotime($r['fecha']);
                                $ahora = time();

                                // ⏱ TURNO ACTUAL (± duración aprox 30 min)
                                $esActual = ($horaTurno <= $ahora && $horaTurno + 1800 >= $ahora);
            ?>

                <tr class="fila-turno grupo-<?= $pid ?>" data-profesional="<?= $pid ?>"
                    style="<?= $r['sobreturno'] ? 'border-left:4px solid #ffc107;' : '' ?>">

                    <!-- HORARIO -->
                    <td>
                        <?= date('H:i', $horaTurno) ?>

                        <?php if ($esActual): ?>
                            <span class="badge badge-danger ml-1">Ahora</span>
                        <?php endif; ?>
                    </td>

                    <!-- PROFESIONAL -->
                    <td><?= htmlspecialchars($nombre) ?></td>

                    <!-- PACIENTE -->
                    <td>
                        <div class="d-flex justify-content-between">
                            <?= htmlspecialchars($r['pacienteApellido'] . ' ' . $r['pacienteNombre']) ?>

                            <?php if ($r['sobreturno']): ?>
                                <span class="badge badge-warning">Sobreturno</span>
                            <?php endif; ?>
                        </div>
                    </td>

                    <!-- DOCUMENTO -->
                    <td><?= htmlspecialchars($r['documento']) ?></td>

                    <!-- ACCIONES -->
                    <td>
                        <a href="./?seccion=turnos_ver&id=<?= $r['Id'] ?>&nc=<?= $rand ?>"
                            class="btn btn-primary btn-sm rounded-circle">
                            <i class="fa fa-eye"></i>
                        </a>
                    </td>

                </tr>

        <?php endforeach;
                        endforeach; ?>

        </tbody>
        </table>

        </div>
    </div>
</div>
</div>

<script>
    $(document).ready(function() {

        // 🔽 COLAPSAR
        $('.grupo-header').click(function() {
            let pid = $(this).data('profesional');
            $('.grupo-' + pid).toggle();
        });

        // 🔎 FILTRO
        let profesionales = {};

        $('.fila-turno').each(function() {
            let pid = $(this).data('profesional');
            let nombre = $(this).find('td:eq(1)').text();

            if (!profesionales[pid]) {
                profesionales[pid] = nombre;
                $('#filtroProfesional').append(
                    `<option value="${pid}">${nombre}</option>`
                );
            }
        });

        $('#filtroProfesional').change(function() {
            let pid = $(this).val();

            if (!pid) {
                $('.fila-turno, .grupo-header').show();
            } else {
                $('.fila-turno, .grupo-header').hide();
                $('.grupo-' + pid).show();
                $('.grupo-header[data-profesional="' + pid + '"]').show();
            }
        });

    });
</script>