<?php
/**
 * Tareas de limpieza que se ejecutan de forma oportunista en cada request,
 * throttladas a 1 vez por día mediante la tabla mantenimiento_tareas.
 * No requiere cron: alcanza con que la app reciba tráfico normal.
 */

function ejecutarTareaDiaria(PDO $pdo, string $tarea, callable $accion): void
{
    $hoy = date('Y-m-d');

    $stmt = $pdo->prepare("SELECT ultima_ejecucion FROM mantenimiento_tareas WHERE tarea = :tarea");
    $stmt->execute([':tarea' => $tarea]);
    $ultima = $stmt->fetchColumn();

    if ($ultima === $hoy) {
        return; // ya se ejecutó hoy
    }

    $accion($pdo);

    $pdo->prepare("
        INSERT INTO mantenimiento_tareas (tarea, ultima_ejecucion)
        VALUES (:tarea, :hoy)
        ON DUPLICATE KEY UPDATE ultima_ejecucion = :hoy2
    ")->execute([':tarea' => $tarea, ':hoy' => $hoy, ':hoy2' => $hoy]);
}

function limpiarDiasAnuladosViejos(PDO $pdo): void
{
    ejecutarTareaDiaria($pdo, 'limpiar_dias_anulados', function (PDO $pdo) {
        $pdo->exec("
            DELETE FROM dias_anulados
            WHERE fecha < DATE_SUB(CURDATE(), INTERVAL 2 MONTH)
        ");
    });
}

function limpiarAuditoriaVieja(PDO $pdo): void
{
    ejecutarTareaDiaria($pdo, 'limpiar_auditoria', function (PDO $pdo) {
        $pdo->exec("
            DELETE FROM auditoria
            WHERE creado_en < DATE_SUB(NOW(), INTERVAL 2 MONTH)
        ");
    });
}
