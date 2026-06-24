<?php
/**
 * Cron mensual: borra registros de auditoria con 2 meses o mas de
 * antiguedad, solo el dia 1 de cada mes.
 *
 * Pensado para ejecutarse via Hostinger > Cron Jobs (no hay sesion de
 * usuario en ese contexto, por eso se protege con un token secreto en
 * vez de login/CSRF).
 *
 * Configuracion en sistema/.env:
 *   CRON_SECRET=algo_largo_y_aleatorio
 *
 * URL de ejemplo para el cron job:
 *   https://tu-dominio.com/sistema/cron/limpiar_auditoria.php?token=ESE_SECRETO
 *
 * Programar para correr una vez al dia (Hostinger no permite "solo el
 * dia 1"); el propio script verifica la fecha y no hace nada el resto
 * de los dias.
 */

require_once __DIR__ . '/../inc/db.php';

header('Content-Type: application/json');

$secreto = env('CRON_SECRET');

if (empty($secreto) || !hash_equals($secreto, $_GET['token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// Solo actuar el primer dia del mes (forzar con ?force=1 para pruebas manuales)
if (date('j') !== '1' && empty($_GET['force'])) {
    echo json_encode(['success' => true, 'message' => 'No es el dia 1 del mes, no se hizo nada']);
    exit;
}

try {

    $limite = date('Y-m-d 00:00:00', strtotime('-2 months'));

    $stmt = $pdo->prepare("DELETE FROM auditoria WHERE creado_en < ?");
    $stmt->execute([$limite]);

    echo json_encode([
        'success' => true,
        'eliminados' => $stmt->rowCount(),
        'limite' => $limite
    ]);

} catch (Throwable $e) {

    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
