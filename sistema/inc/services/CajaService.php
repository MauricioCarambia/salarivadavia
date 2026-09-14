<?php

class CajaService
{
    private PDO $pdo;
    private array $cache = [];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /* =========================================
        🧠 CACHE
    ========================================= */
    private function remember(string $key, callable $callback)
    {
        if (!isset($this->cache[$key])) {
            $this->cache[$key] = $callback();
        }
        return $this->cache[$key];
    }

    /* =========================================
        📅 WHERE BASE
    ========================================= */
    private function buildWhere(array $filtros): array
    {
        $where = " WHERE c.fecha BETWEEN ? AND ? AND c.estado != 'anulado' ";
        $params = [
            $filtros['desde'] . ' 00:00:00',
            $filtros['hasta'] . ' 23:59:59'
        ];

        if (!empty($filtros['caja_id'])) {
            $where .= " AND cs.caja_id = ?";
            $params[] = $filtros['caja_id'];
        }

        if (!empty($filtros['turno'])) {
            $where .= " AND cs.turno = ?";
            $params[] = $filtros['turno'];
        }

        if (!empty($filtros['usuario_id'])) {
            $where .= " AND cs.usuario_id = ?";
            $params[] = $filtros['usuario_id'];
        }

        return [$where, $params];
    }

    /* =========================================
        💰 TOTALES
    ========================================= */
    public function getTotales(array $filtros): array
    {
        return $this->remember(__FUNCTION__ . md5(json_encode($filtros)), function () use ($filtros) {

            [$where, $params] = $this->buildWhere($filtros);

            $sql = "
                SELECT 
                    SUM(CASE 
                        WHEN c.tipo = 'ingreso' 
                         AND LOWER(c.medio_pago) = 'efectivo' 
                        THEN c.total ELSE 0 
                    END) AS ing_efectivo,

                    SUM(CASE 
                        WHEN c.tipo = 'ingreso' 
                         AND c.transferencia_tipo = 'clinica' 
                         AND LOWER(c.medio_pago) = 'transferencia' 
                        THEN c.total ELSE 0 
                    END) AS ing_transferencia,

                    SUM(CASE 
                        WHEN c.tipo = 'egreso' 
                         AND LOWER(c.medio_pago) = 'efectivo' 
                        THEN c.total ELSE 0 
                    END) AS egr_efectivo,

                    SUM(CASE 
                        WHEN c.tipo = 'egreso' 
                         AND LOWER(c.medio_pago) = 'transferencia' 
                        THEN c.total ELSE 0 
                    END) AS egr_transferencia

                FROM cobros c
                INNER JOIN caja_sesion cs ON cs.id = c.caja_sesion_id
                $where
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        });
    }

    /* =========================================
        💰 GANANCIA CLÍNICA EFECTIVO
        FIX: eliminado doble filtro estado
    ========================================= */
    public function getGananciaClinicaEfectivo(array $filtros): float
    {
        return $this->remember(__FUNCTION__ . md5(json_encode($filtros)), function () use ($filtros) {

            [$where, $params] = $this->buildWhere($filtros);

            $sql = "
                SELECT COALESCE(SUM(cr.monto), 0)
                FROM cobros_reparto cr
                INNER JOIN cobros c             ON c.id    = cr.cobro_id
                INNER JOIN destinos_reparto dr  ON dr.id   = cr.destino_id
                INNER JOIN caja_sesion cs       ON cs.id   = c.caja_sesion_id
                $where
                AND dr.categoria       = 'fondo'
                AND dr.tipo            = 'ingreso'
                AND LOWER(c.medio_pago) = 'efectivo'
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            return (float)$stmt->fetchColumn();
        });
    }

    /* =========================================
        💰 GANANCIA CLÍNICA TRANSFERENCIA
        FIX: eliminado doble filtro estado
    ========================================= */
    public function getGananciaClinicaTransferencia(array $filtros): float
    {
        return $this->remember(__FUNCTION__ . md5(json_encode($filtros)), function () use ($filtros) {

            [$where, $params] = $this->buildWhere($filtros);

            $sql = "
                SELECT COALESCE(SUM(cr.monto), 0)
                FROM cobros_reparto cr
                INNER JOIN cobros c             ON c.id    = cr.cobro_id
                INNER JOIN destinos_reparto dr  ON dr.id   = cr.destino_id
                INNER JOIN caja_sesion cs       ON cs.id   = c.caja_sesion_id
                $where
                AND dr.categoria            = 'fondo'
                AND dr.tipo                 = 'ingreso'
                AND LOWER(c.medio_pago)     = 'transferencia'
                AND c.transferencia_tipo   != 'profesional'
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            return (float)$stmt->fetchColumn();
        });
    }

