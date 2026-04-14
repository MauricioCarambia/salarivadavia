<?php

$id  = $_GET['id']  ?? 0;
$pid = $_GET['pid'] ?? 0;

$mensaje = "";

/* =====================================
   ACTUALIZAR PAGO
===================================== */

if(isset($_POST['monto'])){

    $monto = $_POST['monto'];
    $fecha = $_POST['fecha'].'-01';

    $stmt = $conexion->prepare("
        UPDATE pagos_afiliados 
        SET monto = :monto,
            fecha_correspondiente = :fecha
        WHERE Id = :pid
    ");

    $stmt->execute([
        ':monto' => $monto,
        ':fecha' => $fecha,
        ':pid'   => $pid
    ]);

    $mensaje = '<div class="alert alert-info">
                    Pago editado satisfactoriamente.
                </div>';
}


/* =====================================
   ULTIMO MES PAGADO
===================================== */

$stmt = $conexion->prepare("
    SELECT 
        MONTH(fecha_correspondiente) AS mes,
        YEAR(fecha_correspondiente) AS anio
    FROM pagos_afiliados
    WHERE paciente_id = :id
    ORDER BY fecha_correspondiente DESC
    LIMIT 1
");

$stmt->execute([':id'=>$id]);
$rArray = $stmt->fetch(PDO::FETCH_ASSOC);


/* =====================================
   PAGO ACTUAL
===================================== */

$stmt2 = $conexion->prepare("
    SELECT *,
           MONTH(fecha_correspondiente) AS mes,
           YEAR(fecha_correspondiente) AS anio
    FROM pagos_afiliados
    WHERE Id = :pid
");

$stmt2->execute([':pid'=>$pid]);
$rArray2 = $stmt2->fetch(PDO::FETCH_ASSOC);

?>
<!-- Main Wrapper -->
<div id="wrapper">
<div class="normalheader transition animated fadeIn small-header">
  <div class="hpanel">
    <div class="panel-body">
      <h2>
        Editar pagos de afiliaci&oacute;n
      </h2>
    </div>
  </div>
</div>
<?php echo $mensaje; ?>
<div class="content animate-panel">
  <div class="row">
    <div class="col-md-12 col-lg-6">
      <div class="hpanel">
        <div class="panel-heading hbuilt">
          <h3>&Uacute;ltimo mes: <?php echo $meses[$rArray['mes']-1].' de '.$rArray['anio'];?></h3>
        </div>
        <div class="panel-body">
          <form action="./?seccion=afiliados_edit&pid=<?php echo $_GET['pid'];?>&id=<?php echo $_GET['id'];?>&nc=<?php echo $rand;?>" method="post" class="form-inline">
            <label>Monto:</label>
            <div class="input-group">
              <span class="input-group-addon">$</span>
              <input type="number" min="0" step="any" class="form-control" name="monto" value="<?php echo $rArray2['monto'];?>" required>
            </div>
            <br>
            <div class="m-t">
              <label>Correspondiente al mes:</label>
              <div class="input-group">
                <span class="input-group-addon"><i class="fa fa-calendar" aria-hidden="true"></i></span>
                <input type="month" class="form-control" name="fecha" value="<?php echo $rArray2['anio'];?>-<?php echo str_pad($rArray2['mes'], 2, '0', STR_PAD_LEFT);?>" required>
              </div>
            </div>
            <button type="submit" class="btn btn-success m-t pull-right">Guardar</button>
          </form>
        </div>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="hpanel">
        <div class="panel-heading hbuilt">
          Datos del paciente:
          <div class="clearfix"></div>
        </div>
        <div class="panel-body">
          <?php

$stmtPaciente = $conexion->prepare("
    SELECT 
        obras_sociales.obra_social,
        pacientes.*
    FROM pacientes
    LEFT JOIN obras_sociales 
        ON obras_sociales.Id = pacientes.obra_social_id
    WHERE pacientes.Id = :id
");

$stmtPaciente->execute([':id'=>$id]);
$paciente = $stmtPaciente->fetch(PDO::FETCH_ASSOC);

echo '
<label>Apellido:</label> '.$paciente['apellido'].'<br>
<label>Nombre:</label> '.$paciente['nombre'].'<br>
<label>Domicilio:</label> '.$paciente['domicilio'].'<br>
<label>Provincia:</label> '.$paciente['provincia'].'<br>
<label>Localidad:</label> '.$paciente['localidad'].'<br>
<label>Celular:</label> '.$paciente['celular'].'<br>
<label>Fijo:</label> '.$paciente['fijo'].'<br>
<label>Email:</label> '.$paciente['email'].'<br>
<label>Documento:</label> '.$paciente['tipo_documento'].': '.$paciente['documento'].'<br>
<label>Fecha de nacimiento:</label> '.$paciente['nacimiento'].'<br>
<label>N° socio:</label> '.$paciente['nro_afiliado'].'<br>
<label>Obra social:</label> '.$paciente['obra_social'].'<br>
<label>Plan obra social:</label> '.$paciente['obra_social_plan'].'<br>
<label>Número obra social:</label> '.$paciente['obra_social_numero'].'<br>
<label>Sexo:</label> '.$paciente['sexo'];
?>
        </div>
      </div>
    </div>
    <div class="col-md-12">
      <div class="pull-right">
        <a href="./?seccion=socios_historial&id=<?php echo $id; ?>&nc=<?php echo $rand; ?>" class="btn btn-info">Volver</a>
      </div>
    </div>
  </div>
</div>

<!-- Page-Level Scripts -->
<script>
$(document).ready(function(){
    $('.dataTables-example').DataTable({
        columnDefs: [
           { type: 'date-eu', targets: 0 }
         ],
        "aaSorting": [[0, "desc"]],
        "iDisplayLength": 100,
        "aLengthMenu": [[10, 25, 50, 100, 1000], [10, 25, 50, 100, 1000]],
        dom: '<"html5buttons"B>lTfgitp',
        buttons: [
            {extend: 'excel', title: 'pagos'},
            {extend: 'pdf', title: 'pagos'},
            {extend: 'print',
             text: 'IMPRIMIR',
             customize: function (win){
                    $(win.document.body).addClass('white-bg');
                    $(win.document.body).css('font-size', '10px');

                    $(win.document.body).find('table')
                            .addClass('compact')
                            .css('font-size', 'inherit');
            }
            }
        ],
        "language": {
      "sProcessing":     "Procesando...",
      "sLengthMenu":     "Mostrar _MENU_ registros",
      "sZeroRecords":    "No se encontraron resultados",
      "sEmptyTable":     "Ning&uacute;n dato disponible en esta tabla",
      "sInfo":           "Mostrando registros del _START_ al _END_ de un total de _TOTAL_ registros",
      "sInfoEmpty":      "Mostrando registros del 0 al 0 de un total de 0 registros",
      "sInfoFiltered":   "(filtrado de un total de _MAX_ registros)",
      "sInfoPostFix":    "",
      "sSearch":         "Buscar:",
      "sUrl":            "",
      "sInfoThousands":  ",",
      "sLoadingRecords": "Cargando...",
      "oPaginate": {
          "sFirst":    "Primero",
          "sLast":     "&Uacute;ltimo",
          "sNext":     "Siguiente",
          "sPrevious": "Anterior"
      },
      "oAria": {
          "sSortAscending":  ": Activar para ordenar la columna de manera ascendente",
          "sSortDescending": ": Activar para ordenar la columna de manera descendente"
      }
  }

    });

    /* Init DataTables */
    var oTable = $('#editable').DataTable();

});

jQuery.extend( jQuery.fn.dataTableExt.oSort, {
    "date-eu-pre": function ( date ) {
        date = date.replace(" ", "");
         
        if ( ! date ) {
            return 0;
        }
 
        var year;
        var eu_date = date.split(/[\.\-\/]/);
 
        /*year (optional)*/
        if ( eu_date[2] ) {
            year = eu_date[2];
        }
        else {
            year = 0;
        }
 
        /*month*/
        var month = eu_date[1];
        if ( month.length == 1 ) {
            month = 0+month;
        }
 
        /*day*/
        var day = eu_date[0];
        if ( day.length == 1 ) {
            day = 0+day;
        }
 
        return (year + month + day) * 1;
    },
 
    "date-eu-asc": function ( a, b ) {
        return ((a < b) ? -1 : ((a > b) ? 1 : 0));
    },
 
    "date-eu-desc": function ( a, b ) {
        return ((a < b) ? 1 : ((a > b) ? -1 : 0));
    }
} );
</script>