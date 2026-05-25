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
        📅 WHERE BASE (IGUAL AL TUYO)
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
        💰 TOTALES (TU QUERY ORIGINAL)
    ========================================= */
    public function getTotales(array $filtros): array
    {
        return $this->remember(__FUNCTION__ . md5(json_encode($filtros)), function () use ($filtros) {

            [$where, $params] = $this->buildWhere($filtros);

            $sql = "
            SELECT 
                SUM(CASE WHEN c.tipo='ingreso' AND LOWER(c.medio_pago)='efectivo' THEN c.total ELSE 0 END) AS ing_efectivo,
                SUM(CASE WHEN c.tipo='ingreso' AND transferencia_tipo = 'clinica' AND LOWER(c.medio_pago)='transferencia' THEN c.total ELSE 0 END) AS ing_transferencia,
                SUM(CASE WHEN c.tipo='egreso' AND LOWER(c.medio_pago)='efectivo' THEN c.total ELSE 0 END) AS egr_efectivo,
                SUM(CASE WHEN c.tipo='egreso' AND LOWER(c.medio_pago)='transferencia' THEN c.total ELSE 0 END) AS egr_transferencia
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
        💰 GANANCIA CLÍNICA (FONDO)
    ========================================= */
    public function getGananciaClinica(array $filtros): float
    {
        return $this->remember(__FUNCTION__ . md5(json_encode($filtros)), function () use ($filtros) {

            [$where, $params] = $this->buildWhere($filtros);

            $sql = "
        SELECT COALESCE(SUM(cr.monto),0)
        FROM cobros_reparto cr
        INNER JOIN cobros c ON c.id = cr.cobro_id
        INNER JOIN destinos_reparto dr ON dr.id = cr.destino_id
        INNER JOIN caja_sesion cs ON cs.id = c.caja_sesion_id
        $where
        AND dr.categoria = 'fondo'
        AND dr.tipo = 'ingreso'
        AND LOWER(c.medio_pago) = 'efectivo' -- 🔥 CLAVE
        AND c.estado = 'activo'
        ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            return (float)$stmt->fetchColumn();
        });
    }
    public function getGananciaClinicaEfectivo(array $filtros): float
    {
        return $this->remember(__FUNCTION__ . md5(json_encode($filtros)), function () use ($filtros) {

            [$where, $params] = $this->buildWhere($filtros);

            $sql = "
        SELECT COALESCE(SUM(cr.monto),0)
        FROM cobros_reparto cr
        INNER JOIN cobros c ON c.id = cr.cobro_id
        INNER JOIN destinos_reparto dr ON dr.id = cr.destino_id
        INNER JOIN caja_sesion cs ON cs.id = c.caja_sesion_id
        $where
        AND dr.categoria = 'fondo'
        AND dr.tipo = 'ingreso'
        AND LOWER(c.medio_pago) = 'efectivo'
        AND c.estado = 'activo'
        ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            return (float)$stmt->fetchColumn();
        });
    }
    public function getGananciaClinicaTransferencia(array $filtros): float
    {
        return $this->remember(__FUNCTION__ . md5(json_encode($filtros)), function () use ($filtros) {

            [$where, $params] = $this->buildWhere($filtros);

            $sql = "
        SELECT COALESCE(SUM(cr.monto),0)
        FROM cobros_reparto cr
        INNER JOIN cobros c ON c.id = cr.cobro_id
        INNER JOIN destinos_reparto dr ON dr.id = cr.destino_id
        INNER JOIN caja_sesion cs ON cs.id = c.caja_sesion_id
        $where
        AND dr.categoria = 'fondo'
        AND dr.tipo = 'ingreso'
        AND LOWER(c.medio_pago) = 'transferencia'
        AND c.transferencia_tipo != 'profesional' -- 🔥 CLAVE
        AND c.estado = 'activo'
        ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            return (float)$stmt->fetchColumn();
        });
    }

    /* =========================================
        📊 DESTINOS (IGUAL)
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

            /* =========================
               💵 EFECTIVO
            ========================= */
            SUM(CASE 
                WHEN LOWER(c.medio_pago) = 'efectivo' 
                THEN 
                    CASE 
                        WHEN dr.tipo = 'egreso' 
                        THEN -cr.monto
                        ELSE cr.monto 
                    END
                ELSE 0 
            END) AS total_efectivo,

            /* =========================
               🏦 TRANSFERENCIAS
            ========================= */
            SUM(CASE 
                WHEN LOWER(c.medio_pago) = 'transferencia' 
                THEN 
                    CASE 
                        -- ❌ NO RESTAR cuando es pago directo a profesional
                        WHEN dr.tipo = 'egreso' 
                             AND c.transferencia_tipo = 'profesional'
                        THEN 0

                        -- ✅ RESTAR solo egresos reales de clínica
                        WHEN dr.tipo = 'egreso' 
                        THEN -cr.monto

                        ELSE cr.monto 
                    END
                ELSE 0 
            END) AS total_transferencia,

            /* =========================
               📊 TOTAL GENERAL
            ========================= */
            SUM(
                CASE 
                    -- ❌ excluir egreso falso (transferencia directa)
                    WHEN dr.tipo = 'egreso' 
                         AND LOWER(c.medio_pago) = 'transferencia'
                         AND c.transferencia_tipo = 'profesional'
                    THEN 0

                    -- ✅ egreso real
                    WHEN dr.tipo = 'egreso'
                    THEN -cr.monto

                    ELSE cr.monto
                END
            ) AS total_general

        FROM cobros_reparto cr
        INNER JOIN destinos_reparto dr ON dr.id = cr.destino_id
        INNER JOIN cobros c ON c.id = cr.cobro_id
        INNER JOIN caja_sesion cs ON cs.id = c.caja_sesion_id

        $where

        GROUP BY dr.id
        ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        });
    }

    /* =========================================
        🔴 EGRESOS PROFESIONALES
    ========================================= */
    public function getEgresosProfesionalesEfectivo(array $filtros): float
    {
        return $this->remember(__FUNCTION__ . md5(json_encode($filtros)), function () use ($filtros) {

            [$where, $params] = $this->buildWhere($filtros);

            $sql = "
        SELECT COALESCE(SUM(cr.monto),0)
        FROM cobros_reparto cr
        INNER JOIN cobros c ON c.id = cr.cobro_id
        INNER JOIN destinos_reparto dr ON dr.id = cr.destino_id
        INNER JOIN caja_sesion cs ON cs.id = c.caja_sesion_id
        $where
        AND LOWER(c.medio_pago) = 'efectivo'
        AND c.turno_id IS NOT NULL
        AND dr.tipo = 'egreso'
        ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            return (float)$stmt->fetchColumn();
        });
    }

    /* =========================================
        💳 PROFESIONALES A PAGAR
    ========================================= */
    public function getProfesionalesAPagar(array $filtros): float
    {
        return $this->remember(__FUNCTION__ . md5(json_encode($filtros)), function () use ($filtros) {

            [$where, $params] = $this->buildWhere($filtros);

            $sql = "
            SELECT COALESCE(SUM(cr.monto),0)
            FROM cobros_reparto cr
            INNER JOIN cobros c ON c.id = cr.cobro_id
            INNER JOIN destinos_reparto dr ON dr.id = cr.destino_id
            INNER JOIN caja_sesion cs ON cs.id = c.caja_sesion_id
            $where
            AND LOWER(c.medio_pago) = 'transferencia'
            AND c.transferencia_tipo = 'clinica'
            AND dr.tipo = 'egreso'
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            return (float)$stmt->fetchColumn();
        });
    }

    /* =========================================
        💸 COMISIÓN CLÍNICA
    ========================================= */
    public function getComisionClinicaTransferProfesional(array $filtros): float
    {
        return $this->remember(__FUNCTION__ . md5(json_encode($filtros)), function () use ($filtros) {

            [$where, $params] = $this->buildWhere($filtros);

            $sql = "
            SELECT COALESCE(SUM(cr.monto),0)
            FROM cobros_reparto cr
            INNER JOIN cobros c ON c.id = cr.cobro_id
            INNER JOIN destinos_reparto dr ON dr.id = cr.destino_id
            INNER JOIN caja_sesion cs ON cs.id = c.caja_sesion_id
            $where
            AND LOWER(c.medio_pago) = 'transferencia'
            AND c.transferencia_tipo = 'profesional'
            AND dr.categoria = 'caja'
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            return (float)$stmt->fetchColumn();
        });
    }

    /* =========================================
        📋 TURNOS
    ========================================= */
    public function getTurnos(array $filtros): array
    {
        return $this->remember(__FUNCTION__ . md5(json_encode($filtros)), function () use ($filtros) {

            [$where, $params] = $this->buildWhere($filtros);

            $sql = "
            SELECT 
                c.id as cobro_id,
                c.numero_completo,
                c.total as monto,
                c.fecha,
                c.medio_pago,
                c.estado,
                c.tipo,
                c.transferencia_tipo,
                pr.nombre as prof_nom,
                pr.apellido as prof_ape,
                ed.nombre as emp_dest,
                pa.nombre as pac_nom,
pa.apellido as pac_ape,
                GROUP_CONCAT(cd.nombre SEPARATOR ', ') AS practica_nombre
            FROM cobros c
            INNER JOIN caja_sesion cs ON cs.id = c.caja_sesion_id
            LEFT JOIN cobros_detalle cd ON cd.cobro_id = c.id
            LEFT JOIN profesionales pr ON pr.id = c.profesional_id
            LEFT JOIN empleados ed ON ed.id = c.empleado_destino_id
            LEFT JOIN pacientes pa ON pa.id = c.paciente_id
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
    ========================================= */
    public function getExternos(array $filtros): array
    {
        return $this->remember(__FUNCTION__ . md5(json_encode($filtros)), function () use ($filtros) {

            [$where, $params] = $this->buildWhere($filtros);

            $sql = "
            SELECT 
                c.id as cobro_id,
                c.numero_completo,
                c.total as monto,
                c.fecha,
                c.medio_pago,
                c.estado,
                c.tipo,
                c.concepto,
                c.transferencia_tipo,
                ed.nombre as emp_dest,
                pa.nombre as pac_nom,
pa.apellido as pac_ape,
                GROUP_CONCAT(dr.nombre SEPARATOR ', ') as destinos
            FROM cobros c
            INNER JOIN caja_sesion cs ON cs.id = c.caja_sesion_id
            LEFT JOIN empleados ed ON ed.id = c.empleado_destino_id
            LEFT JOIN cobros_reparto cr ON cr.cobro_id = c.id
            LEFT JOIN destinos_reparto dr ON dr.id = cr.destino_id
            LEFT JOIN pacientes pa ON pa.id = c.paciente_id
            $where AND c.turno_id IS NULL
            GROUP BY c.id
            ORDER BY c.fecha DESC
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        });
    }
    public function getDeudaRecuperadaProfesional(array $filtros): float
    {
        return $this->remember(__FUNCTION__ . md5(json_encode($filtros)), function () use ($filtros) {

            [$where, $params] = $this->buildWhere($filtros);

            $sql = "
        SELECT COALESCE(SUM(c.deuda_clinica),0)
        FROM cobros c
        INNER JOIN caja_sesion cs ON cs.id = c.caja_sesion_id
        $where
        AND LOWER(c.medio_pago) = 'transferencia'
        AND c.transferencia_tipo = 'profesional'
        AND c.deuda_clinica > 0
        AND c.estado = 'activo'
        ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            return (float)$stmt->fetchColumn();
        });
    }
    public function getDeudasProfesionales(array $filtros): array
    {
        return $this->remember(__FUNCTION__ . md5(json_encode($filtros)), function () use ($filtros) {

            [$where, $params] = $this->buildWhere($filtros);

            $sql = "
        SELECT 
            p.id,
            p.nombre,
            p.apellido,
            COALESCE(SUM(c.deuda_clinica),0) as deuda_total
        FROM cobros c
        INNER JOIN profesionales p ON p.id = c.profesional_id
        INNER JOIN caja_sesion cs ON cs.id = c.caja_sesion_id
        $where
        AND LOWER(c.medio_pago) = 'transferencia'
        AND c.transferencia_tipo = 'profesional'
        AND c.deuda_clinica > 0
        GROUP BY p.id
        HAVING deuda_total > 0
        ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        });
    }
    public function getTransferenciasEmpleados(array $filtros): array
    {
        return $this->remember(__FUNCTION__ . md5(json_encode($filtros)), function () use ($filtros) {

            [$where, $params] = $this->buildWhere($filtros);

            $sql = "
        SELECT 
            e.id,
            e.nombre,
            SUM(c.total) as total_transferencias
        FROM cobros c
        INNER JOIN empleados e ON e.id = c.empleado_destino_id
        INNER JOIN caja_sesion cs ON cs.id = c.caja_sesion_id
        $where
        AND LOWER(c.medio_pago) = 'transferencia'
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
        🧾 RESUMEN FINAL (TU LÓGICA EXACTA)
    ========================================= */
    public function getResumen(array $filtros): array
    {
        $tot = $this->getTotales($filtros);

        $egresosProfesionales = $this->getEgresosProfesionalesEfectivo($filtros);
        $profAPagar = $this->getProfesionalesAPagar($filtros);
        $comision = $this->getComisionClinicaTransferProfesional($filtros);

        // 🔥 NUEVO
        $deudaProfesional = $this->getDeudaRecuperadaProfesional($filtros);

        $ingEfectivo = (float)($tot['ing_efectivo'] ?? 0);
        $ingTransfer = (float)($tot['ing_transferencia'] ?? 0);
        $egrEfectivo = (float)($tot['egr_efectivo'] ?? 0);
        $egrTransfer = (float)($tot['egr_transferencia'] ?? 0);

        $egresosCaja =
            $egrEfectivo
            + $egresosProfesionales
            + $profAPagar
            - $comision;

        return [
            'caja' => [
                'ingresos' => $ingEfectivo,
                'egresos'  => $egresosCaja,

                // 🔥 SUMA LO RETENIDO AL PROFESIONAL
                'balance'  => $ingEfectivo - $egresosCaja + ($deudaProfesional-$comision)
            ],

            'banco' => [
                'ingresos' => $ingTransfer,
                'egresos'  => $egrTransfer,

                // 🔥 BANCO PURO
                'balance'  => $ingTransfer - $egrTransfer
            ],

            'destinos' => $this->getDestinos($filtros),
            'turnos' => $this->getTurnos($filtros),
            'externos' => $this->getExternos($filtros),
            'deudas' => $this->getDeudasProfesionales($filtros),
            'transferencias' => $this->getTransferenciasEmpleados($filtros)
        ];
    }
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
           🔥 REPARTO (REAL)
        ====================== */
        $stmt = $this->pdo->prepare("
            SELECT 
                SUM(CASE 
                    WHEN d.categoria = 'caja' 
                    THEN (CASE WHEN d.tipo='egreso' THEN -cr.monto ELSE cr.monto END)
                    ELSE 0 
                END) as total_caja,

                SUM(CASE 
                    WHEN d.categoria = 'fondo' 
                    THEN (CASE WHEN d.tipo='egreso' THEN -cr.monto ELSE cr.monto END)
                    ELSE 0 
                END) as total_fondo

            FROM cobros_reparto cr
            INNER JOIN destinos_reparto d ON d.id = cr.destino_id
            INNER JOIN cobros c ON c.id = cr.cobro_id
            WHERE c.caja_sesion_id = ?
            AND c.estado = 'activo'
        ");
        $stmt->execute([$cajaSesionId]);

        $totales = $stmt->fetch(PDO::FETCH_ASSOC);

        $totalCaja  = (float)($totales['total_caja'] ?? 0);
        $totalFondo = (float)($totales['total_fondo'] ?? 0);

        /* ======================
           💰 EFECTIVO REAL
        ====================== */
        $stmt = $this->pdo->prepare("
            SELECT 
                COALESCE(SUM(CASE 
                    WHEN tipo='ingreso' 
                    AND medio_pago='efectivo'
                    AND estado='activo'
                THEN total END),0) as ingresos,

                COALESCE(SUM(CASE 
                    WHEN tipo='egreso' 
                    AND medio_pago='efectivo'
                    AND estado='activo'
                THEN total END),0) as egresos
            FROM cobros
            WHERE caja_sesion_id = ?
        ");
        $stmt->execute([$cajaSesionId]);

        $mov = $stmt->fetch(PDO::FETCH_ASSOC);

        $ingresos = (float)$mov['ingresos'];
        $egresos  = (float)$mov['egresos'];

        /* ======================
           📊 PROFESIONALES
        ====================== */
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(cr.monto),0)
            FROM cobros_reparto cr
            INNER JOIN destinos_reparto d ON d.id = cr.destino_id
            INNER JOIN cobros c ON c.id = cr.cobro_id
            WHERE c.caja_sesion_id = ?
            AND d.categoria = 'profesional'
            AND d.tipo = 'egreso'
            AND c.estado = 'activo'
        ");
        $stmt->execute([$cajaSesionId]);

        $egresosProfesionales = (float)$stmt->fetchColumn();

        /* ======================
           📊 RESULTADOS
        ====================== */

        $cajaEsperada = $montoInicial + $totalCaja;
        $totalSistema =  $montoInicial + $totalCaja + $totalFondo;

        return [
            'caja_id' => $sesion['caja_id'],
            'usuario_id' => $sesion['usuario_id'],

            'monto_inicial' => $montoInicial,

            'total_caja' => $totalCaja,
            'total_fondo' => $totalFondo,
            'total_sistema' => $totalSistema,

            'caja_esperada' => $cajaEsperada,

            'ingresos_efectivo' => $ingresos,
            'egresos_efectivo' => $egresos,
            'egresos_profesionales' => $egresosProfesionales
        ];
    }

    /* =========================================
        🔒 CERRAR CAJA (USA EL CORE)
    ========================================= */
    public function cerrarCaja(int $cajaSesionId, float $montoReal, int $usuarioId): array
    {
        if ($cajaSesionId <= 0) {
            throw new Exception("Caja inválida");
        }

        if ($montoReal < 0) {
            throw new Exception("Monto real inválido");
        }

        try {

            $this->pdo->beginTransaction();

            // ======================
            // 🔎 SESIÓN
            // ======================
            $stmt = $this->pdo->prepare("
            SELECT *
            FROM caja_sesion
            WHERE id = ?
            LIMIT 1
        ");
            $stmt->execute([$cajaSesionId]);
            $sesion = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$sesion) {
                throw new Exception("Sesión no encontrada");
            }

            if ($sesion['estado'] !== 'abierta') {
                throw new Exception("La caja ya está cerrada");
            }

            // ======================
            // 🔒 CONTROL DE PERMISOS
            // ======================
            // $esAdmin = ($rol === 'Administrador');
            // $esPropietario = ((int)$sesion['usuario_id'] === (int)$usuarioId);

            // if (!$esAdmin && !$esPropietario) {
            //     throw new Exception("No tenés permisos para cerrar esta caja");
            // }

            // ======================
            // 🧠 CÁLCULO CENTRAL
            // ======================
            $calc = $this->calcularSesion($cajaSesionId);

            $montoInicial = $calc['monto_inicial'];
            $cajaEsperada = $calc['caja_esperada'];
            $totalSistema = $calc['total_sistema'];
            $totalFondo = $calc['total_fondo'];

            $diferencia = $montoReal - $totalSistema;

            // ======================
            // 🔒 CERRAR SESIÓN
            // ======================
            $stmt = $this->pdo->prepare("
            UPDATE caja_sesion 
            SET 
                estado = 'cerrada',
                fecha_cierre = NOW(),
                monto_cierre = ?
            WHERE id = ?
        ");
            $stmt->execute([$montoReal, $cajaSesionId]);

            // ======================
            // 🔒 CERRAR CAJA
            // ======================
            $stmt = $this->pdo->prepare("
            UPDATE cajas 
            SET activo = 0
            WHERE id = ?
        ");
            $stmt->execute([$sesion['caja_id']]);

            // ======================
            // 🧾 INSERT ARQUEO
            // ======================
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
                $montoInicial,
                $totalSistema,
                $calc['total_caja'],
                $calc['total_fondo'],
                $montoReal,
                $diferencia,
                $usuarioId,
                $cajaSesionId
            ]);

            // ======================
            // 📊 RESUMEN DIARIO
            // ======================
            $this->actualizarResumenFinanciero([
                'fecha' => date('Y-m-d'),
                'monto_inicial' => $montoInicial,
                'total_caja' => $calc['total_caja'],
                'total_fondo' => $calc['total_fondo']
            ]);

            $this->pdo->commit();

            return [
                'success' => true,
                'caja_esperada' => $cajaEsperada,
                'diferencia' => $diferencia,
                'total_sistema' => $totalSistema,
                'total_fondo' => $totalFondo
            ];
        } catch (Throwable $e) {

            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }
    private function actualizarResumenFinanciero(array $data): void
    {
        $fecha = $data['fecha'];

        // ======================
        // 🧱 CREAR SI NO EXISTE
        // ======================
        $stmt = $this->pdo->prepare("
        SELECT id 
        FROM resumen_financiero_diario
        WHERE fecha = ?
    ");
        $stmt->execute([$fecha]);

        if (!$stmt->fetchColumn()) {

            // arrastre simple del día anterior (solo base)
            $stmt = $this->pdo->prepare("
            SELECT saldo_total
            FROM resumen_financiero_diario
            WHERE fecha < ?
            ORDER BY fecha DESC
            LIMIT 1
        ");
            $stmt->execute([$fecha]);

            $saldoAnterior = (float)($stmt->fetchColumn() ?? 0);

            $this->pdo->prepare("
            INSERT INTO resumen_financiero_diario
            (fecha, monto_inicial, saldo_total)
            VALUES (?, ?, ?)
        ")->execute([
                $fecha,
                $saldoAnterior,
                $saldoAnterior
            ]);
        }

        // ======================
        // 📊 1. RECALCULAR MOVIMIENTOS
        // ======================
        $this->pdo->prepare("
        UPDATE resumen_financiero_diario r
        SET 
            total_cajas = (
                SELECT COALESCE(SUM(a.total_caja),0)
                FROM arqueos_caja a
                WHERE a.fecha >= r.fecha
                AND a.fecha < DATE_ADD(r.fecha, INTERVAL 1 DAY)
            ),

            total_fondos = (
                SELECT COALESCE(SUM(a.total_fondo),0)
                FROM arqueos_caja a
                WHERE a.fecha >= r.fecha
                AND a.fecha < DATE_ADD(r.fecha, INTERVAL 1 DAY)
            ),

            egresos_caja = (
                SELECT COALESCE(SUM(e.monto),0)
                FROM egresos e
                WHERE e.tipo = 'caja'
                AND e.creado_en >= r.fecha
                AND e.creado_en < DATE_ADD(r.fecha, INTERVAL 1 DAY)
            ),

            egresos_fondos = (
                SELECT COALESCE(SUM(e.monto),0)
                FROM egresos e
                WHERE e.tipo = 'fondo'
                AND e.creado_en >= r.fecha
                AND e.creado_en < DATE_ADD(r.fecha, INTERVAL 1 DAY)
            )

        WHERE r.fecha = ?
    ")->execute([$fecha]);

        // ======================
        // 💰 2. RECALCULAR SALDOS (SEPARADO = SEGURO)
        // ======================
        $this->pdo->prepare("
        UPDATE resumen_financiero_diario
        SET 
            saldo_caja =
                total_cajas - egresos_caja,

            saldo_fondo =
                total_fondos - egresos_fondos,

            saldo_total =
                (monto_inicial + total_cajas - egresos_caja)
                + (total_fondos - egresos_fondos)

        WHERE fecha = ?
    ")->execute([$fecha]);
    }
}