    /* =========================================
        📊 DESTINOS
    ========================================= */
    public function getDestinos(array $filtros): array
    {
        return $this->remember(__FUNCTION__ . md5(json_encode($filtros)), function () use ($filtros) {

            [$where, $params] = $this->buildWhere($filtros);

            $sql = "
                SELECT 
                    dr.nombre,
                    dr.tipo,
                    dr.categoria,

                    SUM(CASE 
                        WHEN LOWER(c.medio_pago) = 'efectivo' 
                        THEN (CASE WHEN dr.tipo = 'egreso' THEN -cr.monto ELSE cr.monto END)
                        ELSE 0 
                    END) AS total_efectivo,

                    SUM(CASE 
                        WHEN LOWER(c.medio_pago) = 'transferencia' 
                        THEN (
                            CASE 
                                WHEN dr.tipo = 'egreso' AND c.transferencia_tipo = 'profesional' THEN 0
                                WHEN dr.tipo = 'egreso' THEN -cr.monto
                                ELSE cr.monto 
                            END
                        )
                        ELSE 0 
                    END) AS total_transferencia,

                    SUM(
                        CASE 
                            WHEN dr.tipo = 'egreso' 
                             AND LOWER(c.medio_pago) = 'transferencia'
                             AND c.transferencia_tipo = 'profesional'
                            THEN 0
                            WHEN dr.tipo = 'egreso' THEN -cr.monto
                            ELSE cr.monto
                        END
                    ) AS total_general

                FROM cobros_reparto cr
                INNER JOIN destinos_reparto dr ON dr.id   = cr.destino_id
                INNER JOIN cobros c             ON c.id   = cr.cobro_id
                INNER JOIN caja_sesion cs       ON cs.id  = c.caja_sesion_id

                $where

                GROUP BY dr.id
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        });
    }

    /* =========================================
        🔴 EGRESOS PROFESIONALES EFECTIVO
        FIX: reemplazado turno_id IS NOT NULL
             por dr.categoria = 'profesional'
             para incluir movimientos manuales
    ========================================= */
    public function getEgresosProfesionalesEfectivo(array $filtros): float
    {
        return $this->remember(__FUNCTION__ . md5(json_encode($filtros)), function () use ($filtros) {

            [$where, $params] = $this->buildWhere($filtros);

            $sql = "
                SELECT COALESCE(SUM(cr.monto), 0)
                FROM cobros_reparto cr
                INNER JOIN cobros c             ON c.id   = cr.cobro_id
                INNER JOIN destinos_reparto dr  ON dr.id  = cr.destino_id
                INNER JOIN caja_sesion cs       ON cs.id  = c.caja_sesion_id
                $where
                AND LOWER(c.medio_pago) = 'efectivo'
                AND dr.categoria        = 'profesional'
                AND dr.tipo             = 'egreso'
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            return (float)$stmt->fetchColumn();
        });
    }

    /* =========================================
        💳 PROFESIONALES A PAGAR (TRANSFERENCIA CLÍNICA)
    ========================================= */
    public function getProfesionalesAPagar(array $filtros): float
    {
        return $this->remember(__FUNCTION__ . md5(json_encode($filtros)), function () use ($filtros) {

            [$where, $params] = $this->buildWhere($filtros);

            $sql = "
                SELECT COALESCE(SUM(cr.monto), 0)
                FROM cobros_reparto cr
                INNER JOIN cobros c             ON c.id   = cr.cobro_id
                INNER JOIN destinos_reparto dr  ON dr.id  = cr.destino_id
                INNER JOIN caja_sesion cs       ON cs.id  = c.caja_sesion_id
                $where
                AND LOWER(c.medio_pago)     = 'transferencia'
                AND c.transferencia_tipo    = 'clinica'
                AND dr.tipo                 = 'egreso'
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            return (float)$stmt->fetchColumn();
        });
    }

