<?php
/**
 * Restricción de acceso por IP/red.
 * Se configura con la variable de entorno ALLOWED_IPS (lista separada por comas,
 * admite IPs sueltas o rangos en notación CIDR, ej: "190.123.45.67,192.168.1.0/24").
 * Si ALLOWED_IPS está vacía o no definida, la restricción queda deshabilitada.
 */

function obtenerIpCliente(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function restriccionIpActiva(): bool
{
    return trim(env('ALLOWED_IPS', '')) !== '';
}

function ipPermitida(string $ip): bool
{
    $listado = trim(env('ALLOWED_IPS', ''));

    if ($listado === '') {
        return true;
    }

    foreach (explode(',', $listado) as $rango) {
        $rango = trim($rango);

        if ($rango === '') {
            continue;
        }

        if (str_contains($rango, '/')) {
            if (ipEnRangoCidr($ip, $rango)) {
                return true;
            }
        } elseif ($ip === $rango) {
            return true;
        }
    }

    return false;
}

/**
 * True si el usuario tiene una autorización temporal vigente para
 * ingresar desde fuera de la red de la clínica.
 */
function tieneAutorizacionTemporal(PDO $pdo, string $tipoUsuario, int $usuarioId): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM autorizaciones_ip
        WHERE tipo_usuario = ?
          AND usuario_id = ?
          AND expira_en > NOW()
    ");
    $stmt->execute([$tipoUsuario, $usuarioId]);

    return (int) $stmt->fetchColumn() > 0;
}

function ipEnRangoCidr(string $ip, string $cidr): bool
{
    [$subred, $bits] = explode('/', $cidr);
    $bits = (int) $bits;

    $ipBin = inet_pton($ip);
    $subredBin = inet_pton($subred);

    if ($ipBin === false || $subredBin === false || strlen($ipBin) !== strlen($subredBin)) {
        return false;
    }

    $bytes = (int) ($bits / 8);
    $resto = $bits % 8;

    if ($bytes > 0 && substr($ipBin, 0, $bytes) !== substr($subredBin, 0, $bytes)) {
        return false;
    }

    if ($resto === 0) {
        return true;
    }

    $mascara = chr((0xFF << (8 - $resto)) & 0xFF);

    return (substr($ipBin, $bytes, 1) & $mascara) === (substr($subredBin, $bytes, 1) & $mascara);
}
