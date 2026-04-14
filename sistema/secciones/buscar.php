<?php
$busqueda = $_GET['busqueda'];
/*
$consulta = "SELECT * FROM pacientes WHERE (nombre LIKE '%$busqueda%' OR apellido LIKE '%$busqueda%' OR documento LIKE '%$busqueda%' OR nro_afiliado LIKE '%$busqueda%' OR email LIKE '%$busqueda%')";
$resultado = mysql_query($consulta,$conexion);
$cant_pacientes = mysql_num_rows($resultado);
*/
?>
<!-- Main Wrapper -->
<div id="wrapper">
<div class="normalheader transition animated fadeIn small-header">
  <div class="hpanel">
    <div class="panel-body">
      <h2>
        B&uacute;squeda
      </h2>
    </div>
  </div>
</div>
<div class="row">
    <div class="col-lg-12">
      <div class="hpanel">
        <div class="panel-body text-center">
          <form action="./" method="get">
            <input type="hidden" name="seccion" value="buscar">
            <input type="hidden" name="nc" value="<?php echo $rand;?>">
            <div class="input-group">
              <input class="form-control" placeholder="Buscar..." name="busqueda" value="<?php echo $busqueda;?>" required>
              <span class="input-group-btn">
              <button class="btn btn-default" type="submit"><i class="fa fa-search"></i></button>
              </span>
            </div>
          </form>
        </div>
      </div>
    </div>
</div>
<div class="content animate-panel">
  <div class="row">
    <div class="col-lg-6">
      <div class="hpanel">
        <div class="panel-heading">
          Pacientes
        </div>
        <div class="panel-body">
          <table class="table table-striped table-bordered table-hover dataTables-example dataTable" id="DataTables_Table_0" aria-describedby="DataTables_Table_0_info" role="grid">
          <thead>
          <tr role="row">
            <th>Apellido</th>
            <th>Nombre</th>
            <th>Documento</th>
            <th>Socio N&deg;</th>
            <th>Celular</th>
            <th></th>
          </tr>
          </thead>
          <tbody>
            <?php
            $consulta = "SELECT * FROM pacientes WHERE (nombre LIKE '%$busqueda%' OR apellido LIKE '%$busqueda%' OR documento LIKE '%$busqueda%' OR nro_afiliado LIKE '%$busqueda%' OR email LIKE '%$busqueda%')";
            $resultado = mysql_query($consulta,$conexion);
            while($rArray = mysql_fetch_array($resultado)) {
              $pacienteId = $rArray['Id'];
              $consultaA = "SELECT * FROM pagos_afiliados WHERE paciente_id='$pacienteId' AND (fecha_correspondiente BETWEEN DATE_FORMAT(NOW() ,'%Y-%m-01') AND '2030-01-01') ORDER BY fecha_correspondiente DESC LIMIT 1";
              $resultadoA = mysql_query($consultaA,$conexion);
              $cant = mysql_num_rows($resultadoA);
              $aldia = '';
              if($cant>0){$aldia='si';}
              echo '
                <tr>
                  <td>'.$rArray['apellido'].'</td>
                  <td>'.$rArray['nombre'].'</td>
                  <td>'.$rArray['tipo_documento'].': '.$rArray['documento'].'</td>
                  <td>'.$rArray['nro_afiliado'].' '.(($rArray['nro_afiliado']!='')?'<div class="pull-right">'.(($aldia=='si')?'<i class="fa fa-check fa-2x verde"></i>':'<i class="fa fa-times fa-2x rojo"></i>').'</div>':'').'</td>
                  <td>'.$rArray['celular'].'</td>
                  <td>
                  <a href="./?seccion=pacientes_edit&id='.$rArray['Id'].'&nc='.$rand.'" class="btn btn-success"><i class="fa fa-pencil"></i></a>
                  <a href="./?seccion=pacientes_delete&id='.$rArray['Id'].'&nc='.$rand.'" class="btn btn-danger"><i class="fa fa-trash-o"></i></a>
                  <a href="./?seccion=paciente_turnos_ver&id='.$rArray['Id'].'&nc='.$rand.'" class="btn btn-warning"><i class="fa fa-question"></i></a>
                  </td>
                </tr>
              ';
            }
            ?>
          </tbody>
          </table>
        </div>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="hpanel">
        <div class="panel-heading">
          Profesionales
        </div>
        <div class="panel-body">
          <table class="table table-striped table-bordered table-hover dataTables-example dataTable" id="DataTables_Table_0" aria-describedby="DataTables_Table_0_info" role="grid">
          <thead>
          <tr role="row">
            <th>Apellido</th>
            <th>Nombre</th>
            <th>Especialidad</th>
            <th>Celular</th>
            <th></th>
          </tr>
          </thead>
          <tbody>
            <?php
            $consulta = "SELECT profesionales.*, especialidades.especialidad FROM profesionales LEFT JOIN especialidades ON especialidades.Id=profesionales.especialidad_id WHERE (nombre LIKE '%$busqueda%' OR apellido LIKE '%$busqueda%' OR documento LIKE '%$busqueda%' OR email LIKE '%$busqueda%')";
            $resultado = mysql_query($consulta,$conexion);
            while($rArray = mysql_fetch_array($resultado)) {
            echo '
              <tr>
                <td>'.$rArray['apellido'].'</td>
                <td>'.$rArray['nombre'].'</td>
                <td>'.$rArray['especialidad'].'</td>
                <td>'.$rArray['celular'].'</td>
                <td>
                <a href="./?seccion=profesionales_edit&id='.$rArray['Id'].'&nc='.$rand.'" class="btn btn-info"><i class="fa fa-pencil"></i></a>
                <a href="./?seccion=profesionales_delete&id='.$rArray['Id'].'&nc='.$rand.'" class="btn btn-danger"><i class="fa fa-trash-o"></i></a>
                </td>
              </tr>
            ';
            }
            ?>
          </tbody>
          </table>
        </div>
      </div>
    </div>
    <div class="col-md-12">
      <a href="./?seccion=home&nc=<?php echo $rand?>" class="btn btn-info pull-right">Volver</a>
    </div>
  </div>
</div>

<!-- Page-Level Scripts -->
    <script>
        $(document).ready(function(){
            $('.dataTables-example').DataTable({
                "iDisplayLength": 10,
                "aLengthMenu": [[10, 25, 50, 100, 1000], [10, 25, 50, 100, 1000]],
                dom: '<"html5buttons"B>lTfgitp',
                buttons: [
                    {extend: 'excel', title: 'busqueda'},
                    {extend: 'pdf', title: 'busqueda'},
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

    </script>