    /* =========================================
        💸 COMISIÓN CLÍNICA (TRANSFERENCIA PROFESIONAL)
    ========================================= */
    public function getComisionClinicaTransferProfesional(array $filtros): float
    {
        return $this->remember(__FUNCTION__ . md5(json_encode($filtros)), function () use ($filtros) {

            [$where, $params] = $this->buildWhere($filtros);

            $sql = "
                SELECT COALESCE(SUM(cr.monto), 0)
                FROM cobros_reparto cr
                INNER JOIN cobros c             ON c.id   = cr.cobro_id
                INNER JOIN destinos_reparto dr  ON dr.id  = cr.destino_id
                INNER JOIN caja_sesion cs       ON cs.id  = c.caja_sesion_id
                $where
                AND LOWER(c.medio_pago)     = 'transferencia'
                AND c.transferencia_tipo    = 'profesional'
                AND dr.categoria            = 'caja'
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            return (float)$stmt->fetchColumn();
        });
    }

    /* =========================================
        💸 DEUDA RECUPERADA DE PROFESIONAL
        FIX: eliminado doble filtro estado
    ========================================= */
    public function getDeudaRecuperadaProfesional(array $filtros): float
    {
        return $this->remember(__FUNCTION__ . md5(json_encode($filtros)), function () use ($filtros) {

            [$where, $params] = $this->buildWhere($filtros);

            $sql = "
                SELECT COALESCE(SUM(c.deuda_clinica), 0)
                FROM cobros c
                INNER JOIN caja_sesion cs ON cs.id = c.caja_sesion_id
                $where
                AND LOWER(c.medio_pago)     = 'transferencia'
                AND c.transferencia_tipo    = 'profesional'
                AND c.deuda_clinica         > 0
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            return (float)$stmt->fetchColumn();
        });
    }

    /* =========================================
        👨‍⚕️ DEUDAS POR PROFESIONAL
    ========================================= */
    public function getDeudasProfesionales(array $filtros): array
    {
        return $this->remember(__FUNCTION__ . md5(json_encode($filtros)), function () use ($filtros) {

            [$where, $params] = $this->buildWhere($filtros);

            $sql = "
                SELECT 
                    p.Id,
                    p.nombre,
                    p.apellido,
                    COALESCE(SUM(c.deuda_clinica), 0) AS deuda_total
                FROM cobros c
                INNER JOIN profesionales p  ON p.Id   = c.profesional_id
                INNER JOIN caja_sesion cs   ON cs.id  = c.caja_sesion_id
                $where
                AND LOWER(c.medio_pago)     = 'transferencia'
                AND c.transferencia_tipo    = 'profesional'
                AND c.deuda_clinica         > 0
                GROUP BY p.Id
                HAVING deuda_total > 0
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        });
    }

    /* =========================================
        🏦 TRANSFERENCIAS POR EMPLEADO
    ========================================= */
    public function getTransferenciasEmpleados(array $filtros): array
    {
        return $this->remember(__FUNCTION__ . md5(json_encode($filtros)), function () use ($filtros) {

            [$where, $params] = $this->buildWhere($filtros);

            $sql = "
                SELECT 
                    e.id,
                    e.nombre,
                    SUM(c.total) AS total_transferencias
                FROM cobros c
                INNER JOIN empleados e      ON e.id   = c.empleado_destino_id
                INNER JOIN caja_sesion cs   ON cs.id  = c.caja_sesion_id
                $where
                AND LOWER(c.medio_pago)         = 'transferencia'
                AND c.empleado_destino_id IS NOT NULL
                GROUP BY e.id
                HAVING total_transferencias > 0
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        });
    }

    /* =========================================
        📋 TURNOS
        FIX: pr.Id y pa.Id (mayúscula)
    ========================================= */
    public function getTurnos(array $filtros): array
    {
        return $this->remember(__FUNCTION__ . md5(json_encode($filtros)), function () use ($filtros) {

            [$where, $params] = $this->buildWhere($filtros);

            $sql = "
                SELECT 
                    c.id              AS cobro_id,
                    c.numero_completo,
                    c.total           AS monto,
                    c.fecha,
                    c.medio_pago,
                    c.estado,
                    c.tipo,
                    c.transferencia_tipo,
                    pr.nombre         AS prof_nom,
                    pr.apellido       AS prof_ape,
                    ed.nombre         AS emp_dest,
                    pa.nombre         AS pac_nom,
                    pa.apellido       AS pac_ape,
                    pr_dest.nombre   AS prof_dest_nom,
pr_dest.apellido AS prof_dest_ape,
                    GROUP_CONCAT(cd.nombre SEPARATOR ', ') AS practica_nombre
                FROM cobros c
                INNER JOIN caja_sesion cs       ON cs.id  = c.caja_sesion_id
                LEFT JOIN cobros_detalle cd     ON cd.cobro_id    = c.id
                LEFT JOIN profesionales pr      ON pr.Id          = c.profesional_id
                LEFT JOIN empleados ed          ON ed.id          = c.empleado_destino_id
                LEFT JOIN pacientes pa          ON pa.Id          = c.paciente_id
                LEFT JOIN profesionales pr_dest
    ON pr_dest.Id = c.profesional_destino_id
                $where AND c.turno_id IS NOT NULL
                GROUP BY c.id
                ORDER BY c.fecha DESC
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        });
    }

