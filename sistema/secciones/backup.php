<?php
require_once __DIR__ . '/../inc/db.php';

if (empty($_SESSION['login'])) {
    header("Location: login.php");
    exit;
}

if (empty($_SESSION['es_admin'])) {
    die('<div class="alert alert-danger">No tenés permisos para acceder a esta sección</div>');
}

require_once __DIR__ . '/../inc/config.php';

// Configuración de la base de datos (misma fuente que inc/db.php)
$dbhost = env('DB_HOST', 'localhost');
$dbusuario = env('DB_USER', 'root');
$dbpassword = env('DB_PASS', '');
$db = env('DB_NAME', 'sala');

// Carpeta de backups FUERA del webroot, no accesible públicamente
$backupDir = __DIR__ . '/../../../backups_salarivadavia';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0750, true);
}

$backup_file = $backupDir . '/backup_' . date('Y-m-d_H-i-s') . '.sql';

$mysqldump = 'C:\\xampp\\mysql\\bin\\mysqldump.exe';

$command = escapeshellcmd($mysqldump)
    . ' --opt --host=' . escapeshellarg($dbhost)
    . ' --user=' . escapeshellarg($dbusuario)
    . ' --password=' . escapeshellarg($dbpassword)
    . ' ' . escapeshellarg($db)
    . ' > ' . escapeshellarg($backup_file);

exec($command, $output, $result);

if ($result === 0) {
    echo "Backup realizado con éxito. Archivo guardado fuera del directorio público.";
} else {
    echo "Error al realizar el backup. Código de error: $result";
}
