<?php
require_once __DIR__ . '/../inc/db.php';

$rand = rand(1, 9999);

$laboratorio = $pdo->query("
    SELECT estudio, valor
    FROM estudio_lab
    ORDER BY estudio ASC
")->fetchAll(PDO::FETCH_ASSOC);
?>
<style>
    .item-estudio {
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .item-estudio:hover {
        transform: scale(1.02);
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }

    .item-estudio.activo {
        border: 2px solid #17a2b8;
        background: #e8f7fa;
    }

    .item-estudio strong {
        font-size: 14px;
    }
</style>
<div class="row">
    <div class="col-12">

        <div class="card card-info card-outline">

            <!-- HEADER -->
            <div class="card-header d-flex  align-items-center">
                <h3 class="card-title">
                    <i class="fas fa-flask"></i> Laboratorio
                    <a href="./?seccion=estudio_lab_new&nc=<?= $rand ?>" class="btn btn-info btn-sm">
                        <i class="fa fa-plus"></i> Agregar
                    </a>

                    <a href="./?seccion=estudio_lab_listedit&nc=<?= $rand ?>" class="btn btn-success btn-sm">
                        <i class="fa fa-pencil-alt"></i> Editar
                    </a>
                </h3>

            </div>

            <!-- BODY -->
            <div class="card-body">

                <!-- TOTAL -->
                <div class="alert alert-primary d-flex justify-content-between align-items-center">
                    <div>
                        <strong>Total seleccionado:</strong>
                        <span id="suma" class="ml-2 font-weight-bold">$ 0</span>
                    </div>

                    <div>
                        <button id="btn_resumen" class="btn btn-info btn-sm">
                            <i class="fa fa-calculator"></i> Ver resumen

                            <button id="btn_limpiar" class="btn btn-danger btn-sm">
                                <i class="fa fa-times"></i> Limpiar
                            </button>
                    </div>
                </div>
                <div class="mb-3">
                    <input type="text" id="buscador_estudios" class="form-control" placeholder="🔎 Buscar estudio...">
                </div>
                <!-- LISTA -->
                <div class="row">

                    <?php foreach ($laboratorio as $row): ?>

                        <div class="col-md-3 mb-2">
                            <div class="card card-outline card-secondary item-estudio">

                                <div class="card-body p-2 d-flex align-items-center">

                                    <div class="d-flex align-items-center flex-grow-1">
                                        <input type="checkbox" class="checkbox_lab mr-2" value="<?= $row['valor'] ?>">
                                        <strong><?= htmlspecialchars($row['estudio']) ?></strong>
                                    </div>

                                    <span class="badge badge-secondary ml-auto">
                                        $ <?= number_format($row['valor'], 2, ',', '.') ?>
                                    </span>

                                </div>

                            </div>
                        </div>

                    <?php endforeach; ?>

                </div>

            </div>

        </div>
    </div>
</div>



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
    $(document).ready(function() {

        function calcularTotal() {

            let suma = 0;

            $('.checkbox_lab:checked').each(function() {
                suma += parseFloat($(this).val()) || 0;
            });

            $('#suma').text('$ ' + suma.toLocaleString('es-AR'));
        }

        // =========================
        // CLICK EN CARD
        // =========================
        $('.item-estudio').click(function(e) {

            if (e.target.tagName !== 'INPUT') {
                let checkbox = $(this).find('.checkbox_lab');
                checkbox.prop('checked', !checkbox.prop('checked')).trigger('change');
            }

        });

        // =========================
        // SELECCION VISUAL
        // =========================
        $('.checkbox_lab').change(function() {

            let card = $(this).closest('.item-estudio');

            if ($(this).is(':checked')) {
                card.addClass('activo');
            } else {
                card.removeClass('activo');
            }

            calcularTotal();
        });

        // =========================
        // BUSCADOR EN TIEMPO REAL
        // =========================
        $('#buscador_estudios').on('keyup', function() {

            let texto = $(this).val().toLowerCase();

            $('.item-estudio').each(function() {

                let nombre = $(this).find('strong').text().toLowerCase();

                if (nombre.includes(texto)) {
                    $(this).parent().show(); // col-md-3
                } else {
                    $(this).parent().hide();
                }

            });

        });

        // =========================
        // LIMPIAR
        // =========================
        $('#btn_limpiar').click(function() {

            $('.checkbox_lab').prop('checked', false);
            $('.item-estudio').removeClass('activo');
            calcularTotal();

        });

        // =========================
        // RESUMEN
        // =========================
        $('#btn_resumen').click(function() {

            let seleccionados = [];
            let total = 0;

            $('.checkbox_lab:checked').each(function() {

                let nombre = $(this).closest('.item-estudio').find('strong').text();
                let valor = parseFloat($(this).val());

                total += valor;

                seleccionados.push({
                    nombre: nombre,
                    valor: valor
                });

            });

            if (!seleccionados.length) {
                Swal.fire('Atención', 'No seleccionaste estudios', 'warning');
                return;
            }

            // =========================
            // HTML RESUMEN
            // =========================
            let html = `
        <div style="text-align:left;">
    `;

            seleccionados.forEach(item => {
                html += `
        <div style="display:flex; justify-content:space-between;">
            <span>${item.nombre}</span>
            <strong>$ ${item.valor.toLocaleString('es-AR')}</strong>
        </div>
        `;
            });

            html += `
        <hr>
        <div style="display:flex; justify-content:space-between; font-size:16px;">
            <strong>Total</strong>
            <strong>$ ${total.toLocaleString('es-AR')}</strong>
        </div>
    </div>
    `;

            // =========================
            // SWEET ALERT CON BOTÓN IMPRIMIR
            // =========================
            Swal.fire({
                title: 'Resumen de estudios',
                html: html,
                width: 500,
                showCancelButton: true,
                confirmButtonText: 'Imprimir ticket 🧾',
                cancelButtonText: 'Cerrar',
                confirmButtonColor: '#3498DB'
            }).then(result => {

                if (result.isConfirmed) {

                    imprimirTicketLab(seleccionados, total);

                }

            });

        });

        async function imprimirTicketLab(items, total) {

            try {

                if (!qz.websocket.isActive()) {
                    await qz.websocket.connect();
                }

                const config = qz.configs.create("POS-80C", {
                    encoding: 'CP437'
                });

                let contenido = [];

                function linea(nombre, valor) {

                    let left = nombre.substring(0, 30);
                    let right = "$" + parseFloat(valor).toFixed(2);

                    let spaces = 48 - (left.length + right.length);
                    if (spaces < 1) spaces = 1;

                    return left + " ".repeat(spaces) + right;
                }

              /* ENCABEZADO CON LOGO */
            contenido.push("\x1B\x61\x01"); // Centrado

            // 1. Insertar la imagen (puede ser URL o Base64)
            contenido.push({
                type: 'pixel',
                format: 'png', // o 'png'
                flavor: 'file',
                data: 'images/logo_blanco_negro.png', // Ruta relativa, absoluta o base64
                options: {
                    language: "ESCPOS",
                    dotDensity: "double"
                }
            });

            //             const logo = {
            //    type: 'pixel',
            //    format: 'png',
            //    flavor: 'file',
            //    data: 'https://tuweb.com/logo.png',
            //    options: { 
            //       language: "ESCPOS", 
            //       dotDensity: "double",
            //       width: 200 // Ajusta el tamaño en píxeles según tu papel de 80mm
            //    }
            // };

            // // Luego en tu función:
            // let contenido = [logo, "\x1B\x61\x01", "SALA RIVADAVIA\n", ...];

            contenido.push("\n"); // Salto de línea después del logo
            // contenido.push("\x1B\x61\x01");
            contenido.push("SALA RIVADAVIA\n");
                contenido.push("LABORATORIO\n");
                contenido.push("Av. Eva Perón 695\n");
                contenido.push("Temperley\n");
                contenido.push("Fecha: " + new Date().toLocaleString() + "\n");
                contenido.push("------------------------------------------\n");

                /* ================= DETALLE ================= */
                contenido.push("\x1B\x61\x00");

                items.forEach(i => {
                    contenido.push(linea(i.nombre, i.valor) + "\n");
                });

                contenido.push("------------------------------------------\n");

                /* ================= TOTAL ================= */
                contenido.push("\x1B\x61\x02");
                contenido.push("TOTAL: $" + parseFloat(total).toFixed(2) + "\n");

                contenido.push("\x1B\x61\x01");
                contenido.push("\nGracias por su visita\n");

                /* ================= CORTE ================= */
                contenido.push("\n\n\n");
                contenido.push("\x1D\x56\x00");

                await qz.print(config, contenido);

            } catch (err) {
                console.error("ERROR IMPRESIÓN LAB:", err);
                Swal.fire("Error", "No se pudo imprimir", "error");
            }
        }
    });
</script>