    /* =========================================
        📋 EXTERNOS
        FIX: pa.Id (mayúscula)
    ========================================= */
    public function getExternos(array $filtros): array
    {
        return $this->remember(__FUNCTION__ . md5(json_encode($filtros)), function () use ($filtros) {

            [$where, $params] = $this->buildWhere($filtros);

            $sql = "
                SELECT 
                    c.id              AS cobro_id,
                    c.numero_completo,
                    c.total           AS monto,
                    c.fecha,
                    c.medio_pago,
                    c.estado,
                    c.tipo,
                    c.concepto,
                    c.transferencia_tipo,
                    ed.nombre         AS emp_dest,
                    pa.nombre         AS pac_nom,
                    pa.apellido       AS pac_ape,
                    pr.nombre   AS prof_nom,
pr.apellido AS prof_ape,
                    GROUP_CONCAT(dr.nombre SEPARATOR ', ') AS destinos
                FROM cobros c
                INNER JOIN caja_sesion cs       ON cs.id  = c.caja_sesion_id
                LEFT JOIN empleados ed          ON ed.id          = c.empleado_destino_id
                LEFT JOIN cobros_reparto cr     ON cr.cobro_id    = c.id
                LEFT JOIN destinos_reparto dr   ON dr.id          = cr.destino_id
                LEFT JOIN pacientes pa          ON pa.Id          = c.paciente_id
                LEFT JOIN profesionales pr ON pr.Id = c.profesional_id
                $where AND c.turno_id IS NULL
                GROUP BY c.id
                ORDER BY c.fecha DESC
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        });
    }

    /* =========================================
        🧾 RESUMEN FINAL
        FIX: balance de caja sin doble resta de comisión
    ========================================= */
    public function getResumen(array $filtros): array
    {
        $tot = $this->getTotales($filtros);

        $egresosProfesionalesEfectivo = $this->getEgresosProfesionalesEfectivo($filtros);
        $profAPagar                   = $this->getProfesionalesAPagar($filtros);
        $comision                     = $this->getComisionClinicaTransferProfesional($filtros);
        $deudaProfesional             = $this->getDeudaRecuperadaProfesional($filtros);

        $ingEfectivo = (float)($tot['ing_efectivo']      ?? 0);
        $ingTransfer = (float)($tot['ing_transferencia']  ?? 0);
        $egrEfectivo = (float)($tot['egr_efectivo']      ?? 0);
        $egrTransfer = (float)($tot['egr_transferencia']  ?? 0);

        /* =============================================
       CAJA FÍSICA

       CASO 1 (efectivo):
         + ingEfectivo (el total cobrado)
         - egresosProfesionalesEfectivo (lo que se le paga al prof)
         = queda la parte de la clínica ✅

       CASO 2 (transf. clínica):
         + 0 en ingEfectivo
         - egresosProfesionalesEfectivo (se le paga al prof de caja)
         = balance negativo en caja, positivo en banco ✅

       CASO 3 (transf. profesional):
         + 0 en ingEfectivo
         - 0 en egresos (el prof ya cobró por transf, no se le paga de caja)
         = balance 0 en caja ✅
         la deuda se muestra informativa hasta que se descuente
    ============================================= */
        $ingresosEfectivoTotal = $ingEfectivo + $deudaProfesional;
        $egresosCaja = $egrEfectivo + $egresosProfesionalesEfectivo + $profAPagar;

        /* =============================================
       BANCO

       CASO 1: no entra nada al banco
       CASO 2: entra el total cobrado, no sale nada
               (el prof cobra de caja, no del banco)
       CASO 3: no entra nada (el prof recibió la transf
               directa, no pasó por el banco de la clínica)
    ============================================= */
        $ingBanco = $ingTransfer;   // solo transf. clínica directa
        $egrBanco = $egrTransfer;   // egresos reales por banco

        return [
            'caja' => [
                'ingresos'             => $ingresosEfectivoTotal,
                'egresos'              => $egresosCaja,
                'egresos_profesional'  => $egresosProfesionalesEfectivo + $profAPagar,
                'balance'              => $ingresosEfectivoTotal - $egresosCaja,
            ],

            'banco' => [
                'ingresos'             => $ingBanco,
                'egresos'              => $egrBanco,
                'egresos_profesional'  => $profAPagar,
                'balance'              => $ingBanco - $egrBanco,
            ],

            // Informativo: lo que los profesionales deben
            // compensar con efectivo en días futuros
            'deuda_pendiente'  => $deudaProfesional,

            'destinos'         => $this->getDestinos($filtros),
            'turnos'           => $this->getTurnos($filtros),
            'externos'         => $this->getExternos($filtros),
            'deudas'           => $this->getDeudasProfesionales($filtros),
            'transferencias'   => $this->getTransferenciasEmpleados($filtros),
        ];
    }

