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
       CAPITAL INICIAL
    ========================== */
        $capitalInicial = (float)$this->pdo
            ->query("
        SELECT COALESCE(monto_inicial,0)
        FROM resumen_financiero_diario
        WHERE monto_inicial > 0
        ORDER BY fecha DESC
        LIMIT 1
    ")
            ->fetchColumn();

        $fondoInicial = (float)$this->pdo
            ->query("
        SELECT COALESCE(fondo_inicial,0)
        FROM resumen_financiero_diario
        WHERE fondo_inicial > 0
        ORDER BY fecha DESC
        LIMIT 1
    ")
            ->fetchColumn();

        /* ==========================
       INGRESOS ACUMULADOS
    ========================== */
        $stmt = $this->pdo->query("
        SELECT
            COALESCE(SUM(total_caja),0)  AS ingresos_caja,
            COALESCE(SUM(total_fondo),0) AS ingresos_fondo
        FROM arqueos_caja
    ");

        $ingresos = $stmt->fetch(PDO::FETCH_ASSOC);

        $cajaIngresos  = (float)$ingresos['ingresos_caja'];
        $fondoIngresos = (float)$ingresos['ingresos_fondo'];

        /* ==========================
       EGRESOS ACUMULADOS
    ========================== */
        $stmt = $this->pdo->query("
        SELECT
            COALESCE(
                SUM(
                    CASE
                        WHEN tipo = 'caja'
                        THEN monto
                        ELSE 0
                    END
                ),
            0) AS eg_caja,

            COALESCE(
                SUM(
                    CASE
                        WHEN tipo = 'fondo'
                        THEN monto
                        ELSE 0
                    END
                ),
            0) AS eg_fondo

        FROM egresos
    ");

        $egresos = $stmt->fetch(PDO::FETCH_ASSOC);

        $egresosCaja  = (float)$egresos['eg_caja'];
        $egresosFondo = (float)$egresos['eg_fondo'];

        /* ==========================
       DISPONIBLES REALES
    ========================== */

        $cajaDisponible =
            $capitalInicial
            + $cajaIngresos
            - $egresosCaja;

        $fondoDisponible =
            $fondoInicial
            + $fondoIngresos
            - $egresosFondo;

        $totalDisponible =
            $cajaDisponible
            + $fondoDisponible;

        /* ==========================
       RETORNO
    ========================== */
        return [

            // Capitales base
            'monto_inicial' => $capitalInicial,
            'fondo_inicial' => $fondoInicial,

            // Movimientos
            'ingresos_caja'  => $cajaIngresos,
            'egresos_caja'   => $egresosCaja,

            'ingresos_fondo' => $fondoIngresos,
            'egresos_fondo'  => $egresosFondo,

            // Disponibles reales
            'caja'  => $cajaDisponible,
            'fondo' => $fondoDisponible,

            // Total clínica
            'total' => $totalDisponible
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
