<?php
// Configuración de la base de datos
$dbhost = "localhost";
$dbusuario = "root";
$dbpassword = "";
$db = "sala";

// Nombre del archivo de backup
$backup_file = __DIR__ . "\\backup_" . date("Y-m-d_H-i-s") . ".sql";

// Comando para realizar el backup
$command = "C:\\xampp\\mysql\\bin\\mysqldump --opt --host=$dbhost --user=$dbusuario --password=$dbpassword $db > $backup_file";

// Ejecutar el comando
exec($command, $output, $result);

// Verificar si se realizó correctamente
if ($result == 0) {
    echo "Backup realizado con éxito. Archivo: $backup_file";
} else {
    echo "Error al realizar el backup. Código de error: $result";
}
?>
