<?php
require_once "../inc/db.php";
session_start();

header('Content-Type: application/json');

try {

    $real = floatval($_POST['total_real'] ?? 0);

    $inicio = date('Y-m-d 00:00:00');
    $fin = date('Y-m-d 23:59:59');

    /* TOTAL SISTEMA */
    $stmt = $pdo->prepare("
        SELECT tipo, SUM(monto) total
        FROM caja_movimientos
        WHERE fecha BETWEEN ? AND ?
        GROUP BY tipo
    ");
    $stmt->execute([$inicio, $fin]);

    $ing = 0; $egr = 0;

    foreach($stmt as $r){
        if($r['tipo']=='INGRESO') $ing = $r['total'];
        else $egr = $r['total'];
    }

    $sistema = $ing - $egr;
    $dif = $real - $sistema;

    /* GUARDAR */
    $stmt = $pdo->prepare("
        INSERT INTO arqueos_caja
        (caja_id, fecha, total_sistema, total_real, diferencia, usuario_id)
        VALUES (1, NOW(), ?, ?, ?, ?)
    ");

    $stmt->execute([
        $sistema,
        $real,
        $dif,
        $_SESSION['id'] ?? 0
    ]);

    echo json_encode([
        'success'=>true,
        'diferencia'=>$dif
    ]);

} catch(Exception $e){

    echo json_encode([
        'success'=>false,
        'message'=>$e->getMessage()
    ]);
}