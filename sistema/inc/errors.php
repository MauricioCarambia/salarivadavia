<?php
/**
 * Configuración centralizada de manejo de errores.
 * En producción (APP_ENV != 'development') se ocultan los errores al
 * navegador y se registran en el log de PHP en su lugar.
 */

if (env('APP_ENV', 'production') === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}
