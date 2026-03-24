<?php
require_once __DIR__ . '/../inc/db.php';

$id         = $_GET['id'] ?? null;
$confirmar  = $_GET['confirmar'] ?? '';
$mensaje    = '';

if (!$id) {
    $mensaje = '
        <div class="alert alert-danger">
            ID inválido.
        </div>';
}

/* ==========================
   ELIMINAR REGISTRO
========================== */
if ($confirmar === 'si' && $id) {

    try {

        $sql = $pdo->prepare("
            DELETE FROM cardiologia_sur
            WHERE Id = :id
        ");

        $sql->execute([
            ':id' => $id
        ]);

        $mensaje = '
            <div class="alert alert-success">
                Turno eliminado correctamente.
            </div>
            <div class="pull-right">
                <a href="?seccion=estudios&nc=' . $rand . '" class="btn btn-info">
                    Aceptar
                </a>
            </div>';

    } catch (PDOException $e) {

        $mensaje = '
            <div class="alert alert-danger">
                Error al eliminar el turno.
            </div>';
    }
}
?>

<!-- Main Wrapper -->
<div id="wrapper">
    <div class="normalheader transition animated fadeIn small-header">
        <div class="hpanel">
            <div class="panel-body">
                <h2>Eliminar Turno</h2>
            </div>
        </div>
    </div>

    <div class="content animate-panel">
        <div class="row">
            <div class="col-lg-4">
                <div class="hpanel">
                    <div class="panel-body">

                        <?php if ($confirmar !== 'si' && $id): ?>

                            <div class="alert alert-danger">
                                ¿Confirma eliminar el turno?<br>
                                Esta acción no puede deshacerse.
                            </div>

                            <div class="pull-right">
                                <a href="?seccion=cardiologia_sur_delete&id=<?= $id ?>&confirmar=si&nc=<?= $rand ?>"
                                   class="btn btn-danger">
                                    Eliminar
                                </a>

                                <a href="?seccion=estudios_cardiologia&nc=<?= $rand ?>"
                                   class="btn btn-default">
                                    Cancelar
                                </a>
                            </div>

                        <?php endif; ?>

                        <?= $mensaje ?>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>