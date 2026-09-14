<?php

const AUDITORIA_ACCION_LABELS = [
    'cobro_anulado'            => 'Comprobante anulado',
    'pago_afiliado_eliminado'  => 'Cuota de socio eliminada',
    'acceso_bloqueado'         => 'Acceso bloqueado por IP',
    'acceso_concedido'         => 'Inicio de sesión',
    'autorizacion_ip_otorgada' => 'Autorización de acceso remoto otorgada',
    'autorizacion_ip_revocada' => 'Autorización de acceso remoto revocada',
];

function auditoriaAccionLabel(string $accion): string
{
    return AUDITORIA_ACCION_LABELS[$accion] ?? $accion;
}

function registrarAuditoria(PDO $pdo, string $accion, ?string $detalle = null, ?string $motivo = null): void
{
    $stmt = $pdo->prepare("
        INSERT INTO auditoria (usuario_id, usuario_nombre, accion, detalle, motivo, ip)
        VALUES (:usuario_id, :usuario_nombre, :accion, :detalle, :motivo, :ip)
    ");

    $stmt->execute([
        ':usuario_id'     => $_SESSION['user_id'] ?? null,
        ':usuario_nombre' => $_SESSION['nombre_completo'] ?? null,
        ':accion'         => $accion,
        ':detalle'        => $detalle,
        ':motivo'         => $motivo,
        ':ip'             => $_SERVER['REMOTE_ADDR'] ?? null,
    ]);
}
