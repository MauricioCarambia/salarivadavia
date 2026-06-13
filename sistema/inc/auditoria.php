<?php

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
