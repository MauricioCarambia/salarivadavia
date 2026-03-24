<?php
require_once 'inc/db.php';

$rand = random_int(1000, 9999);

// Traer todos los profesionales con su especialidad
$sql = "SELECT 
            p.Id,
            p.apellido,
            p.nombre,
            p.porcentaje,
            e.especialidad
        FROM profesionales p
        LEFT JOIN especialidades e ON e.Id = p.especialidad_id
        ORDER BY p.apellido ASC";

$stmt = $conexion->prepare($sql);
$stmt->execute();
$profesionales = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Main Wrapper -->
<div id="wrapper">
<div class="normalheader transition animated fadeIn small-header">
  <div class="hpanel">
    <div class="panel-body">
      <h2>Pagos a profesionales</h2>
    </div>
  </div>
</div>

<div class="content animate-panel">
  <div class="row">
    <div class="col-lg-12">
      <div class="hpanel">
        <div class="panel-body">
          <table class="table table-striped table-bordered table-hover dataTables-example">
            <thead>
              <tr>
                <th>Apellido</th>
                <th>Nombre</th>
                <th>Especialidad</th>
                <th>Saldo</th>
                <th>&Uacute;ltimo pago</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
            <?php foreach($profesionales as $prof): 
                $profId = $prof['Id'];

                // Total pagado al profesional
                $sql1 = "SELECT COALESCE(SUM(monto),0) AS total_profesional 
                         FROM pagos_profesionales 
                         WHERE profesional_id = :pid";
                $stmt1 = $conexion->prepare($sql1);
                $stmt1->execute(['pid' => $profId]);
                $totalProfesional = $stmt1->fetchColumn();

                // Total generado en turnos según porcentaje
                $sql2 = "SELECT COALESCE(SUM(pago),0) * :porcentaje / 100 AS total_recibido 
                         FROM turnos 
                         WHERE profesional_id = :pid";
                $stmt2 = $conexion->prepare($sql2);
                $stmt2->execute([
                    'pid' => $profId,
                    'porcentaje' => $prof['porcentaje']
                ]);
                $totalRecibido = $stmt2->fetchColumn();

                // Diferencia / saldo
                $saldo = $totalRecibido - $totalProfesional;

                // Último pago
                $sql3 = "SELECT fecha FROM pagos_profesionales 
                         WHERE profesional_id = :pid 
                         ORDER BY fecha DESC LIMIT 1";
                $stmt3 = $conexion->prepare($sql3);
                $stmt3->execute(['pid' => $profId]);
                $ultimoPago = $stmt3->fetchColumn();
            ?>
              <tr>
                <td><?= htmlspecialchars($prof['apellido']) ?></td>
                <td><?= htmlspecialchars($prof['nombre']) ?></td>
                <td><?= htmlspecialchars($prof['especialidad']) ?></td>
                <td>$<?= number_format($saldo,2) ?></td>
                <td><?= $ultimoPago ? date('d/m/Y', strtotime($ultimoPago)) : '-' ?></td>
                <td>
                  <a href="./?seccion=pagos_new&id=<?= $profId ?>&nc=<?= $rand ?>" class="btn btn-info"><i class="fa fa-plus"></i></a>
                  <a href="./?seccion=pagos_view&id=<?= $profId ?>&nc=<?= $rand ?>" class="btn btn-success"><i class="fa fa-eye"></i> Historial de pagos</a>
                  <a href="./?seccion=pagos_fechas&id=<?= $profId ?>&nc=<?= $rand ?>" class="btn btn-warning"><i class="fa fa-eye"></i> Historial de turnos</a>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Page-Level Scripts -->
<script>
$(document).ready(function(){
    $('.dataTables-example').DataTable({
        "iDisplayLength": 100,
        "aLengthMenu": [[10, 25, 50, 100, 1000],[10, 25, 50, 100, 1000]],
        dom: '<"html5buttons"B>lTfgitp',
        buttons: [
            {extend: 'excel', title: 'profesionales'},
            {extend: 'pdf', title: 'profesionales'},
            {extend: 'print', text: 'IMPRIMIR', customize: function (win){
                $(win.document.body).addClass('white-bg');
                $(win.document.body).css('font-size', '10px');
                $(win.document.body).find('table').addClass('compact').css('font-size','inherit');
            }}
        ],
        "language": {
            "sProcessing": "Procesando...",
            "sLengthMenu": "Mostrar _MENU_ registros",
            "sZeroRecords": "No se encontraron resultados",
            "sEmptyTable": "Ningún dato disponible en esta tabla",
            "sInfo": "Mostrando registros del _START_ al _END_ de un total de _TOTAL_ registros",
            "sInfoEmpty": "Mostrando registros del 0 al 0 de un total de 0 registros",
            "sInfoFiltered": "(filtrado de un total de _MAX_ registros)",
            "sSearch": "Buscar:",
            "oPaginate": { "sFirst":"Primero", "sLast":"Último", "sNext":"Siguiente", "sPrevious":"Anterior" }
        }
    });
});
</script>