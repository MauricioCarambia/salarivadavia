<?php
require_once "inc/db.php";

$practicas = $pdo->query("SELECT id,nombre FROM practicas WHERE activo=1")->fetchAll(PDO::FETCH_ASSOC);
$profesionales = $pdo->query("SELECT id,nombre FROM profesionales")->fetchAll(PDO::FETCH_ASSOC);

$repartos = $pdo->query("
    SELECT r.*, p.nombre AS practica, pr.nombre AS profesional
    FROM practicas_reparto r
    INNER JOIN practicas p ON p.id = r.practica_id
    LEFT JOIN profesionales pr ON pr.id = r.profesional_id
")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="card card-outline card-danger shadow-sm">
    <div class="card-header d-flex justify-content-between">
        <h3><i class="fas fa-percentage mr-2"></i> Repartos</h3>

        <button class="btn btn-danger btn-sm" data-toggle="modal" data-target="#modalReparto">
            <i class="fas fa-plus"></i> Nuevo
        </button>
    </div>

    <div class="card-body">

        <table id="tablaReparto" class="table table-bordered table-hover">
            <thead>
                <tr>
                    <th>Práctica</th>
                    <th>Profesional</th>
                    <th>Reglas</th>
                    <th width="120">Acciones</th>
                </tr>
            </thead>
            <tbody>

                <?php foreach($repartos as $r): ?>
                <tr id="fila_<?= $r['id'] ?>">

                    <td><?= $r['practica'] ?></td>
                    <td><?= $r['profesional'] ?? 'General' ?></td>

                    <td>
                        <button class="btn btn-info btn-sm verReglas" data-id="<?= $r['id'] ?>">
                            Ver reglas
                        </button>
                    </td>

                    <td class="text-center">
                        <button class="btn btn-primary btn-sm editar" data-id="<?= $r['id'] ?>">
                            <i class="fas fa-edit"></i>
                        </button>

                        <button class="btn btn-danger btn-sm eliminar" data-id="<?= $r['id'] ?>">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>

                </tr>
                <?php endforeach; ?>

            </tbody>
        </table>

    </div>
</div>
<div class="modal fade" id="modalReparto">
    <div class="modal-dialog modal-lg">
        <form id="formReparto">

            <input type="hidden" name="id" id="reparto_id">

            <div class="modal-content">

                <div class="modal-header bg-danger">
                    <h5>Configurar reparto</h5>
                    <button class="close" data-dismiss="modal">&times;</button>
                </div>

                <div class="modal-body">

                    <select name="practica_id" id="practica_id" class="form-control mb-2" required>
                        <option value="">Práctica</option>
                        <?php foreach($practicas as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= $p['nombre'] ?></option>
                        <?php endforeach; ?>
                    </select>

                    <select name="profesional_id" id="profesional_id" class="form-control mb-3">
                        <option value="">General</option>
                        <?php foreach($profesionales as $pr): ?>
                            <option value="<?= $pr['id'] ?>"><?= $pr['nombre'] ?></option>
                        <?php endforeach; ?>
                    </select>

                    <table class="table table-sm" id="tablaReglas">
                        <thead>
                            <tr>
                                <th>Tipo</th>
                                <th>Destino</th>
                                <th>Valor</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>

                    <button type="button" class="btn btn-success btn-sm" id="addRegla">
                        <i class="fas fa-plus"></i> Agregar regla
                    </button>

                </div>

                <div class="modal-footer">
                    <button class="btn btn-danger">Guardar</button>
                </div>

            </div>

        </form>
    </div>
</div>
<script>
    let tabla;

$(document).ready(function () {

    tabla = $('#tablaReparto').DataTable({
        responsive: true,
        autoWidth: false
    });

});

/* AGREGAR REGLA */
$('#addRegla').click(function(){

    let fila = `
    <tr>
        <td>
            <select class="form-control tipo">
                <option value="porcentaje">%</option>
                <option value="fijo">$</option>
            </select>
        </td>
        <td>
            <select class="form-control destino">
                <option value="profesional">Profesional</option>
                <option value="clinica">Clínica</option>
                <option value="farmacia">Farmacia</option>
                <option value="patologia">Patología</option>
            </select>
        </td>
        <td>
            <input type="number" class="form-control valor">
        </td>
        <td>
            <button type="button" class="btn btn-danger btn-sm eliminarRegla">
                <i class="fas fa-trash"></i>
            </button>
        </td>
    </tr>`;

    $('#tablaReglas tbody').append(fila);

});

/* ELIMINAR REGLA */
$(document).on('click', '.eliminarRegla', function(){
    $(this).closest('tr').remove();
});
/* NUEVO REPARTO */
$('[data-target="#modalReparto"]').click(function(){

    // Limpiar formulario
    $('#reparto_id').val('');
    $('#practica_id').val('');
    $('#profesional_id').val('');

    // Limpiar reglas
    $('#tablaReglas tbody').html('');

});
/* GUARDAR */
$('#formReparto').submit(function(e){
    e.preventDefault();

    let reglas = [];

    $('#tablaReglas tbody tr').each(function(){

        reglas.push({
            tipo: $(this).find('.tipo').val(),
            destino: $(this).find('.destino').val(),
            valor: $(this).find('.valor').val()
        });

    });

    let data = {
        id: $('#reparto_id').val(),
        practica_id: $('#practica_id').val(),
        profesional_id: $('#profesional_id').val(),
        reglas: reglas
    };

    $.ajax({
        url: 'ajax/practicas_reparto_guardar.php',
        method: 'POST',
        data: JSON.stringify(data),
        contentType: 'application/json',
        success: function(res){

            if(res.success){
                Swal.fire('Guardado','','success').then(()=>location.reload());
            }

        }
    });

});
/* EDITAR */
$(document).on('click', '.editar', function(){

    let id = $(this).data('id');

    $('#reparto_id').val(id);

    // Traer datos por AJAX
    $.get('ajax/practicas_reparto_get.php', {id:id}, function(res){

        if(res.success){

            $('#practica_id').val(res.data.practica_id);
            $('#profesional_id').val(res.data.profesional_id);

            // Limpiar reglas
            $('#tablaReglas tbody').html('');

            // Cargar reglas
            res.reglas.forEach(r => {

                let fila = `
                <tr>
                    <td>
                        <select class="form-control tipo">
                            <option value="porcentaje" ${r.tipo=='porcentaje'?'selected':''}>%</option>
                            <option value="fijo" ${r.tipo=='fijo'?'selected':''}>$</option>
                        </select>
                    </td>
                    <td>
                        <select class="form-control destino">
                            <option value="profesional" ${r.destino=='profesional'?'selected':''}>Profesional</option>
                            <option value="clinica" ${r.destino=='clinica'?'selected':''}>Clínica</option>
                            <option value="farmacia" ${r.destino=='farmacia'?'selected':''}>Farmacia</option>
                            <option value="patologia" ${r.destino=='patologia'?'selected':''}>Patología</option>
                        </select>
                    </td>
                    <td>
                        <input type="number" class="form-control valor" value="${r.valor}">
                    </td>
                    <td>
                        <button type="button" class="btn btn-danger btn-sm eliminarRegla">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>`;

                $('#tablaReglas tbody').append(fila);

            });

            $('#modalReparto').modal('show');

        }

    }, 'json');

});
/* ELIMINAR */
$(document).on('click', '.eliminar', function(){

    let id = $(this).data('id');

    Swal.fire({
        title:'¿Eliminar?',
        icon:'warning',
        showCancelButton:true
    }).then(r=>{

        if(r.isConfirmed){

            $.post('ajax/practicas_reparto_eliminar.php',{id},function(res){

                if(res.success){
                    tabla.row($('#fila_'+id)).remove().draw();
                }

            },'json');

        }

    });

});
</script>