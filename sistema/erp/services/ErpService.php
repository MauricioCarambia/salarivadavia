<?php

class ErpService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /* ==============================
        💰 KPI DEL DÍA
        FIX: si no existe hoy, hereda
             el saldo_total del día anterior
             como monto_inicial
    ============================== */
    public function getKpis(): array
{
    /* ==========================
       TOMAR EL ÚLTIMO DÍA DEL RESUMEN
    ========================== */
    $stmt = $this->pdo->query("
        SELECT
            monto_inicial,
            fondo_inicial,
            total_cajas,
            total_fondos,
            egresos_caja,
            egresos_fondos,
            saldo_caja,
            saldo_fondo,
            saldo_total
        FROM resumen_financiero_diario
        ORDER BY fecha DESC
        LIMIT 1
    ");

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        return [
            'monto_inicial'  => 0,
            'fondo_inicial'  => 0,
            'ingresos_caja'  => 0,
            'egresos_caja'   => 0,
            'ingresos_fondo' => 0,
            'egresos_fondo'  => 0,
            'caja'           => 0,
            'fondo'          => 0,
            'total'          => 0,
        ];
    }

    return [
        'monto_inicial'  => (float)$row['monto_inicial'],
        'fondo_inicial'  => (float)$row['fondo_inicial'],
        'ingresos_caja'  => (float)$row['total_cajas'],
        'egresos_caja'   => (float)$row['egresos_caja'],
        'ingresos_fondo' => (float)$row['total_fondos'],
        'egresos_fondo'  => (float)$row['egresos_fondos'],
        'caja'           => (float)$row['saldo_caja'],
        'fondo'          => (float)$row['saldo_fondo'],
        'total'          => (float)$row['saldo_total'],
    ];
}

    /* ==============================
        📅 LIBRO DIARIO
    ============================== */
    public function getLibroDiario(): array
    {
        $stmt = $this->pdo->query("
        SELECT
            fecha,
            monto_inicial,
            fondo_inicial,
            saldo_caja,
            saldo_fondo,
            saldo_total
        FROM resumen_financiero_diario
        ORDER BY fecha DESC
        LIMIT 60
    ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    /* ==============================
        📦 MOVIMIENTOS DEL DÍA
    ============================== */
    public function getMovimientos(string $fecha): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                id,
                fecha,
                tipo,
                concepto,
                monto,
                profesional_id
            FROM egresos
            WHERE DATE(creado_en) = ?
            ORDER BY id DESC
        ");
        $stmt->execute([$fecha]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ==============================
        ⚠️ ALERTAS ERP
    ============================== */
    public function getAlertas(): array
    {
        $alertas = [];
        $hoy = date('Y-m-d');

        $stmt = $this->pdo->prepare("
            SELECT saldo_total
            FROM resumen_financiero_diario
            WHERE fecha = ?
            LIMIT 1
        ");
        $stmt->execute([$hoy]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row && $row['saldo_total'] < 0) {
            $alertas[] = "❌ Saldo negativo detectado en caja";
        }

        $stmt = $this->pdo->query("
            SELECT COUNT(*) FROM caja_sesion WHERE estado = 'abierta'
        ");
        if ((int)$stmt->fetchColumn() > 1) {
            $alertas[] = "⚠️ Hay múltiples cajas abiertas";
        }

        return $alertas;
    }
    public function getFondos(): array
    {
        $sql = "
        SELECT
            d.id,
            d.nombre,
            COALESCE(SUM(r.monto),0) AS saldo
        FROM destinos_reparto d
        LEFT JOIN cobros_reparto r
            ON r.destino_id = d.id
        WHERE d.categoria = 'fondo'
        GROUP BY d.id, d.nombre
        ORDER BY d.nombre
    ";

        $stmt = $this->pdo->query($sql);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public function reconstruirDesde(string $fecha): void
    {
        $stmt = $this->pdo->prepare("
        SELECT fecha
        FROM resumen_financiero_diario
        WHERE fecha >= ?
        ORDER BY fecha
    ");

        $stmt->execute([$fecha]);

        $fechas = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($fechas as $f) {
            reconstruirDia($this->pdo, $f);
        }
    }
}
