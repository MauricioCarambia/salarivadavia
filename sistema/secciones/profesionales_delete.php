<?php
require_once __DIR__ . '/../inc/db.php'; // $pdo = instancia PDO
$rand = rand(1000,9999);

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$confirmar = $_GET['confirmar'] ?? '';
$swalGuardado = false;
$swalError = false;

// Obtener profesional existente
$stmt = $pdo->prepare("SELECT nombre, apellido, firma FROM profesionales WHERE Id = ?");
$stmt->execute([$id]);
$profesional = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$profesional && $id > 0) {
    $swalError = "Profesional no encontrado.";
}

// Eliminar profesional si confirma
if ($id > 0 && $confirmar === 'si' && $profesional) {
    try {
        $pdo->beginTransaction();

        // Aquí podrías eliminar datos relacionados si existen (ej. turnos, horarios)
        // $stmtTurnos = $pdo->prepare("DELETE FROM turnos WHERE profesional_id = ?");
        // $stmtTurnos->execute([$id]);

        // Eliminar profesional
        $stmtEliminar = $pdo->prepare("DELETE FROM profesionales WHERE Id = ?");
        $stmtEliminar->execute([$id]);

        // Eliminar firma física si existe
        if (!empty($profesional['firma']) && file_exists($profesional['firma'])) {
            unlink($profesional['firma']);
        }

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
            <h3 class="card-title">Eliminar profesional</h3>
        </div>
        <div class="card-body">
            <?php if (!$swalGuardado && !$swalError && $profesional): ?>
                <p><strong>Profesional:</strong> <?= htmlspecialchars($profesional['nombre'].' '.$profesional['apellido']) ?></p>
                <?php if ($profesional['firma'] && file_exists($profesional['firma'])): ?>
                    <p>Firma registrada:</p>
                    <img src="<?= $profesional['firma'] ?>" alt="Firma" class="img-fluid" style="max-height:100px;">
                <?php endif; ?>
                <div class="alert alert-danger mt-2">
                    &iquest;Confirma eliminar este profesional?<br>
                    Esta acción no puede deshacerse.
                </div>
                <div class="text-right">
                    <a href="?seccion=profesionales_delete&id=<?= $id ?>&confirmar=si&nc=<?= $rand ?>" class="btn btn-danger">Eliminar</a>
                    <a href="?seccion=profesionales&nc=<?= $rand ?>" class="btn btn-secondary">Cancelar</a>
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
            title: '¡Profesional eliminado!',
            text: 'El profesional fue eliminado correctamente.',
            confirmButtonText: 'Aceptar'
        }).then(() => {
            window.location.href = './?seccion=profesionales&nc=<?= $rand ?>';
        });
    <?php elseif ($swalError): ?>
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: '<?= addslashes($swalError) ?>',
            confirmButtonText: 'Aceptar'
        }).then(() => {
            window.location.href = './?seccion=profesionales&nc=<?= $rand ?>';
        });
    <?php endif; ?>
});
</script>
<?php endif; ?>