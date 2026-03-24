<?php
require_once __DIR__ . '/../inc/db.php';

$confirmar = $_GET['confirmar'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
?>

<!-- Main Wrapper -->
<div id="wrapper">
<div class="normalheader transition animated fadeIn small-header">
    <div class="hpanel">
        <div class="panel-body">
            <h2>Eliminar paciente de lista de espera</h2>
        </div>
    </div>
</div>

<div class="content animate-panel">
<div class="row">
<div class="col-lg-4">
<div class="hpanel">
<div class="panel-body">

<?php

/* ===============================
   ELIMINAR
================================*/
if ($confirmar === 'si' && $id > 0) {

    $stmt = $pdo->prepare("
        DELETE FROM lista_espera
        WHERE Id = :id
        LIMIT 1
    ");

    $stmt->execute([
        ':id' => $id
    ]);

    echo '
    <div class="alert alert-info">
        Se eliminó el paciente correctamente.
    </div>

    <div class="pull-right">
        <a href="?seccion=lista_espera&nc='.$rand.'" class="btn btn-info">
            Aceptar
        </a>
    </div>';
}

/* ===============================
   CONFIRMACION
================================*/
else {

    if ($id <= 0) {

        echo '<div class="alert alert-danger">
                ID inválido.
              </div>';

    } else {

        echo '
        <div class="alert alert-danger">
            ¿Confirma eliminar el paciente?<br>
            Esta acción no puede deshacerse.
        </div>

        <div class="pull-right">
            <a href="?seccion=lista_espera_delete&id='.$id.'&confirmar=si&nc='.$rand.'" 
               class="btn btn-danger">
               Eliminar
            </a>

            <a href="?seccion=lista_espera&nc='.$rand.'" 
               class="btn btn-default">
               Cancelar
            </a>
        </div>';
    }
}
?>

</div>
</div>
</div>
</div>
</div>
</div>