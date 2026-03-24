<?php
$id = $_GET['id'];

if(isset($_POST['guardar'])){
  $obra_social = $_POST['obra_social'];
  
  $sql = "UPDATE obras_sociales SET obra_social='$obra_social' WHERE Id='$id'";
  $rsql = mysql_query($sql,$conexion);
  $last_id = mysql_insert_id();

  $mensaje = '<div class="alert alert-info">Obra social editada satisfactoriamente.</div>';

}

$consulta = "SELECT * FROM obras_sociales WHERE Id='$id'";
$resultado = mysql_query($consulta,$conexion);
$rArray = mysql_fetch_array($resultado);
?>
<!-- Main Wrapper -->
<div id="wrapper">
<div class="normalheader transition animated fadeIn small-header">
  <div class="hpanel">
    <div class="panel-body">
      <h2>
        Editar obra social
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
          <form class="form-horizontal" action="./?seccion=obras_edit&id=<?php echo $id;?>&nc=<?php echo $rand;?>" method="POST">
            <div class="form-group">
              <label class="col-sm-2 control-label">Nombre</label>
              <div class="col-sm-10">
                <input type="text" class="form-control" name="obra_social" value="<?php echo $rArray['obra_social'];?>" required>
              </div>
            </div>
            <div class="form-group">
              <div class="col-sm-12">
                <div class="pull-right">
                  <a href="./?seccion=obras&nc=<?php echo $rand; ?>" class="btn btn-info">Volver</a>
                  <input type="submit" class="btn btn-info" name="guardar" value="Guardar">
                </div>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>