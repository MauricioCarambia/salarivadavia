<?php
/**
 * Rate limiting de intentos de login por usuario+IP.
 * Requiere que $pdo (inc/db.php) ya esté disponible y la tabla login_attempts.
 */

const LOGIN_MAX_INTENTOS = 5;
const LOGIN_BLOQUEO_MINUTOS = 15;

function loginEstaBloqueado(PDO $pdo, string $usuario, string $ip): ?int
{
    $stmt = $pdo->prepare("
        SELECT bloqueado_hasta
        FROM login_attempts
        WHERE usuario = ? AND ip = ?
    ");
    $stmt->execute([$usuario, $ip]);
    $bloqueadoHasta = $stmt->fetchColumn();

    if (!$bloqueadoHasta) {
        return null;
    }

    $segundosRestantes = strtotime($bloqueadoHasta) - time();

    return $segundosRestantes > 0 ? $segundosRestantes : null;
}

function registrarIntentoFallido(PDO $pdo, string $usuario, string $ip): void
{
    $stmt = $pdo->prepare("
        SELECT intentos FROM login_attempts WHERE usuario = ? AND ip = ?
    ");
    $stmt->execute([$usuario, $ip]);
    $intentos = (int) $stmt->fetchColumn();
    $intentos++;

    $bloqueadoHasta = null;
    if ($intentos >= LOGIN_MAX_INTENTOS) {
        $bloqueadoHasta = date('Y-m-d H:i:s', time() + LOGIN_BLOQUEO_MINUTOS * 60);
    }

    $stmt = $pdo->prepare("
        INSERT INTO login_attempts (usuario, ip, intentos, bloqueado_hasta, ultimo_intento)
        VALUES (?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            intentos = ?,
            bloqueado_hasta = ?,
            ultimo_intento = NOW()
    ");
    $stmt->execute([$usuario, $ip, $intentos, $bloqueadoHasta, $intentos, $bloqueadoHasta]);
}

function limpiarIntentosLogin(PDO $pdo, string $usuario, string $ip): void
{
    $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE usuario = ? AND ip = ?");
    $stmt->execute([$usuario, $ip]);
}
