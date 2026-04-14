<?php
function obtenerListaEspera(PDO $conexion, string $especialidad): array
{
    $stmt = $conexion->prepare("
        SELECT *
        FROM lista_espera
        WHERE especialidad = :especialidad
        ORDER BY Id DESC
        LIMIT 50
    ");

    $stmt->execute([':especialidad' => $especialidad]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function colorAsignado(?string $estado): string
{
    return match ($estado) {
        'Confirmo' => 'table-success',
        'No Confirmo' => 'table-danger',
        'Pendiente Confirmacion' => 'table-warning',
        'Agendado' => 'table-primary',
        default => ''
    };
}
function badgeEstado(?string $estado): string
{
    return match ($estado) {
        'Confirmo' => '<span class="badge badge-success"><i class="fa fa-check"></i> Confirmó</span>',
        'No Confirmo' => '<span class="badge badge-danger"><i class="fa fa-times"></i> No confirmó</span>',
        'Pendiente Confirmacion' => '<span class="badge badge-warning"><i class="fa fa-clock"></i> Pendiente</span>',
        'Agendado' => '<span class="badge badge-primary"><i class="fa fa-edit"></i> Agendado</span>',
        default => '<span class="badge badge-secondary">Sin estado</span>'
    };
}

$registros = obtenerListaEspera($conexion, $especialidad);
?>

<div class="card card-info card-outline">

    <div class="card-header">
        <h3 class="card-title"><?= $titulo ?>


            <a href="./?seccion=lista_espera_new" class="btn btn-success btn-sm">
                <i class="fa fa-plus"></i> Nuevo
            </a>
        </h3>
    </div>

    <div class="card-body table-responsive">

        <table class="table  table-striped datatable">

            <thead>
                <tr>
                    <th>Paciente</th>
                    <th>Documento</th>
                    <th>Celular</th>
                    <th>Edad</th>
                    <th>Disponibilidad</th>
                    <th>Preferencia horario</th>
                    <th>Profesional / turno</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>

            <tbody>

                <?php foreach ($registros as $r): ?>
                    <tr>

                        <td><?= htmlspecialchars($r['apellido'] . ' ' . $r['nombre']) ?></td>
                        <td><?= htmlspecialchars($r['documento']) ?></td>
                        <td><?= htmlspecialchars($r['celular']) ?></td>
                        <td><?= htmlspecialchars($r['edad']) ?></td>
                        <td><?= htmlspecialchars($r['disponibilidad']) ?></td>
                        <td><?= htmlspecialchars($r['horario']) ?></td>
                        <td><?= htmlspecialchars($r['profesional']) ?></td>

                        <td><?= badgeEstado($r['asignado']) ?></td>

                        <td>
                            <div class="btn-group">

                                <button class="btn btn-success btn-sm rounded-circle btnEdit" data-id="<?= $r['Id'] ?>"
                                    data-nombre="<?= htmlspecialchars($r['nombre']) ?>"
                                    data-apellido="<?= htmlspecialchars($r['apellido']) ?>"
                                    data-celular="<?= htmlspecialchars($r['celular']) ?>"
                                    data-edad="<?= htmlspecialchars($r['edad']) ?>"
                                    data-horario="<?= htmlspecialchars($r['horario']) ?>"
                                    data-profesional="<?= htmlspecialchars($r['profesional']) ?>"
                                    data-asignado="<?= htmlspecialchars($r['asignado']) ?>">
                                    <i class="fas fa-edit"></i>
                                </button>

                                <button class="btn btn-danger btn-sm btnDelete rounded-circle" data-id="<?= $r['Id'] ?>">
                                    <i class="fas fa-trash "></i>
                                </button>

                            </div>
                        </td>

                    </tr>
                <?php endforeach; ?>

            </tbody>
        </table>

    </div>
</div>
<div class="modal fade" id="modalEditar">
    <div class="modal-dialog">
        <div class="modal-content">

            <div class="modal-header bg-success">
                <h5 class="modal-title">Editar paciente</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>

            <form id="formEditar">

                <div class="modal-body">

                    <input type="hidden" name="id" id="edit_id">

                    <div class="form-group">
                        <label>Nombre</label>
                        <input type="text" id="edit_nombre" name="nombre" class="form-control">
                    </div>

                    <div class="form-group">
                        <label>Apellido</label>
                        <input type="text" id="edit_apellido" name="apellido" class="form-control">
                    </div>

                    <div class="form-group">
                        <label>Celular</label>
                        <input type="text" id="edit_celular" name="celular" class="form-control">
                    </div>

                    <div class="form-group">
                        <label>Edad</label>
                        <input type="number" id="edit_edad" name="edad" class="form-control">
                    </div>

                    <div class="form-group">
                        <label>Horario</label>
                        <input type="text" id="edit_horario" name="horario" class="form-control">
                    </div>

                    <div class="form-group">
                        <label>Profesional</label>
                        <input type="text" id="edit_profesional" name="profesional" class="form-control">
                    </div>

                    <div class="form-group">
                        <label>Estado</label>
                        <select id="edit_asignado" name="asignado" class="form-control">
                            <option value="">Seleccionar</option>
                            <option value="Confirmo">Confirmo</option>
                            <option value="No Confirmo">No Confirmo</option>
                            <option value="Pendiente Confirmacion">Pendiente Confirmacion</option>
                            <option value="Agendado">Agendado</option>
                        </select>
                    </div>

                </div>

                <div class="modal-footer">
                    <button class="btn btn-success">Guardar</button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                </div>

            </form>

        </div>
    </div>
</div>
<script>
    $(function(){

    let tabla = $('.datatable').DataTable();

    /* ================= EDITAR ================= */
    $(document).on('click','.btnEdit',function(){

        $('#edit_id').val($(this).data('id'));
        $('#edit_nombre').val($(this).data('nombre'));
        $('#edit_apellido').val($(this).data('apellido'));
        $('#edit_celular').val($(this).data('celular'));
        $('#edit_edad').val($(this).data('edad'));
        $('#edit_horario').val($(this).data('horario'));
        $('#edit_profesional').val($(this).data('profesional'));
        $('#edit_asignado').val($(this).data('asignado'));

        $('#modalEditar').modal('show');
    });

    /* ================= GUARDAR EDIT ================= */
    $('#formEditar').submit(function(e){
        e.preventDefault();

        $.post('ajax/lista_espera_update.php', $(this).serialize(), function(resp){

            if(resp.success){

                Swal.fire({
                    icon:'success',
                    title:'Actualizado',
                    timer:1200,
                    showConfirmButton:false
                });

                setTimeout(()=>location.reload(),1200);

            }else{
                Swal.fire('Error',resp.message,'error');
            }

        },'json');
    });

    /* ================= ELIMINAR ================= */
    $(document).on('click','.btnDelete',function(){

        let id = $(this).data('id');
        let fila = $(this).closest('tr');

        Swal.fire({
            title:'¿Eliminar paciente?',
            icon:'warning',
            showCancelButton:true,
            confirmButtonText:'Sí, eliminar'
        }).then((r)=>{

            if(r.isConfirmed){

                $.post('ajax/lista_espera_delete.php',{id:id},function(resp){

                    if(resp.success){
                        tabla.row(fila).remove().draw();

                        Swal.fire({
                            icon:'success',
                            title:'Eliminado',
                            timer:1200,
                            showConfirmButton:false
                        });
                    }else{
                        Swal.fire('Error',resp.message,'error');
                    }

                },'json');

            }

        });

    });

});
</script>