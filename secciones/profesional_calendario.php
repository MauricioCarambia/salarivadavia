<?php
$id = $user_profesional_id;

$consulta = "SELECT profesionales.*, especialidades.especialidad FROM profesionales LEFT JOIN especialidades ON especialidades.Id=profesionales.especialidad_id WHERE profesionales.Id='$id'";
$resultado = mysql_query($consulta,$conexion);
$rArray = mysql_fetch_array($resultado);
$duracion_turnos = $rArray['duracion_turnos'];
$vacaciones_desde = $rArray['vacaciones_desde'];
$vacaciones_hasta = $rArray['vacaciones_hasta'];

$consultaH = "SELECT MIN(hora_inicio) AS apertura, MAX(hora_fin) AS cierre FROM profesionales_horarios WHERE profesional_id='$id'";
$resultadoH = mysql_query($consultaH,$conexion);
$rArrayH = mysql_fetch_array($resultadoH);
$apertura = $rArrayH['apertura'];
$cierre = $rArrayH['cierre'];

$consultaH2 = "SELECT * FROM profesionales_horarios WHERE profesional_id='$id'";
$resultadoH2 = mysql_query($consultaH2,$conexion);
$cantH2 = mysql_num_rows($resultadoH2);
if($cantH2==0){
  $mensaje = '<div class="alert alert-danger">El profesional no tiene asignado ning&uacute;n horario de atenci&oacute;n</div>';
}

$consultaD = "SELECT * FROM profesionales_horarios WHERE profesional_id='$id' GROUP BY dia";
$resultadoD = mysql_query($consultaD,$conexion);
$dias_semana = array('0', '1', '2', '3', '4', '5', '6');
while($rArrayD = mysql_fetch_array($resultadoD)){
  $dias_profesional[] = $rArrayD['dia'];
};
$dias_trabajo = array_diff($dias_semana,$dias_profesional);

?>

<!-- Main Wrapper -->
<div id="wrapper">
<div class="normalheader transition animated fadeIn small-header">
  <div class="hpanel">
    <div class="panel-body">
      <h3>
        Turnos del profesional: <?php echo $rArray['apellido']; ?> <?php echo $rArray['nombre']; ?>
      </h3>
      <h2>
        Especialidad: <?php echo $rArray['especialidad']; ?>
      </h2>
    </div>
  </div>
