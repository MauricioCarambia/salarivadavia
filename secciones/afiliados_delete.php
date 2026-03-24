<!-- Main Wrapper -->
<div id="wrapper">
<div class="normalheader transition animated fadeIn small-header">
  <div class="hpanel">
    <div class="panel-body">
      <h2>
        Eliminar pago de afiliaci&oacute;n
      </h2>
    </div>
  </div>
</div>
<div class="content animate-panel">
  <div class="row">
    <div class="col-lg-4">
      <div class="hpanel">
        <div class="panel-body">
        <?php

          $confirmar = $_GET['confirmar'] ?? '';
          $id  = $_GET['id']  ?? 0;
          $pid = $_GET['pid'] ?? 0;

          if($confirmar == 'si'){

            $stmt = $pdo->prepare("
                DELETE FROM pagos_afiliados
                WHERE Id = :pid
            ");

            $stmt->execute([
                ':pid' => $pid
            ]);

            echo '
            <div class="alert alert-info">Se elimin&oacute; el pago.</div>
            <div class="pull-right">
              <a href="?seccion=socios_historial&id='.$id.'&nc='.$rand.'" class="btn btn-info">Aceptar</a>
            </div>
            ';
          }
          else{

            echo '
            <div class="alert alert-danger">&iquest;Confirma eliminar el pago?.<br>
              Esta acci&oacute;n no puede deshacerse.<br>
            </div>
            <div class="pull-right">
              <a href="?seccion=afiliados_delete&pid='.$pid.'&id='.$id.'&confirmar=si&nc='.$rand.'" class="btn btn-info">Eliminar</a>
              <a href="?seccion=socios_historial&id='.$id.'&nc='.$rand.'" class="btn btn-info">Cancelar</a>
            </div>';
          }

        ?>
        </div>
      </div>
    </div>
  </div>
</div>