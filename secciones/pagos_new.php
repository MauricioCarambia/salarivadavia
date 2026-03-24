<?php
$id = $_GET['id'];

if(isset($_POST['monto'])){
  $monto = $_POST['monto'];
  $fecha = $_POST['fecha'];
  $sql = "INSERT INTO pagos_profesionales (profesional_id, monto, fecha) VALUES ('$id', '$monto', '$fecha')";
  $rsql = mysql_query($sql,$conexion);
  
  $mensaje = '<div class="alert alert-info">Pago cargado satisfactoriamente.</div>';
}

$consulta = "SELECT * FROM profesionales WHERE Id='$id'";
$resultado = mysql_query($consulta,$conexion);
$rArray = mysql_fetch_array($resultado);
$nombre = $rArray['apellido'].' '.$rArray['nombre'];
$porcentaje = $rArray['porcentaje'];
$celular = $rArray['celular'];
$fijo = $rArray['fijo'];

$consultaP = "SELECT SUM(monto) AS total_profesional FROM pagos_profesionales WHERE profesional_id='$id'";
$resultadoP = mysql_query($consultaP,$conexion);
$rArrayP = mysql_fetch_array($resultadoP);

$consultaP2 = "SELECT SUM(pago) AS total_recibido FROM turnos WHERE profesional_id='$id'";
$resultadoP2 = mysql_query($consultaP2,$conexion);
$rArrayP2 = mysql_fetch_array($resultadoP2);

$diferencia = ($rArrayP2['total_recibido']*$rArray['porcentaje']/100)-$rArrayP['total_profesional'];

?>
<!-- Main Wrapper -->
<div id="wrapper">
<div class="normalheader transition animated fadeIn small-header">
  <div class="hpanel">
    <div class="panel-body">
      <h2>
        Pagos
      </h2>
        Nombre: <b><?php echo $nombre;?></b><br>
        Porcentaje: <b><?php echo $porcentaje;?>%</b><br>
        Saldo: <b>$<?php echo $diferencia;?></b><br>
        Celular: <b><?php echo $celular;?></b><br>
        Tel&eacute;fono fijo: <b><?php echo $fijo;?></b>
    </div>
  </div>
</div>
<?php echo $mensaje; ?>
<div class="content animate-panel">
  <div class="row">
    <div class="col-md-12 col-lg-12">
      <div class="hpanel">
        <div class="panel-heading hbuilt">
          <h3>Nuevo pago (Saldo: $<?php echo $diferencia;?>)</h3>
        </div>
        <div class="panel-body">
          <form action="./?seccion=pagos_new&id=<?php echo $_GET['id'];?>nc=<?php echo $rand;?>" method="post" class="form-inline">
            <label>Monto:</label>
            <div class="input-group">
              <span class="input-group-addon">$</span>
              <input type="number" min="0" step="any" class="form-control" name="monto" required>
            </div>
            <br>
            <div class="m-t">
              <label>Fecha:</label>
              <div class="input-group">
                <span class="input-group-addon"><i class="fa fa-calendar" aria-hidden="true"></i></span>
                <input type="date" class="form-control" name="fecha" value="<?php echo date('Y-m-d');?>" required>
              </div>
            </div>
            <button type="submit" class="btn btn-success m-t pull-right">Guardar</button>
          </form>
        </div>
      </div>
    </div>
    <div class="col-md-12">
      <a href="./?seccion=pagos&nc=<?php echo $rand?>" class="btn btn-info pull-right">Volver</a>
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