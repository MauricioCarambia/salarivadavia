<?php
require_once __DIR__ . '/../inc/db.php';

if (empty($_SESSION['login'])) {
    header("Location: login.php");
    exit;
}

if (empty($_SESSION['es_admin']) && !in_array('auditoria', $_SESSION['accesos'] ?? [])) {
    die('<div class="alert alert-danger">No tenés permisos para acceder a esta sección</div>');
}
?>

<div class="card card-info card-outline">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-history"></i> Auditoría de anulaciones</h3>
    </div>

    <div class="card-body table-responsive">
        <table id="tablaAuditoria" class="table table-bordered table-striped table-sm" style="width:100%">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Usuario</th>
                    <th>Acción</th>
                    <th>Detalle</th>
                    <th>Motivo</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<script>
    $(document).ready(function() {
        $('#tablaAuditoria').DataTable({
            serverSide: true,
            processing: true,
            ajax: 'ajax/auditoria_dt.php',
            order: [
                [0, 'desc']
            ],
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'
            },
            columns: [{
                    data: 'fecha'
                },
                {
                    data: 'usuario'
                },
                {
                    data: 'accion'
                },
                {
                    data: 'detalle'
                },
                {
                    data: 'motivo'
                }
            ]
        });
    });
</script>
