<?php
require_once __DIR__ . '/sistema/inc/db.php';

$stmt = $pdo->query("
    SELECT id, fecha, total_cajas, total_fondos, egresos_caja, egresos_fondos,
           monto_inicial, fondo_inicial
    FROM resumen_financiero_diario
    ORDER BY fecha ASC
");
$dias = $stmt->fetchAll(PDO::FETCH_ASSOC);

$saldoCajaAnterior  = 0;
$saldoFondoAnterior = 0;

foreach ($dias as $i => $dia) {

    if ($i === 0) {
        // Primer día: respetar los iniciales cargados a mano
        $arrastre_caja  = (float)$dia['monto_inicial'];
        $arrastre_fondo = (float)$dia['fondo_inicial'];
    } else {
        // Días siguientes: heredar saldo del día anterior
        $arrastre_caja  = $saldoCajaAnterior;
        $arrastre_fondo = $saldoFondoAnterior;
    }

    $saldoCaja  = $arrastre_caja  + (float)$dia['total_cajas']  - (float)$dia['egresos_caja'];
    $saldoFondo = $arrastre_fondo + (float)$dia['total_fondos'] - (float)$dia['egresos_fondos'];
    $saldoTotal = $saldoCaja + $saldoFondo;

    $pdo->prepare("
        UPDATE resumen_financiero_diario
        SET
            monto_inicial = ?,
            fondo_inicial = ?,
            saldo_caja    = ?,
            saldo_fondo   = ?,
            saldo_total   = ?
        WHERE id = ?
    ")->execute([
        $arrastre_caja,
        $arrastre_fondo,
        $saldoCaja,
        $saldoFondo,
        $saldoTotal,
        $dia['id'],
    ]);

    echo "✅ {$dia['fecha']} | arrastre_caja=\${$arrastre_caja} | arrastre_fondo=\${$arrastre_fondo} | saldo_caja=\${$saldoCaja} | saldo_fondo=\${$saldoFondo} | total=\${$saldoTotal}\n";

    $saldoCajaAnterior  = $saldoCaja;
    $saldoFondoAnterior = $saldoFondo;
}

echo "\nListo.\n";