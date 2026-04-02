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
   ESTADO SOCIO (MOROSO > 3 MESES)
================================ */
$stmtEstado = $conexion->prepare("
    SELECT MAX(fecha_correspondiente) as ultima_fecha
    FROM pagos_afiliados
    WHERE paciente_id = :id
");
$stmtEstado->execute([':id' => $id]);

$ultimaFecha = $stmtEstado->fetchColumn();

$aldia = false;

if ($ultimaFecha) {

   $fechaUltimoPago = new DateTime($ultimaFecha);
   $hoy = new DateTime();

   $diff = $fechaUltimoPago->diff($hoy);

   $mesesDeuda = ($diff->y * 12) + $diff->m;

   // 🔥 regla: si debe MÁS de 3 meses → moroso
   $aldia = ($mesesDeuda <= 2);
   $mesesDeuda = max(0, $mesesDeuda);
} else {
   // nunca pagó → moroso directo
   $aldia = false;
}

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


              
                  <a href="./?seccion=afiliados_new&id=<?= $id ?>&nc=<?= $rand ?>" class="btn btn-success btn-sm">
                     <i class="fas fa-plus"></i> Nuevo pago
                  </a>
            

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
                  <small class="text-danger">
                     Debe <?= $mesesDeuda ?> meses
                  </small>
               <?php endif; ?>

            </div>

         </div>
      </div>



      <!-- TABLA PAGOS -->
      <div class="row mb-3">
         <div class="col-12">
            <div class="card card-info card-outline">


               <div class="card-body table-responsive">

                  <table class="table table-striped datatable">
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

                                       <!-- EDITAR -->
                                       <button class="btn btn-sm btn-success btnEdit rounded-circle"
                                          data-id="<?= $pago['Id'] ?>" data-monto="<?= $pago['monto'] ?>"
                                          data-fecha="<?= date('Y-m', strtotime($pago['fecha_correspondiente'])) ?>"
                                          title="Editar">
                                          <i class="fas fa-edit"></i>
                                       </button>

                                       <!-- ELIMINAR -->
                                       <button class="btn btn-sm btn-danger btnDelete rounded-circle"
                                          data-id="<?= $pago['Id'] ?>" title="Eliminar">
                                          <i class="fas fa-trash"></i>
                                       </button>

                                    </div>
                               
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
<div class="modal fade" id="modalEditarPago">
   <div class="modal-dialog">
      <div class="modal-content">

         <div class="modal-header bg-info">
            <h5 class="modal-title">Editar pago</h5>
            <button type="button" class="close" data-dismiss="modal">&times;</button>
         </div>

         <form id="formEditarPago">

            <div class="modal-body">

               <input type="hidden" name="id" id="edit_id">

               <div class="form-group">
                  <label>Monto</label>
                  <input type="number" name="monto" id="edit_monto" class="form-control" required>
               </div>

               <div class="form-group">
                  <label>Mes</label>
                  <input type="month" name="fecha" id="edit_fecha" class="form-control" required>
               </div>

            </div>

            <div class="modal-footer">
               <button type="submit" class="btn btn-success">Guardar</button>
               <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
            </div>

         </form>

      </div>
   </div>
</div>
<script>
   $(function () {

      let tabla = $('.datatable').DataTable({
         order: [[0, 'desc']]
      });

      /* =========================
         EDITAR - ABRIR MODAL
      ========================= */
      $(document).on('click', '.btnEdit', function () {

         $('#edit_id').val($(this).data('id'));
         $('#edit_monto').val($(this).data('monto'));
         $('#edit_fecha').val($(this).data('fecha'));

         $('#modalEditarPago').modal('show');
      });

      /* =========================
         EDITAR - GUARDAR AJAX
      ========================= */
      $('#formEditarPago').submit(function (e) {
         e.preventDefault();

         $.ajax({
            url: 'ajax/afiliado_pago_update.php',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',

            success: function (resp) {

               if (resp.success) {

                  Swal.fire({
                     icon: 'success',
                     title: 'Actualizado',
                     timer: 1500,
                     showConfirmButton: false
                  });

                  setTimeout(() => location.reload(), 1200);

               } else {
                  Swal.fire('Error', resp.message, 'error');
               }
            },

            error: function () {
               Swal.fire('Error', 'Error de conexión', 'error');
            }
         });

      });

      /* =========================
         ELIMINAR
      ========================= */
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

                  success: function (resp) {

                     if (resp.success) {

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