<?php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/csrf.php';

$confirmar = $_GET['confirmar'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$rand = $_GET['nc'] ?? rand();
$tokenValido = hash_equals($_SESSION['csrf_token'] ?? '', $_GET['csrf_token'] ?? '');
?>
<!-- Main Wrapper -->
<div id="wrapper">
    <div class="normalheader transition animated fadeIn small-header">
        <div class="hpanel">
            <div class="panel-body">
                <h2>
                    Eliminar registro
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
                        if ($confirmar === 'si' && $tokenValido && $id > 0) {

                            $stmt = $pdo->prepare("DELETE FROM historias_clinicas WHERE Id = :id");
                            $stmt->execute([':id' => $id]);

                            echo '
                            <div class="alert alert-info">Se eliminó el registro.</div>
                            <div class="pull-right">
                            <a href="?seccion=historia_pacientes&nc='.$rand.'" class="btn btn-info">Aceptar</a>
                            </div>
                            ';
                        } else {

                            if ($id <= 0) {
                                echo '<div class="alert alert-danger">ID inválido.</div>';
                            } else {
                                echo '
                                <div class="alert alert-danger">¿Confirma eliminar el registro?<br>
                                Esta acción no puede deshacerse.<br>
                                </div>
                                <div class="pull-right">
                                <a href="?seccion=historia_clinica_delete&id='.$id.'&confirmar=si&csrf_token='.urlencode(csrf_token()).'&nc='.$rand.'" class="btn btn-info">Eliminar</a>
                                <a href="?seccion=historia_pacientes&nc='.$rand.'" class="btn btn-info">Cancelar</a>
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
