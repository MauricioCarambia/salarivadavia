<?php
$id = $_GET['id'];

$desde = $_POST['desde'];
$hasta = $_POST['hasta'];

if($desde==''){$desde = date('Y-m-d');}
if($hasta==''){$hasta = date('Y-m-d');}

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
        Pagos <a href="./?seccion=pagos_new&id=<?php echo $_GET['id'];?>&nc=<?php echo $rand?>" class="btn btn-info"><i class="fa fa-plus"></i></a>
      </h2>
        Nombre: <b><?php echo $nombre;?></b><br>
        Porcentaje: <b><?php echo $porcentaje;?>%</b><br>
        Saldo: <b>$<?php echo $diferencia;?></b><br>
        Celular: <b><?php echo $celular;?></b><br>
        Tel&eacute;fono fijo: <b><?php echo $fijo;?></b>
    </div>
  </div>
</div>
<div class="content animate-panel">
  <div class="row">
    <div class="col-md-12 col-lg-12">
      <div class="hpanel">
        <div class="panel-heading hbuilt">
          <h3>Historial de turnos</h3>
          <div class="m-t">
            <form action="./?seccion=pagos_fechas&id=<?php echo $_GET['id'];?>nc=<?php echo $rand;?>" method="post" class="form-inline">
              <div class="form-group">
                <label>Desde:</label>
                <input type="date" class="form-control" name="desde" value="<?php echo strftime('%Y-%m-%d', strtotime($desde));?>" required>
              </div>
              <div class="form-group">
                <label>Hasta:</label>
                <input type="date" class="form-control" name="hasta" value="<?php echo strftime('%Y-%m-%d', strtotime($hasta));?>" required>
              </div>
              <button type="submit" class="btn btn-success">Buscar por rango de fechas</button>
            </form>
          </div>
        </div>
        <div class="panel-body">
          <table class="table table-striped table-bordered table-hover dataTables-example dataTable" id="DataTables_Table_0" aria-describedby="DataTables_Table_0_info" role="grid">
          <thead>
          <tr role="row">
            <th>Fecha</th>
            <th>Monto</th>
            <th>Paciente</th>
          </tr>
          </thead>
          <tbody>
            <?php
            $consultaT = "SELECT SUM(pago) AS total FROM turnos WHERE (fecha BETWEEN '$desde' AND '$hasta' + INTERVAL 1 DAY) AND profesional_id='$id' AND pago > 0";
            $resultadoT = mysql_query($consultaT,$conexion);
            $rArrayT = mysql_fetch_array($resultadoT);
            $total = $rArrayT['total'];
            $consulta = "SELECT turnos.*, pacientes.nombre, pacientes.apellido FROM turnos LEFT JOIN pacientes ON pacientes.Id=turnos.paciente_id WHERE (fecha BETWEEN '$desde' AND '$hasta' + INTERVAL 1 DAY) AND profesional_id='$id' AND pago > 0";
            $resultado = mysql_query($consulta,$conexion);
            
            while($rArray = mysql_fetch_array($resultado)) {
            echo '
              <tr>
                <td>'.strftime('%d/%m/%Y', strtotime($rArray['fecha'])).'</td>
                <td>$'.$rArray['pago'].'</td>
                <td>'.$rArray['apellido'].' '.$rArray['nombre'].'</td>
              </tr>
            ';
            }
            ?>
          </tbody>
          </table>
          <h3 class="text-center">Total: $<?php echo $total;?></h3>
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