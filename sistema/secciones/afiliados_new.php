<?php
require_once __DIR__ . '/../inc/db.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

/* =========================
   ULTIMO MES PAGADO
========================= */
$stmt = $conexion->prepare("
    SELECT 
        MONTH(fecha_correspondiente) AS mes,
        YEAR(fecha_correspondiente)  AS anio
    FROM pagos_afiliados
    WHERE paciente_id = :id
    ORDER BY fecha_correspondiente DESC
    LIMIT 1
");
$stmt->execute([':id' => $id]);
$ultimoPago = $stmt->fetch(PDO::FETCH_ASSOC);

/* =========================
   DATOS PACIENTE
========================= */
$stmtPaciente = $conexion->prepare("
    SELECT 
        os.obra_social,
        p.*
    FROM pacientes p
    LEFT JOIN obras_sociales os ON os.Id = p.obra_social_id
    WHERE p.Id = :id
");
$stmtPaciente->execute([':id' => $id]);
$paciente = $stmtPaciente->fetch(PDO::FETCH_ASSOC);

$meses = [
    'Enero',
    'Febrero',
    'Marzo',
    'Abril',
    'Mayo',
    'Junio',
    'Julio',
    'Agosto',
    'Septiembre',
    'Octubre',
    'Noviembre',
    'Diciembre'
];
?>


<!-- ========== DATOS DEL PACIENTE (arriba, ancho completo) ========== -->
<div class="card card-info card-outline mb-3">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-user mr-1"></i> Datos del paciente</h3>

    </div>
    <div class="card-body py-2">
        <?php if ($paciente): ?>
            <div class="row">
                <div class="col-md-3">
                    <small class="text-muted d-block">Apellido y nombre</small>
                    <strong><?= htmlspecialchars($paciente['apellido']) ?>, <?= htmlspecialchars($paciente['nombre']) ?></strong>
                </div>
                <div class="col-md-2">
                    <small class="text-muted d-block">N° socio</small>
                    <strong><?= htmlspecialchars($paciente['nro_afiliado']) ?></strong>
                </div>
                <div class="col-md-2">
                    <small class="text-muted d-block">Documento</small>
                    <?= htmlspecialchars($paciente['tipo_documento']) ?> <?= htmlspecialchars($paciente['documento']) ?>
                </div>
                <div class="col-md-2">
                    <small class="text-muted d-block">Celular</small>
                    <?= htmlspecialchars($paciente['celular']) ?>
                </div>
                <div class="col-md-2">
                    <small class="text-muted d-block">Obra social</small>
                    <?= htmlspecialchars($paciente['obra_social']) ?>
                </div>
                <div class="col-md-1">
                    <small class="text-muted d-block">Localidad</small>
                    <?= htmlspecialchars($paciente['localidad']) ?>
                </div>
            </div><br>
        <?php else: ?>
            <div class="alert alert-warning mb-0">Paciente no encontrado</div>
        <?php endif; ?>
        <?php if ($ultimoPago): ?>
            <div class="card-tools">
                <span class="badge badge-info">
                    Último pago: <?= $meses[$ultimoPago['mes'] - 1] . ' ' . $ultimoPago['anio'] ?>
                </span>
            </div>
        <?php else: ?>
            <div class="card-tools">
                <span class="badge badge-warning">Sin pagos registrados</span>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ========== FILA: INPUTS (izq) + CARRITO (der) ========== -->
<div class="row">

    <!-- INPUTS -->
    <div class="col-md-4">
        <div class="card card-info card-outline">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-plus-circle mr-1"></i> Agregar mes</h3>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label>Monto</label>
                    <input type="number" id="monto" class="form-control" min="0" step="0.01" placeholder="0.00">
                </div>
                <div class="form-group">
                    <label>Mes a abonar</label>
                    <?php
                    if ($ultimoPago) {
                        $siguiente = mktime(0, 0, 0, $ultimoPago['mes'] + 1, 1, $ultimoPago['anio']);
                        $valorFecha = date('Y-m', $siguiente);
                    } else {
                        $valorFecha = date('Y-m');
                    }
                    ?>
                    <input type="month" id="fecha" class="form-control" value="<?= $valorFecha ?>">
                </div>
                <button type="button" id="agregarMes" class="btn btn-primary btn-block">
                    <i class="fas fa-plus mr-1"></i> Agregar al carrito
                </button>
            </div>
        </div>
    </div>

    <!-- CARRITO -->
    <div class="col-md-8">
        <div class="card card-info card-outline">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-shopping-cart mr-1"></i> Carrito de pagos</h3>
                <span id="carritoCount" class="badge badge-secondary ">0 ítems</span>
            </div>
            <div class="card-body p-0">

                <div id="carritoEmpty" class="text-center text-muted py-5">
                    <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                    <span>Agregá meses para comenzar</span>
                </div>

                <table class="table table-sm table-hover mb-0" id="carritoTable" style="display:none;">
                    <thead>
                        <tr>
                            <th>Mes / Año</th>
                            <th class="text-right">Monto</th>
                            <th width="40"></th>
                        </tr>
                    </thead>
                    <tbody id="carritoBody"></tbody>
                    <tfoot>
                        <tr class="bg-light">
                            <th>TOTAL</th>
                            <th class="text-right" id="totalCarrito">$0,00</th>
                            <th></th>
                        </tr>
                    </tfoot>
                </table>

            </div>
            <div class="card-footer text-right">
                <button type="button" id="guardarPagos" class="btn btn-success" disabled>
                    <i class="fas fa-save mr-1"></i> Guardar pagos
                </button>
            </div>
        </div>
    </div>

</div>


<!-- ================= JS CARRITO + TICKET ================= -->
<script>
    document.addEventListener("DOMContentLoaded", function() {

        if (typeof qz === "undefined") {
            console.error("QZ no está cargado");
            return;
        }

        qz.security.setCertificatePromise(function(resolve, reject) {
            fetch("certificado/certificate.pem")
                .then(res => res.text())
                .then(resolve)
                .catch(reject);
        });

        qz.security.setSignaturePromise(function(toSign) {
            return function(resolve, reject) {
                fetch("certificado/firma.php", {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json"
                        },
                        body: JSON.stringify({
                            request: toSign
                        })
                    })
                    .then(res => res.text())
                    .then(resolve)
                    .catch(reject);
            };
        });

    });
    (function() {
        const pacienteId = <?= $id ?>;
        const nroSocio = <?= json_encode($paciente['nro_afiliado'] ?? '') ?>;

        let carrito = [];

        const meses = [
            'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
            'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
        ];

        /* ---- helpers ---- */
        function formatMoney(n) {
            return '$' + n.toLocaleString('es-AR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        function labelFecha(ym) {
            const [y, m] = ym.split('-');
            return meses[parseInt(m) - 1] + ' ' + y;
        }

        function renderCarrito() {
            const tbody = document.getElementById('carritoBody');
            const empty = document.getElementById('carritoEmpty');
            const table = document.getElementById('carritoTable');
            const total = document.getElementById('totalCarrito');
            const count = document.getElementById('carritoCount');
            const btnSave = document.getElementById('guardarPagos');

            tbody.innerHTML = '';

            if (carrito.length === 0) {
                empty.style.display = '';
                table.style.display = 'none';
                total.textContent = '$ 0,00';
                count.textContent = '0 ítems';
                btnSave.disabled = true;
                return;
            }

            empty.style.display = 'none';
            table.style.display = '';
            let suma = 0;

            carrito.forEach((item, idx) => {
                suma += item.monto;
                const tr = document.createElement('tr');
                tr.innerHTML = `
                <td>${item.label}</td>
                <td class="text-right">${formatMoney(item.monto)}</td>
                <td class="text-center">
                    <button class="btn btn-xs btn-danger" data-idx="${idx}" title="Quitar">
                        <i class="fas fa-times"></i>
                    </button>
                </td>
            `;
                tbody.appendChild(tr);
            });

            total.textContent = formatMoney(suma);
            count.textContent = carrito.length + (carrito.length === 1 ? ' ítem' : ' ítems');
            btnSave.disabled = false;
        }

        /* ---- agregar al carrito ---- */
        document.getElementById('agregarMes').addEventListener('click', function() {
            const monto = parseFloat(document.getElementById('monto').value);
            const fecha = document.getElementById('fecha').value;

            if (!monto || monto <= 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Ingresá un monto válido',
                    timer: 1800,
                    showConfirmButton: false
                });
                return;
            }
            if (!fecha) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Seleccioná un mes',
                    timer: 1800,
                    showConfirmButton: false
                });
                return;
            }
            if (carrito.some(i => i.fecha === fecha)) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Ese mes ya está en el carrito',
                    timer: 1800,
                    showConfirmButton: false
                });
                return;
            }

            // Verificar si ya está pagado en la base de datos
            const btnAgregar = document.getElementById('agregarMes');
            btnAgregar.disabled = true;
            btnAgregar.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Verificando...';

            fetch(`ajax/verificar_mes_afiliado.php?paciente_id=<?= $id ?>&fecha=${fecha}`)
                .then(r => r.json())
                .then(data => {
                    btnAgregar.disabled = false;
                    btnAgregar.innerHTML = '<i class="fas fa-plus mr-1"></i> Agregar al carrito';

                    if (data.pagado) {
                        const meses_es = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
                        const [anio, mes] = fecha.split('-');
                        const mesNombre = meses_es[parseInt(mes) - 1] + ' ' + anio;

                        const filas = data.lista.map(p => {
                            const fechaPago = new Date(p.fecha_pago).toLocaleDateString('es-AR');
                            return `<tr>
                            <td style="padding:4px 8px;">${p.apellido}, ${p.nombre}</td>
                            <td style="padding:4px 8px;">${p.nro_afiliado}</td>
                            <td style="padding:4px 8px;">$${parseFloat(p.monto).toLocaleString('es-AR',{minimumFractionDigits:2})}</td>
                            <td style="padding:4px 8px;">${fechaPago}</td>
                        </tr>`;
                        }).join('');

                        Swal.fire({
                            icon: 'warning',
                            title: `${mesNombre} ya fue abonado`,
                            html: `
                            <p style="margin-bottom:10px;font-size:14px;">Los siguientes socios ya tienen registrado ese mes:</p>
                            <table style="width:100%;border-collapse:collapse;font-size:13px;text-align:left;">
                                <thead>
                                    <tr style="background:#f0f0f0; color:#333;">
                                        <th style="padding:5px 8px;">Socio</th>
                                        <th style="padding:5px 8px;">N° socio</th>
                                        <th style="padding:5px 8px;">Monto</th>
                                        <th style="padding:5px 8px;">Fecha pago</th>
                                    </tr>
                                </thead>
                                <tbody>${filas}</tbody>
                            </table>
                        `,
                            showCancelButton: true,
                            confirmButtonText: 'Agregar igual',
                            cancelButtonText: 'Cancelar',
                            confirmButtonColor: '#e67e22',
                            width: 560,
                        }).then(result => {
                            if (result.isConfirmed) agregarAlCarrito(fecha, monto);
                        });

                    } else {
                        agregarAlCarrito(fecha, monto);
                    }
                })
                .catch(() => {
                    btnAgregar.disabled = false;
                    btnAgregar.innerHTML = '<i class="fas fa-plus mr-1"></i> Agregar al carrito';
                    agregarAlCarrito(fecha, monto);
                });
        });

        function agregarAlCarrito(fecha, monto) {
            carrito.push({
                fecha,
                label: labelFecha(fecha),
                monto
            });
            renderCarrito();

            const [y, m] = fecha.split('-').map(Number);
            const next = new Date(y, m, 1);
            const ny = next.getFullYear();
            const nm = String(next.getMonth() + 1).padStart(2, '0');
            document.getElementById('fecha').value = `${ny}-${nm}`;
            document.getElementById('monto').value = '';
            document.getElementById('monto').focus();
        }

        /* ---- borrar del carrito ---- */
        document.getElementById('carritoBody').addEventListener('click', function(e) {
            const btn = e.target.closest('button[data-idx]');
            if (!btn) return;
            carrito.splice(parseInt(btn.dataset.idx), 1);
            renderCarrito();
        });

        /* ====================================================
           TICKET TÉRMICO
        ==================================================== */
        async function imprimirTicketAfiliado(afiliados, items, total) {
            try {
                if (!qz.websocket.isActive()) {
                    await qz.websocket.connect();
                }
            } catch (connErr) {
                console.warn('QZ Tray no disponible, no se imprimirá el ticket:', connErr);
                Swal.fire({
                    icon: 'warning',
                    title: 'Impresora no disponible',
                    text: 'El pago se guardó correctamente pero no se pudo conectar con QZ Tray para imprimir.',
                    confirmButtonText: 'Aceptar'
                });
                return;
            }

            try {

                const config = qz.configs.create("POS-80C", {
                    encoding: "CP437"
                });

                const LINE = 48;

                function center(txt) {
                    return "\x1B\x61\x01" + txt + "\n";
                }

                function left(txt) {
                    return "\x1B\x61\x00" + txt + "\n";
                }

                function right(txt) {
                    return "\x1B\x61\x02" + txt + "\n";
                }

                function sep() {
                    return "------------------------------------------------\n";
                }

                function linea(label, valor) {
                    label = String(label);
                    valor = String(valor);

                    const espacios = Math.max(
                        1,
                        LINE - label.length - valor.length
                    );

                    return label + " ".repeat(espacios) + valor + "\n";
                }

                const ahora = new Date();

                const fecha =
                    ahora.toLocaleDateString("es-AR");

                const hora =
                    ahora.toLocaleTimeString("es-AR");

                let c = [];

                /* =====================================
                   RESET
                ===================================== */

                c.push("\x1B\x40");

                /* =====================================
                   CABECERA
                ===================================== */

                c.push("\x1B\x45\x01");
                c.push("\x1D\x21\x11");
                c.push(center("SALA RIVADAVIA"));
                c.push("\x1D\x21\x00");
                c.push("\x1B\x45\x00");

                c.push(center("RECIBO DE PAGO"));
                c.push(center("SOCIOS"));

                c.push(sep());

                c.push(center("CUIT: 30-54589575-3"));
                c.push(center("IVA RESPONSABLE INSCRIPTO"));

                c.push("\n");

                c.push(center("AV. EVA PERON 695"));
                c.push(center("TEMPERLEY (1834)"));
                c.push(center("BUENOS AIRES"));

                c.push("\n");

                c.push(center("TEL: 3989-4325"));
                c.push(center("TEL: 3991-2183"));

                c.push(sep());

                /* =====================================
                   DATOS DEL RECIBO
                ===================================== */

                c.push(left("FECHA : " + fecha));
                c.push(left("HORA  : " + hora));

                c.push(sep());

                /* =====================================
                   NUMERO DE SOCIO
                ===================================== */

                c.push("\x1B\x45\x01");
                c.push(center("N° DE SOCIO"));
                c.push(center(nroSocio));
                c.push("\x1B\x45\x00");

                c.push("\n");

                /* =====================================
                   SOCIO INCLUIDO
                ===================================== */

                c.push(left("SOCIOS INCLUIDOS"));
                c.push("\n");

                afiliados.forEach(af => {

                    let nombre =
                        `${af.apellido}, ${af.nombre}`;

                    if (nombre.length > 40) {
                        nombre = nombre.substring(0, 40);
                    }

                    c.push(left("• " + nombre));
                });

                c.push(sep());

                /* =====================================
                   CUOTAS ABONADAS
                ===================================== */

                c.push(left("CUOTAS ABONADAS"));
                c.push("\n");

                items.forEach(item => {

                    const importe =
                        parseFloat(item.monto)
                        .toLocaleString("es-AR", {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        });

                    c.push(
                        linea(
                            item.label,
                            "$ " + importe
                        )
                    );
                });

                c.push(sep());

                /* =====================================
                   TOTAL
                ===================================== */

                c.push("\x1B\x45\x01");
                c.push("\x1D\x21\x11");

                c.push(center("TOTAL"));

                c.push(
                    center(
                        "$ " +
                        parseFloat(total)
                        .toLocaleString("es-AR", {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        })
                    )
                );

                c.push("\x1D\x21\x00");
                c.push("\x1B\x45\x00");

                c.push(sep());

                /* =====================================
                   MENSAJE
                ===================================== */

                c.push("\n");

                c.push(center("COMPROBANTE VALIDO"));
                c.push(center("COMO RECIBO DE PAGO"));

                c.push("\n");

                c.push(center("¡GRACIAS POR ELEGIRNOS!"));

                c.push("\n\n\n");

                /* =====================================
                   CORTE
                ===================================== */

                c.push("\x1D\x56\x00");

                await qz.print(config, c);

            } catch (printErr) {

                console.error(printErr);

                Swal.fire({
                    icon: 'error',
                    title: 'Error al imprimir',
                    text: printErr.toString()
                });
            }
        }

        /* ---- guardar pagos ---- */
        document.getElementById('guardarPagos').addEventListener('click', function() {
            if (carrito.length === 0) return;

            const totalCarrito = carrito.reduce((s, i) => s + i.monto, 0);

            // Traer afiliados del mismo nro de socio antes de mostrar el Swal
            fetch('ajax/preview_pago_masivo.php?paciente_id=<?= $id ?>')
                .then(r => r.json())
                .catch(() => [])
                .then(afiliados => {

                    const filasMeses = carrito.map(item => `
                    <tr>
                        <td style="text-align:left; padding:4px 8px;">${item.label}</td>
                        <td style="text-align:right; padding:4px 8px;">${formatMoney(item.monto)}</td>
                    </tr>`).join('');

                    const filasAfiliados = afiliados.length ?
                        afiliados.map(af => `
                        <tr>
                            <td style="padding:3px 8px;">${af.apellido}, ${af.nombre}</td>
                            <td style="padding:3px 8px;  font-size:12px;">${af.nro_afiliado}</td>
                        </tr>`).join('') :
                        '<tr><td colspan="2" style="padding:4px 8px; color:#999;">Sin afiliados encontrados</td></tr>';

                    const previewHtml = `
                    <div style="font-size:14px; text-align:left;">
                        <p style="font-weight:600; margin-bottom:4px;">Socios alcanzados:</p>
                        <table style="width:100%; border-collapse:collapse; margin-bottom:12px;">
                            <thead>
                                <tr thead style="background:#f0f0f0; color:#333;">
                                    <th style="text-align:left; padding:5px 8px;">Apellido y nombre</th>
                                    <th style="text-align:left; padding:5px 8px;">N° socio</th>
                                </tr>
                            </thead>
                            <tbody>${filasAfiliados}</tbody>
                        </table>
                        <p style="font-weight:600; margin-bottom:4px;">Meses a cobrar:</p>
                        <table style="width:100%; border-collapse:collapse;">
                            <thead>
                                <tr style="background:#f0f0f0; color:#333;">
                                    <th style="text-align:left; padding:5px 8px;">Mes</th>
                                    <th style="text-align:right; padding:5px 8px;">Monto</th>
                                </tr>
                            </thead>
                            <tbody>${filasMeses}</tbody>
                            <tfoot>
                                <tr style="border-top:2px solid #333; font-weight:bold;">
                                    <td style="padding:6px 8px;">TOTAL</td>
                                    <td style="text-align:right; padding:6px 8px;">${formatMoney(totalCarrito)}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                `;

                    Swal.fire({
                        title: 'Confirmar pago',
                        html: previewHtml,
                        icon: 'question',
                        showCancelButton: true,
                        showDenyButton: true,
                        confirmButtonText: '<i class="fas fa-print"></i> Guardar e imprimir',
                        denyButtonText: '<i class="fas fa-save"></i> Guardar sin imprimir',
                        cancelButtonText: 'Cancelar',
                        confirmButtonColor: '#28a745',
                        denyButtonColor: '#17a2b8',
                        width: 520,
                    }).then(result => {
                        if (result.isDismissed) return;

                        const debeImprimir = result.isConfirmed; // true = imprimir, false = no imprimir

                        fetch('ajax/guardar_pagos_afiliado.php?paciente_id=<?= $id ?>', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json'
                                },
                                body: JSON.stringify({
                                    items: carrito
                                })
                            })
                            .then(r => {
                                if (!r.ok) throw new Error('HTTP ' + r.status);
                                return r.json();
                            })
                            .then(async data => {
                                if (data.ok) {
                                    if (debeImprimir) {
                                        await imprimirTicketAfiliado(data.afiliados || [], carrito, totalCarrito);
                                    }
                                    Swal.fire({
                                        icon: 'success',
                                        title: data.msg,
                                        timer: 2000,
                                        showConfirmButton: false
                                    }).then(() => location.reload());
                                } else {
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'Error',
                                        text: data.msg
                                    });
                                }
                            })
                            .catch(err => {
                                console.error(err);
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error de conexión',
                                    text: 'No se pudo comunicar con el servidor. Verificá tu conexión e intentá de nuevo.'
                                });
                            });
                    }); // cierre .then(afiliados =>
                }); // cierre fetch
        });

        renderCarrito();
    })();
</script>