<?php
require_once "../inc/db.php";

session_name("turnos");
session_start();

header('Content-Type: application/json');

try {

    $pdo->beginTransaction();

    /* ==============================
       📥 DATOS
    ============================== */
    $tipo           = $_POST['tipo'] ?? '';
    $concepto       = trim($_POST['concepto'] ?? '');
    $monto_input    = $_POST['monto'] ?? null;
    $practica_id    = (int)($_POST['practica_id'] ?? 0);
    $profesional_id = (int)($_POST['profesional_id'] ?? 0);
    $paciente_id    = (int)($_POST['paciente_id'] ?? 0);
    $usuario_id     = $_SESSION['user_id'] ?? 0;

    /* ==============================
       🧠 VALIDACIONES
    ============================== */
    if (!$tipo) throw new Exception("Debe seleccionar tipo de movimiento");
    if (!$concepto) throw new Exception("Debe ingresar un concepto");
    if (!$usuario_id) throw new Exception("Sesión expirada");

    /* ==============================
       🏦 CAJA ABIERTA
    ============================== */
    $stmt = $pdo->prepare("
        SELECT * 
        FROM caja_sesion 
        WHERE usuario_id = ? AND estado = 'abierta'
        LIMIT 1
    ");
    $stmt->execute([$usuario_id]);
    $caja = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$caja) throw new Exception("No hay caja abierta");

    $caja_id = (int)$caja['caja_id'];
    $caja_sesion_id = (int)$caja['id'];

    /* ==============================
       🔢 NUMERACIÓN
    ============================== */
    $stmt = $pdo->prepare("
        SELECT MAX(numero) 
        FROM cobros 
        WHERE punto_venta = ? 
        FOR UPDATE
    ");
    $stmt->execute([$caja_id]);

    $ultimoNumero = (int)$stmt->fetchColumn();
    $nuevoNumero = $ultimoNumero ? $ultimoNumero + 1 : 1;

    $numeroCompleto = str_pad($caja_id, 4, '0', STR_PAD_LEFT) . '-' .
                      str_pad($nuevoNumero, 8, '0', STR_PAD_LEFT);

    /* ==============================
       💰 MONTO
    ============================== */
    $monto = (float)$monto_input;

    if ((!$monto || $monto <= 0) && $practica_id > 0) {

        $tipo_paciente = 'particular';

        if ($paciente_id > 0) {
            $stmt = $pdo->prepare("
                SELECT MAX(fecha_correspondiente)
                FROM pagos_afiliados
                WHERE paciente_id = ?
            ");
            $stmt->execute([$paciente_id]);
            $ultima = $stmt->fetchColumn();

            if ($ultima) {
                $ultimo = new DateTime(date('Y-m-01', strtotime($ultima)));
                $actual = new DateTime(date('Y-m-01'));
                $diff = $ultimo->diff($actual);
                $meses = ($diff->y * 12) + $diff->m;

                if ($meses <= 3) {
                    $tipo_paciente = 'socio';
                }
            }
        }

        $stmt = $pdo->prepare("
            SELECT precio
            FROM practicas_precios
            WHERE practica_id = ?
              AND tipo_paciente = ?
              AND activo = 1
            ORDER BY fecha_desde DESC
            LIMIT 1
        ");
        $stmt->execute([$practica_id, $tipo_paciente]);
        $monto = (float)$stmt->fetchColumn();
    }

    if ($monto <= 0) {
        throw new Exception("Debe ingresar un monto válido o seleccionar una práctica");
    }

    /* ==============================
       📄 CREAR COBRO
    ============================== */
    $stmt = $pdo->prepare("
        INSERT INTO cobros 
        (fecha, total, usuario_id, caja_id, caja_sesion_id, estado, paciente_id, profesional_id, punto_venta, numero, numero_completo)
        VALUES (NOW(), ?, ?, ?, ?, 'activo', ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $monto,
        $usuario_id,
        $caja_id,
        $caja_sesion_id,
        $paciente_id ?: null,
        $profesional_id ?: null,
        $caja_id,
        $nuevoNumero,
        $numeroCompleto
    ]);

    $cobro_id = $pdo->lastInsertId();

    /* ==============================
       📋 DETALLE
    ============================== */
    if ($practica_id > 0) {

        $stmt = $pdo->prepare("SELECT nombre FROM practicas WHERE id = ?");
        $stmt->execute([$practica_id]);
        $practica = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("
            INSERT INTO cobros_detalle
            (cobro_id, practica_id, nombre, precio)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $cobro_id,
            $practica_id,
            $practica['nombre'] ?? 'Práctica',
            $monto
        ]);
    }

    /* ==============================
       💰 MOVIMIENTO CAJA
    ============================== */
    $stmt = $pdo->prepare("
        INSERT INTO caja_movimientos 
        (caja_id, caja_sesion_id, tipo, concepto, monto, fecha, cobro_id, descripcion)
        VALUES (?, ?, ?, ?, ?, NOW(), ?, ?)
    ");

    $descripcion = ($practica_id > 0) ? "Movimiento con practica" : "Movimiento manual";

    $stmt->execute([
        $caja_id,
        $caja_sesion_id,
        strtolower($tipo), // 🔥 importante (ingreso/egreso en minúscula)
        $concepto,
        $monto,
        $cobro_id,
        $descripcion
    ]);

    /* ==============================
       🔥 REPARTO
    ============================== */
    if ($practica_id > 0) {

        $stmt = $pdo->prepare("
            SELECT id
            FROM practicas_reparto
            WHERE practica_id = ?
            AND (profesional_id = ? OR profesional_id IS NULL)
            ORDER BY profesional_id DESC
            LIMIT 1
        ");
        $stmt->execute([$practica_id, $profesional_id ?: null]);
        $reparto_id = $stmt->fetchColumn();

        if ($reparto_id) {

            $stmt = $pdo->prepare("
                SELECT destino_id, tipo_id, valor, orden
                FROM practicas_reparto_detalle
                WHERE reparto_id = ?
                ORDER BY orden ASC
            ");
            $stmt->execute([$reparto_id]);
            $reglas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($reglas)) {

                $stmtInsert = $pdo->prepare("
                    INSERT INTO cobros_reparto 
                    (cobro_id, destino_id, monto)
                    VALUES (?, ?, ?)
                ");

                $resto = $monto;

                // 💰 FIJOS
                foreach ($reglas as $r) {
                    if ((int)$r['tipo_id'] === 2) {
                        $montoFijo = (float)$r['valor'];
                        $stmtInsert->execute([$cobro_id, $r['destino_id'], $montoFijo]);
                        $resto -= $montoFijo;
                    }
                }

                if ($resto < 0) $resto = 0;

                // 📊 PORCENTAJES
                foreach ($reglas as $r) {
                    if ((int)$r['tipo_id'] === 1) {
                        $montoPorcentaje = ($resto * (float)$r['valor']) / 100;
                        $stmtInsert->execute([$cobro_id, $r['destino_id'], $montoPorcentaje]);
                    }
                }
            }
        }
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'cobro_id' => $cobro_id,
        'numero' => $numeroCompleto
    ]);

} catch (Exception $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}