    /* =========================================
        🔢 CALCULAR SESIÓN
        FIX: $diferencia contra $cajaEsperada,
             no contra $totalSistema
    ========================================= */
    public function calcularSesion(int $cajaSesionId): array
    {
        /* ======================
           🔎 SESIÓN
        ====================== */
        $stmt = $this->pdo->prepare("
            SELECT caja_id, usuario_id, monto_inicial
            FROM caja_sesion
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$cajaSesionId]);
        $sesion = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$sesion) {
            throw new Exception("Sesión no encontrada");
        }

        $montoInicial = (float)$sesion['monto_inicial'];

        /* ======================
           🔥 REPARTO POR CATEGORÍA
        ====================== */
        $stmt = $this->pdo->prepare("
            SELECT 
                SUM(CASE 
                    WHEN d.categoria = 'caja' 
                    THEN (CASE WHEN d.tipo = 'egreso' THEN -cr.monto ELSE cr.monto END)
                    ELSE 0 
                END) AS total_caja,

                SUM(CASE 
                    WHEN d.categoria = 'fondo' 
                    THEN (CASE WHEN d.tipo = 'egreso' THEN -cr.monto ELSE cr.monto END)
                    ELSE 0 
                END) AS total_fondo

            FROM cobros_reparto cr
            INNER JOIN destinos_reparto d ON d.id  = cr.destino_id
            INNER JOIN cobros c           ON c.id  = cr.cobro_id
            WHERE c.caja_sesion_id = ?
            AND c.estado = 'activo'
        ");
        $stmt->execute([$cajaSesionId]);
        $totales = $stmt->fetch(PDO::FETCH_ASSOC);

        $totalCaja  = (float)($totales['total_caja']  ?? 0);
        $totalFondo = (float)($totales['total_fondo'] ?? 0);

        /* ======================
           💰 EFECTIVO REAL
        ====================== */
        $stmt = $this->pdo->prepare("
            SELECT 
                COALESCE(SUM(CASE 
                    WHEN tipo = 'ingreso' AND medio_pago = 'efectivo' AND estado = 'activo'
                    THEN total 
                END), 0) AS ingresos,

                COALESCE(SUM(CASE 
                    WHEN tipo = 'egreso' AND medio_pago = 'efectivo' AND estado = 'activo'
                    THEN total 
                END), 0) AS egresos
            FROM cobros
            WHERE caja_sesion_id = ?
        ");
        $stmt->execute([$cajaSesionId]);
        $mov = $stmt->fetch(PDO::FETCH_ASSOC);

        $ingresos = (float)$mov['ingresos'];
        $egresos  = (float)$mov['egresos'];

        /* ======================
           📊 EGRESOS PROFESIONALES
        ====================== */
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(cr.monto), 0)
            FROM cobros_reparto cr
            INNER JOIN destinos_reparto d ON d.id  = cr.destino_id
            INNER JOIN cobros c           ON c.id  = cr.cobro_id
            WHERE c.caja_sesion_id = ?
            AND d.categoria = 'profesional'
            AND d.tipo      = 'egreso'
            AND c.estado    = 'activo'
            AND (
                LOWER(c.medio_pago) = 'efectivo'
                OR (LOWER(c.medio_pago) = 'transferencia' AND c.transferencia_tipo = 'clinica')
            )
        ");
        $stmt->execute([$cajaSesionId]);
        $egresosProfesionales = (float)$stmt->fetchColumn();

        $stmt = $this->pdo->prepare("
    SELECT
        medio_pago,
        SUM(
            CASE
                WHEN tipo = 'egreso' THEN -total
                ELSE total
            END
        ) total
    FROM cobros
    WHERE caja_sesion_id = ?
    AND estado = 'activo'
    GROUP BY medio_pago
");
        $stmt->execute([$cajaSesionId]);

        $totalesMedioPago = [
            'efectivo' => 0,
            'transferencia' => 0,
            'debito' => 0,
            'credito' => 0
        ];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $medio = strtolower($row['medio_pago']);

            if (isset($totalesMedioPago[$medio])) {
                $totalesMedioPago[$medio] = (float)$row['total'];
            }
        }
        /* ======================
           💵 / 🏦 REPARTO POR MEDIO DE PAGO
           (caja + fondo, separado efectivo vs transferencia)

           - Si el cobro fue por transferencia al profesional, la
             parte de la clínica (comisión) el profesional la entrega
             en efectivo, por lo que cuenta como efectivo.
           - Si el cobro fue por transferencia a la clínica, lo que
             se le paga al profesional se le da en efectivo desde la
             caja, así que también se descuenta del efectivo.
        ====================== */
        $stmt = $this->pdo->prepare("
            SELECT
                SUM(CASE
                    WHEN LOWER(c.medio_pago) = 'efectivo'
                      OR (LOWER(c.medio_pago) = 'transferencia' AND c.transferencia_tipo = 'profesional')
                    THEN (CASE WHEN d.tipo = 'egreso' THEN -cr.monto ELSE cr.monto END)

                    WHEN LOWER(c.medio_pago) = 'transferencia'
                     AND c.transferencia_tipo = 'clinica'
                     AND d.categoria = 'profesional'
                     AND d.tipo = 'egreso'
                    THEN -cr.monto

                    ELSE 0
                END) AS total_efectivo,

                SUM(CASE
                    WHEN LOWER(c.medio_pago) = 'transferencia'
                     AND c.transferencia_tipo != 'profesional'
                     AND d.categoria IN ('caja', 'fondo')
                    THEN (CASE WHEN d.tipo = 'egreso' THEN -cr.monto ELSE cr.monto END)

                    WHEN LOWER(c.medio_pago) = 'transferencia'
                     AND c.transferencia_tipo = 'clinica'
                     AND d.categoria = 'profesional'
                     AND d.tipo = 'egreso'
                    THEN cr.monto

                    ELSE 0
                END) AS total_transferencia

            FROM cobros_reparto cr
            INNER JOIN destinos_reparto d ON d.id = cr.destino_id
            INNER JOIN cobros c           ON c.id = cr.cobro_id
            WHERE c.caja_sesion_id = ?
            AND c.estado = 'activo'
            AND (
                d.categoria IN ('caja', 'fondo')
                OR (
                    d.categoria = 'profesional'
                    AND d.tipo = 'egreso'
                    AND LOWER(c.medio_pago) = 'transferencia'
                    AND c.transferencia_tipo = 'clinica'
                )
            )
        ");
        $stmt->execute([$cajaSesionId]);
        $repartoPorMedio = $stmt->fetch(PDO::FETCH_ASSOC);

        $totalRepartoEfectivo      = (float)($repartoPorMedio['total_efectivo']      ?? 0);
        $totalRepartoTransferencia = (float)($repartoPorMedio['total_transferencia'] ?? 0);

        /* ======================
           📊 RESULTADOS
           FIX: diferencia vs cajaEsperada (físico),
                no vs totalSistema (incluye fondos)
        ====================== */
        $cajaEsperada = $montoInicial + $totalCaja + $totalFondo;  // era: $montoInicial + $totalCaja
        $totalSistema = $montoInicial + $totalCaja + $totalFondo;  // igual que caja_esperada ahora

        $cajaEsperadaEfectivo      = $montoInicial + $totalRepartoEfectivo;
        $cajaEsperadaTransferencia = $totalRepartoTransferencia;

        return [
            'caja_id'                     => $sesion['caja_id'],
            'usuario_id'                  => $sesion['usuario_id'],
            'monto_inicial'               => $montoInicial,
            'total_caja'                  => $totalCaja,
            'total_fondo'                 => $totalFondo,
            'total_sistema'               => $totalSistema,
            'caja_esperada'               => $cajaEsperada,
            'caja_esperada_efectivo'      => $cajaEsperadaEfectivo,
            'caja_esperada_transferencia' => $cajaEsperadaTransferencia,
            'ingresos_efectivo'           => $ingresos,
            'egresos_efectivo'            => $egresos,
            'egresos_profesionales'       => $egresosProfesionales,
        ];
    }

    /* =========================================
        🔒 CERRAR CAJA
        FIX: $diferencia contra $cajaEsperada
    ========================================= */
    public function cerrarCaja(int $cajaSesionId, float $montoReal, int $usuarioId): array
    {
        if ($cajaSesionId <= 0) throw new Exception("Caja inválida");
        if ($montoReal < 0) throw new Exception("Monto inválido");

        try {

            // 🔥 SOLO UNA TRANSACCIÓN (AQUÍ)
            $this->pdo->beginTransaction();

            /* ======================
           🔎 SESIÓN
        ====================== */
            $stmt = $this->pdo->prepare("
            SELECT *
            FROM caja_sesion
            WHERE id = ?
            FOR UPDATE
        ");
            $stmt->execute([$cajaSesionId]);
            $sesion = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$sesion) throw new Exception("Sesión no encontrada");
            if ($sesion['estado'] !== 'abierta') throw new Exception("Caja cerrada");

            /* ======================
           🧠 CÁLCULO
        ====================== */
            $calc = $this->calcularSesion($cajaSesionId);

            $cajaEsperada = $calc['caja_esperada'];
            $totalSistema = $calc['total_sistema'];

            // El monto real ingresado es solo efectivo, se compara
            // contra lo que debería quedar en efectivo (no incluye
            // lo que quedó por transferencia)
            $diferencia = $montoReal - $calc['caja_esperada_efectivo'];

            /* ======================
           🔒 CERRAR SESIÓN
        ====================== */
            $stmt = $this->pdo->prepare("
            UPDATE caja_sesion 
            SET estado = 'cerrada',
                fecha_cierre = NOW(),
                monto_cierre = ?
            WHERE id = ?
        ");
            $stmt->execute([$montoReal, $cajaSesionId]);

            /* ======================
           🔒 DESACTIVAR CAJA
        ====================== */
            $stmt = $this->pdo->prepare("
            UPDATE cajas SET activo = 0 WHERE id = ?
        ");
            $stmt->execute([$sesion['caja_id']]);

            /* ======================
           🧾 ARQUEO
        ====================== */
            $stmt = $this->pdo->prepare("
            INSERT INTO arqueos_caja (
                caja_id,
                fecha,
                monto_inicial,
                total_sistema,
                total_caja,
                total_fondo,
                total_real,
                diferencia,
                usuario_id,
                caja_sesion_id
            ) VALUES (?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?)
        ");

            $stmt->execute([
                $sesion['caja_id'],
                $calc['monto_inicial'],
                $totalSistema,
                $calc['total_caja'],
                $calc['total_fondo'],
                $montoReal,
                $diferencia,
                $usuarioId,
                $cajaSesionId
            ]);

            /* ======================
           🔁 RECONSTRUCCIÓN
        ====================== */

            $fechaAfectada = date('Y-m-d', strtotime($sesion['fecha_apertura']));

            $this->actualizarResumenFinanciero(['fecha' => $fechaAfectada]);

            $this->pdo->commit();

            return [
                'success' => true,
                'caja_esperada' => $cajaEsperada,
                'diferencia' => $diferencia,
            ];
        } catch (Throwable $e) {

            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }

    /* =========================================
        📅 RESUMEN FINANCIERO DIARIO
    ========================================= */
 private function actualizarResumenFinanciero(array $data): void
{
    $fecha = $data['fecha'];

    /* ======================
       CREAR DÍA SI NO EXISTE
       HEREDANDO SALDOS DEL DÍA ANTERIOR
    ====================== */
    $stmt = $this->pdo->prepare("
        SELECT id
        FROM resumen_financiero_diario
        WHERE fecha = ?
        LIMIT 1
    ");
    $stmt->execute([$fecha]);

    if (!$stmt->fetchColumn()) {

        $stmt = $this->pdo->prepare("
            SELECT saldo_caja, saldo_fondo
            FROM resumen_financiero_diario
            WHERE fecha < ?
            ORDER BY fecha DESC
            LIMIT 1
        ");
        $stmt->execute([$fecha]);
        $anterior = $stmt->fetch(PDO::FETCH_ASSOC);

        $montoInicialHeredado = (float)($anterior['saldo_caja']  ?? 0);
        $fondoInicialHeredado = (float)($anterior['saldo_fondo'] ?? 0);

        $this->pdo->prepare("
            INSERT INTO resumen_financiero_diario (
                fecha,
                monto_inicial,
                fondo_inicial,
                total_cajas,
                total_fondos,
                egresos_caja,
                egresos_fondos,
                saldo_caja,
                saldo_fondo,
                saldo_total
            )
            VALUES (?, ?, ?, 0, 0, 0, 0, ?, ?, ?)
        ")->execute([
            $fecha,
            $montoInicialHeredado,
            $fondoInicialHeredado,
            $montoInicialHeredado,
            $fondoInicialHeredado,
            $montoInicialHeredado + $fondoInicialHeredado,
        ]);
    }

    /* ======================
       ARQUEOS DEL DÍA
    ====================== */
    $stmt = $this->pdo->prepare("
        SELECT
            COALESCE(SUM(total_caja),0)  AS cajas,
            COALESCE(SUM(total_fondo),0) AS fondos
        FROM arqueos_caja
        WHERE DATE(fecha) = ?
    ");
    $stmt->execute([$fecha]);
    $ar = $stmt->fetch(PDO::FETCH_ASSOC);

    /* ======================
       EGRESOS DEL DÍA
    ====================== */
    $stmt = $this->pdo->prepare("
        SELECT
            COALESCE(SUM(CASE WHEN tipo='caja'  THEN monto ELSE 0 END), 0) AS eg_caja,
            COALESCE(SUM(CASE WHEN tipo='fondo' THEN monto ELSE 0 END), 0) AS eg_fondo
        FROM egresos
        WHERE DATE(creado_en) = ?
    ");
    $stmt->execute([$fecha]);
    $eg = $stmt->fetch(PDO::FETCH_ASSOC);

    /* ======================
       ARRASTRE DEL DÍA ANTERIOR
    ====================== */
    $stmt = $this->pdo->prepare("
        SELECT saldo_caja, saldo_fondo
        FROM resumen_financiero_diario
        WHERE fecha < ?
        ORDER BY fecha DESC
        LIMIT 1
    ");
    $stmt->execute([$fecha]);
    $anterior = $stmt->fetch(PDO::FETCH_ASSOC);

    // Si es el primer día, usar monto_inicial cargado manualmente
    if ($anterior) {
        $arrastre_caja  = (float)$anterior['saldo_caja'];
        $arrastre_fondo = (float)$anterior['saldo_fondo'];
    } else {
        $stmt = $this->pdo->prepare("
            SELECT monto_inicial, fondo_inicial
            FROM resumen_financiero_diario
            WHERE fecha = ?
            LIMIT 1
        ");
        $stmt->execute([$fecha]);
        $base = $stmt->fetch(PDO::FETCH_ASSOC);
        $arrastre_caja  = (float)($base['monto_inicial'] ?? 0);
        $arrastre_fondo = (float)($base['fondo_inicial'] ?? 0);
    }

    /* ======================
       SALDOS ACUMULADOS
    ====================== */
    $saldoCaja  = $arrastre_caja  + (float)$ar['cajas']  - (float)$eg['eg_caja'];
    $saldoFondo = $arrastre_fondo + (float)$ar['fondos'] - (float)$eg['eg_fondo'];
    $saldoTotal = $saldoCaja + $saldoFondo;

    /* ======================
       GUARDAR RESUMEN
    ====================== */
    $this->pdo->prepare("
        UPDATE resumen_financiero_diario
        SET
            fondo_inicial  = ?,
            total_cajas    = ?,
            total_fondos   = ?,
            egresos_caja   = ?,
            egresos_fondos = ?,
            saldo_caja     = ?,
            saldo_fondo    = ?,
            saldo_total    = ?
        WHERE fecha = ?
    ")->execute([
        $arrastre_fondo,
        $ar['cajas'],
        $ar['fondos'],
        $eg['eg_caja'],
        $eg['eg_fondo'],
        $saldoCaja,
        $saldoFondo,
        $saldoTotal,
        $fecha,
    ]);
}
}