</div>
<?php echo $mensaje; ?>
<div class="content animate-panel">
  <div class="row">
    <div class="col-lg-12">
      <div class="hpanel">
        <div class="panel-body">
          <div id='calendar'></div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>

    jQuery(document).ready(function() {


        /* initialize the external events
         -----------------------------------------------------------------*/


        jQuery('#external-events div.external-event').each(function() {

            // store data so the calendar knows to render an event upon drop
            jQuery(this).data('event', {
                title: jQuery.trim(jQuery(this).text()), // use the element's text as the event title
                stick: true // maintain when user navigates (see docs on the renderEvent method)
            });

            // make the event draggable using jQuery UI
            jQuery(this).draggable({
                zIndex: 1111999,
                revert: true,      // will cause the event to go back to its
                revertDuration: 0  //  original position after the drag
            });

        });


        /* initialize the calendar
         -----------------------------------------------------------------*/
        var date = new Date();
        var d = date.getDate();
        var m = date.getMonth();
        var y = date.getFullYear();
        jQuery('#calendar').fullCalendar({
            lang: 'es',
            header: {
                left: 'prev,next today',
                center: 'title',
                right: 'month,agendaWeek,agendaDay'
            },
            defaultView: 'agendaWeek', 
            editable: false,
            slotDuration: '00:<?php echo $duracion_turnos; ?>:00',
            droppable: false, // this allows things to be dropped onto the calendar
            drop: function() {
                // is the "remove after drop" checkbox checked?
                if (jQuery('#drop-remove').is(':checked')) {
                    // if so, remove the element from the "Draggable Events" list
                    jQuery(this).remove();
                }
            },
            events: [
<?php
$consulta = "SELECT turnos.*, pacientes.nombre, pacientes.apellido, DATE_ADD(fecha, INTERVAL $duracion_turnos MINUTE) AS fecha_fin FROM turnos LEFT JOIN pacientes ON pacientes.Id=turnos.paciente_id WHERE turnos.profesional_id='$id'";
$resultado = mysql_query($consulta,$conexion);
while($rArray = mysql_fetch_array($resultado)){
  echo "
                  {
                    title: '".$rArray['apellido']." ".$rArray['nombre']."',
                    start: '".$rArray['fecha']."',
                    end: '".$rArray['fecha_fin']."',
                    backgroundColor: '#".(($rArray['sobreturno']=='1')?'b63f4c':'3a87ad')."',
                    borderColor: '#".(($rArray['sobreturno']=='1')?'b63f4c':'3a87ad')."',
                    className: 'turnos'
                },
                ";
}
/*
$consulta = "SELECT * FROM vacaciones WHERE profesional_id='$id'";
$resultado = mysql_query($consulta,$conexion);
while($rArray = mysql_fetch_array($resultado)){
  echo "
                  {
                    title: 'VACACIONES',
                    start: '".$rArray['fecha']." 0:00:00',
                    end: '".$rArray['fecha']." 23:59:59',
                    backgroundColor: '#FF8888',
                    borderColor: '#FF8888',
                    className: 'vacaciones'
                },
                ";
}
*/
$consulta = "SELECT * FROM profesionales_horarios WHERE profesional_id='$id'";
$resultado = mysql_query($consulta,$conexion);
while($rArray = mysql_fetch_array($resultado)){
  echo "
                {
                    start: '".$rArray['hora_inicio']."',
                    end: '".$rArray['hora_fin']."',
                    color: 'white',
                    rendering: 'background',
                    dow: [".$rArray['dia']."],
                    className: 'horasDisponibles'
                },
                ";
}
if($vacaciones_desde != '' && $vacaciones_hasta != ''){
  echo "
                  {
                    title: 'VACACIONES',
                    start: '".$vacaciones_desde." 0:00:00',
                    end: '".$vacaciones_hasta." 23:59:59',
                    backgroundColor: '#FF8888',
                    borderColor: '#FF8888',
                    className: 'vacaciones'
                  },
  ";
}

$consulta = "SELECT * FROM dias_anulados WHERE profesional_id='$id'";
$resultado = mysql_query($consulta,$conexion);
while($rArray = mysql_fetch_array($resultado)){
  echo "
                {
                    title: 'ANULADO',
                    start: '".$rArray['fecha']." 0:00:00',
                    end: '".$rArray['fecha']."  23:59:59',
                    backgroundColor: '#FF8888',
                    borderColor: '#FF8888',
                    className: 'vacaciones'
                },
                ";
}
?>

            ],
            allDaySlot: false,
            hiddenDays: [<?php
            foreach ($dias_trabajo as $value) {
                echo $value.', ';
              }
            ?>],
            minTime: "<?php echo $apertura;?>",
            maxTime: "<?php echo $cierre;?>",
            slotEventOverlap:false,
            eventOverlap: false,
            timeFormat: ''
        });
jQuery('#calendar').on( 'click', '.fc-event', function(e){
  e.preventDefault();
  window.open( jQuery(this).attr('href'), '_parent' );
});
<?php
$dia = $_GET['d'];
$mes = $_GET['me'];
$anio = $_GET['a'];
if(isset($_GET['a'])){
  echo "
    $('#calendar').fullCalendar('gotoDate', '".$anio."-".$mes."-".$dia."');
  ";
}
?>
    });

</script>


<script type = "text/javascript">
jQuery(document).ready(function() {
  if (jQuery(window).width() <= 640) {
  	$('#calendar').fullCalendar('changeView', 'agendaDay');
  }
});
</script>