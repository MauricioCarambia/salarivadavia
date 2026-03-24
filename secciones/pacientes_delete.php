<?php
require_once __DIR__ . '/../inc/db.php'; // $pdo = instancia PDO
$rand = rand(1000,9999);

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$confirmar = $_GET['confirmar'] ?? '';
$swalGuardado = false;
$swalError = false;

if ($id > 0 && $confirmar === 'si') {
    try {
        $pdo->beginTransaction();

        // Eliminamos las historias clínicas asociadas
        $stmtHC = $pdo->prepare("DELETE FROM historias_clinicas WHERE paciente_id = :id");
        $stmtHC->execute([':id' => $id]);

        // Eliminamos el paciente
        $stmtPaciente = $pdo->prepare("DELETE FROM pacientes WHERE Id = :id");
        $stmtPaciente->execute([':id' => $id]);

        $pdo->commit();
        $swalGuardado = true;

    } catch (Exception $e) {
        $pdo->rollBack();
        $swalError = "Error al eliminar: " . $e->getMessage();
    }
}
?>

<div class="content">
    <div class="card card-danger card-outline">
        <div class="card-header">
            <h3 class="card-title">Eliminar paciente</h3>
        </div>
        <div class="card-body">
            <?php if (!$swalGuardado && !$swalError): ?>
                <div class="alert alert-danger">
                    &iquest;Confirma eliminar el paciente y su Historia Clínica?<br>
                    Esta acción no puede deshacerse.
                </div>
                <div class="text-right">
                    <a href="?seccion=pacientes_delete&id=<?= $id ?>&confirmar=si&nc=<?= $rand ?>" class="btn btn-danger">Eliminar</a>
                    <a href="?seccion=pacientes&nc=<?= $rand ?>" class="btn btn-secondary">Cancelar</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($swalGuardado || $swalError): ?>
<script>
document.addEventListener("DOMContentLoaded", function() {
    <?php if ($swalGuardado): ?>
        Swal.fire({
            icon: 'success',
            title: '¡Paciente eliminado!',
            text: 'El paciente y su historia clínica fueron eliminados correctamente.',
            confirmButtonText: 'Aceptar'
        }).then(() => {
            window.location.href = './?seccion=pacientes&nc=<?= $rand ?>';
        });
    <?php elseif ($swalError): ?>
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: '<?= addslashes($swalError) ?>',
            confirmButtonText: 'Aceptar'
        }).then(() => {
            window.location.href = './?seccion=pacientes&nc=<?= $rand ?>';
        });
    <?php endif; ?>
});
</script>
<?php endif; ?>