<?php
$user_tipo = $_SESSION['tipo'] ?? null;
$id = $_GET['id'] ?? 0;

/* ===============================
   PACIENTE
================================ */
$stmtPaciente = $conexion->prepare("
    SELECT *
    FROM pacientes
    WHERE Id = :id
");
$stmtPaciente->execute([':id' => $id]);
$paciente = $stmtPaciente->fetch(PDO::FETCH_ASSOC);

/* ===============================
   AL DÍA
================================ */
$stmtAldia = $conexion->prepare("
    SELECT Id
    FROM pagos_afiliados
    WHERE paciente_id = :id
    AND fecha_correspondiente BETWEEN DATE_FORMAT(NOW(),'%Y-%m-01')
    AND '2030-01-01'
    LIMIT 1
");
$stmtAldia->execute([':id' => $id]);

$aldia = ($stmtAldia->rowCount() > 0);

/* ===============================
   PAGOS
================================ */
$stmtPagos = $conexion->prepare("
    SELECT *,
           MONTH(fecha_correspondiente) AS mes,
           YEAR(fecha_correspondiente) AS anio
    FROM pagos_afiliados
    WHERE paciente_id = :id
    ORDER BY fecha_correspondiente DESC
");
$stmtPagos->execute([':id' => $id]);
$pagos = $stmtPagos->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="row mb-3">
   <div class="col-12">
      <div class="card card-info card-outline">
         <div class="card-header">
            <h3 class="card-title">Historial de pagos del socio


               <?php if ($user_tipo == 'admin' || $user_tipo == 'contable'): ?>
                  <a href="./?seccion=afiliados_new&id=<?= $id ?>&nc=<?= $rand ?>" class="btn btn-success btn-sm">
                     <i class="fas fa-plus"></i> Nuevo pago
                  </a>
               <?php endif; ?>

               <a href="./?seccion=socios&nc=<?= $rand ?>" class="btn btn-secondary btn-sm ml-2 rounded">
                  Volver
               </a>
            </h3>
         </div>


         <!-- CARD PACIENTE -->

         <div class="card-body">

            <div class="row ">
               <div class="col-md-4">
                  <strong>Nombre:</strong><br>
                  <?= $paciente['apellido'] . ' ' . $paciente['nombre'] ?>
               </div>

               <div class="col-md-2">
                  <strong>N° socio:</strong><br>
                  <?= $paciente['nro_afiliado'] ?>
               </div>

               <div class="col-md-3">
                  <strong>Celular:</strong><br>
                  <?= $paciente['celular'] ?>
               </div>

               <div class="col-md-3">
                  <strong>Teléfono fijo:</strong><br>
                  <?= $paciente['fijo'] ?>
               </div>
            </div>

            <hr>

            <div>
               <strong>Estado:</strong><br>

               <?php if ($aldia): ?>
                  <span class="badge badge-success p-2">
                     <i class="fas fa-check"></i> Al día
                  </span>
               <?php else: ?>
                  <span class="badge badge-danger p-2">
                     <i class="fas fa-times"></i> Moroso
                  </span>
               <?php endif; ?>
            </div>

         </div>
      </div>



      <!-- TABLA PAGOS -->
      <div class="row mb-3">
         <div class="col-12">
            <div class="card card-info card-outline">


               <div class="card-body table-responsive">

                  <table class="table table-bordered table-striped datatable">
                     <thead>
                        <tr>
                           <th>Fecha correspondiente</th>
                           <th>Periodo</th>
                           <th>Fecha pago</th>
                           <th>Monto</th>
                           <th>Acciones</th>
                        </tr>
                     </thead>

                     <tbody>
                        <?php foreach ($pagos as $pago): ?>
                           <tr>
                              <td><?= $pago['fecha_correspondiente'] ?></td>

                              <td>
                                 <?= isset($meses[$pago['mes'] - 1])
                                    ? $meses[$pago['mes'] - 1] . ' de ' . $pago['anio']
                                    : '-'; ?>
                              </td>

                              <td>
                                 <?= date('d/m/Y', strtotime($pago['fecha_pago'])) ?>
                              </td>

                              <td>$<?= number_format($pago['monto'], 2) ?></td>

                              <td>
                                 <div class="btn-group">
                                    <?php if ($user_tipo == 'admin' || $user_tipo == 'contable'): ?>

                                       <a href="./?seccion=afiliados_edit&pid=<?= $pago['Id'] ?>&id=<?= $id ?>&nc=<?= $rand ?>"
                                          class="btn btn-sm btn-success rounded-circle">
                                          <i class="fas fa-edit"></i>
                                       </a>

                                       <button class="btn btn-sm btn-danger btnDelete rounded-circle"
                                          data-id="<?= $pago['Id'] ?>">
                                          <i class="fas fa-trash"></i>
                                       </button>
                                    </div>
                                 <?php endif; ?>

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
</div>
</div>
<script>
   $(function () {

      // Inicializar DataTable
      let tabla = $('.datatable').DataTable({
         order: [[0, 'desc']]

      });

      // DELETE AJAX
      $(document).on('click', '.btnDelete', function () {

         let id = $(this).data('id');
         let fila = $(this).closest('tr');

         Swal.fire({
            title: '¿Eliminar pago?',
            text: "No se puede deshacer",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
         }).then((result) => {

            if (result.isConfirmed) {

               $.ajax({
                  url: 'ajax/afiliado_pago_delete.php',
                  type: 'POST',
                  data: { id: id },
                  dataType: 'json',
                  xhrFields: {
                     withCredentials: true
                  },
                  success: function (resp) {

                     if (resp.success) {

                        // ✅ ELIMINAR BIEN EN DATATABLE
                        tabla.row(fila).remove().draw();

                        Swal.fire({
                           icon: 'success',
                           title: 'Eliminado',
                           timer: 1500,
                           showConfirmButton: false
                        });

                     } else {
                        Swal.fire('Error', resp.message, 'error');
                     }

                  },

                  error: function () {
                     Swal.fire('Error', 'Error de conexión', 'error');
                  }

               });

            }

         });

      });

   });
</script>