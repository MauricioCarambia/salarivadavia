<?php
require_once __DIR__ . '/../inc/db.php';
if (isset($_GET['accion']) && $_GET['accion'] === 'getHC') {

    header('Content-Type: application/json');

    $hcId = (int) $_GET['id'];

    $stmt = $pdo->prepare("SELECT * FROM historias_clinicas WHERE Id = :id");
    $stmt->execute([':id' => $hcId]);

    echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
    exit;
}
$profesionalId = $_SESSION['user_id'] ?? 0;
$tipoUsuario = $_SESSION['tipo'] ?? '';
$id = (int) ($_GET['id'] ?? 0);

if (!$id) {
    die('<div class="alert alert-danger">Paciente inválido</div>');
}


/* ================= PACIENTE ================= */
$stmt = $pdo->prepare("SELECT * FROM pacientes WHERE Id = :id");
$stmt->execute([':id' => $id]);
$pacienteData = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pacienteData) {
    die('<div class="alert alert-danger">Paciente no encontrado</div>');
}
$pacienteNombre = $pacienteData['apellido'] . ' ' . $pacienteData['nombre'];
/* ================= HISTORIAS ================= */
$stmtHC = $pdo->prepare("
    SELECT hc.*, 
           p.firma AS profesionalfirma, 
           p.apellido AS profesionalapellido,
           p.nombre AS profesionalnombre, 
           p.matricula_nacional, 
           p.matricula_provincial,
           e.especialidad
    FROM historias_clinicas hc
    LEFT JOIN profesionales p ON hc.profesional_id = p.Id
    LEFT JOIN especialidades e ON p.especialidad_id = e.Id
    WHERE hc.paciente_id = :pid
    ORDER BY hc.fecha DESC 
");
$stmtHC->execute([':pid' => $id]);
$historias = $stmtHC->fetchAll(PDO::FETCH_ASSOC);
function mostrarCampo($label, $valor)
{
    if (empty(trim(strip_tags($valor))))
        return '';

    $iconos = [
        'Motivo' => 'fa-comment-medical',
        'Síntomas' => 'fa-thermometer-half',
        'Signos vitales' => 'fa-heartbeat',
        'Exámenes' => 'fa-file-medical',
        'Diagnóstico' => 'fa-stethoscope',
        'Medicación' => 'fa-pills',
        'Observaciones' => 'fa-notes-medical'
    ];

    $icono = $iconos[$label] ?? 'fa-circle';

    return "<div class='hc-row'>
                <div class='hc-label'>
                    <i class='fas {$icono}'></i> {$label}:
                </div>
                <div class='hc-value'>{$valor}</div>
            </div>";
}
?>

<div class="card card-info card-outline">
    <div class="card-header d-flex align-items-center ">
        <h3 class="card-title"><i class="fas fa-notes-medical"></i> Historia Clínica</h3>
        <div>
            <?php if ($tipoUsuario === 'profesional'): ?>
                <a href="./?seccion=historia_clinica_new&paciente_id=<?= $id ?>" class="btn btn-success btn-sm ml-2">
                    <i class="fa fa-plus"></i> Nueva HC
                </a>
            <?php endif; ?>
            <button class="btn btn-warning btn-sm ml-2" onclick="imprimirHC()">
                <i class="fa fa-print"></i> Imprimir
            </button>
            <!-- <a class="btn btn-info btn-sm ml-2" href="secciones/pdf_historia_clinica.php?id=<?= $id ?>" target="_blank">
                PDF
            </a> -->
        </div>
    </div>

    <div class="card-body">
        <!-- INFO PACIENTE -->
        <div class="row mb-3">
            <div class="col-12">
                <div class="card card-info card-outline">
                    <div class="card-header ">
                        <h3 class="card-title ml-2"> Datos del paciente</h3>
                    </div>
                    <div class="card-header d-flex align-items-center ">
                        <div class="col-md-3"><b>Paciente:</b><br><?= htmlspecialchars($pacienteNombre) ?></div>
                        <div class="col-md-2"><b>DNI:</b><br><?= htmlspecialchars($pacienteData['documento']) ?></div>
                        <div class="col-md-2">
                            <b>Nacimiento:</b><br><?= $pacienteData['nacimiento'] ? date('d/m/Y', strtotime($pacienteData['nacimiento'])) : '-' ?>
                        </div>
                        <div class="col-md-2"><b>Celular:</b><br><?= htmlspecialchars($pacienteData['celular']) ?></div>
                        <div class="col-md-2"><b>HC:</b><br><span
                                class="badge badge-info"><?= htmlspecialchars($pacienteData['historia_clinica']) ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>


        <?php if ($tipoUsuario !== 'profesional'): ?>
            <div class="alert alert-warning"><i class="fa fa-lock"></i> Solo los profesionales pueden agregar o editar
                historias clínicas.</div>
        <?php endif; ?>

        <div class="table-responsive">
            <table id="tablaHC" class="table table-bordered table-hover table-sm datatable">
                <thead class="thead-dark">
                    <tr>
                        <th style="width:50%">Consulta</th>
                        <th style="width:25%">Profesional</th>
                        <th style="width:15%" class="text-center">Firma</th>
                        <?php if ($tipoUsuario === 'profesional'): ?>
                            <th style="width:1%" class="text-center">Acciones</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($historias)): ?>
                        <?php foreach ($historias as $hc): ?>
                            <tr>
                                <td>
                                    <div class="hc-item">
                                        <?= mostrarCampo('Motivo', $hc['motivo'] ?? '') ?>
                                        <?= mostrarCampo('Síntomas', $hc['sintomas'] ?? '') ?>
                                        <?= mostrarCampo('Signos vitales', $hc['vitales'] ?? '') ?>
                                        <?= mostrarCampo('Exámenes', $hc['examenes'] ?? '') ?>
                                        <?= mostrarCampo('Diagnóstico', $hc['diagnostico'] ?? '') ?>
                                        <?= mostrarCampo('Medicación', $hc['medicamento'] ?? '') ?>
                                        <?= mostrarCampo('Observaciones', $hc['texto'] ?? '') ?>
                                    </div>
                                </td>
                                <td class="text-center align-middle">
                                    <span data-order="<?= $hc['fecha'] ?>">
                                        <b><?= date('d/m/Y', strtotime($hc['fecha'])) ?></b>
                                    </span><br>
                                    <?= htmlspecialchars($hc['profesionalapellido'] . ' ' . $hc['profesionalnombre']) ?><br>
                                    <span class="badge badge-info"><?= htmlspecialchars($hc['especialidad']) ?></span><br>
                                    <small>MN: <?= $hc['matricula_nacional'] ?> | MP: <?= $hc['matricula_provincial'] ?></small>
                                </td>
                                <td class="text-center align-middle">
                                    <?php if (!empty($hc['profesionalfirma'])): ?><img
                                            src="<?= htmlspecialchars($hc['profesionalfirma']) ?>"
                                            style="width:150px"><?php endif; ?>
                                </td>
                                <?php if ($tipoUsuario === 'profesional'): ?>
                                    <td class="text-center align-middle">
                                        <?php if ($hc['profesional_id'] == $profesionalId): ?>
                                            <button class="btn btn-success btn-sm" onclick="abrirHCModal(<?= (int) $hc['Id'] ?>)">
                                                <i class="fa fa-edit"></i>
                                            </button>
                                            <button class="btn btn-danger btn-sm" onclick="eliminarHC(<?= $hc['Id'] ?>)">
                                                <i class="fa fa-trash"></i>
                                            </button>
                                        <?php else: ?>
                                            <!-- celda vacía pero EXISTE -->
                                            &nbsp;
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td class="text-center">
                                <div class="alert alert-warning m-2">No hay registros de historia clínica</div>
                            </td>
                            <td></td>
                            <td></td>
                            <?php if ($tipoUsuario === 'profesional'): ?>
                                <td></td>
                            <?php endif; ?>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="hcModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">

            <form id="formEditarHC">

                <div class="modal-header bg-info">
                    <h5 class="modal-title">Editar Historia Clínica</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>

                <div class="modal-body">

                    <input type="hidden" name="id" id="hc_id">

                    <div class="row">


                        <div class="col-md-12">
                            <label>Motivo</label>
                            <input type="text" name="motivo" id="hc_motivo" class="form-control">
                        </div>
                    </div>

                    <div class="form-group mt-2">
                        <label>Síntomas</label>
                        <input type="text" name="sintomas" id="hc_sintomas" class="form-control">
                    </div>

                    <div class="form-group">
                        <label>Signos Vitales</label>
                        <input type="text" name="vitales" id="hc_vitales" class="form-control">
                    </div>

                    <div class="form-group">
                        <label>Exámenes</label>
                        <input type="text" name="examenes" id="hc_examenes" class="form-control">
                    </div>

                    <div class="form-group">
                        <label>Diagnóstico</label>
                        <input type="text" name="diagnostico" id="hc_diagnostico" class="form-control">
                    </div>

                    <div class="form-group">
                        <label>Medicación</label>
                        <input type="text" name="medicamento" id="hc_medicamento" class="form-control">
                    </div>

                    <div class="form-group">
                        <label>Observaciones</label>
                        <div id="editorHCModal" style="height: 350px;"></div>
                        <input type="hidden" name="texto" id="hc_texto">
                    </div>

                </div>

                <div class="modal-footer">
                    <button class="btn btn-success">Guardar</button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                </div>

            </form>

        </div>
    </div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
    let quillModal;

    $('#hcModal').on('shown.bs.modal', function () {
        if (!quillModal) {
            quillModal = new Quill('#editorHCModal', {
                theme: 'snow',
                placeholder: 'Escribir evolución clínica...',
                modules: {
                    toolbar: [
                        [{ header: [1, 2, false] }],
                        ['bold', 'italic', 'underline'],
                        [{ list: 'ordered' }, { list: 'bullet' }],
                        ['link'],
                        ['clean']
                    ]
                }
            });
        }

        // Cargar contenido al abrir modal
        let texto = $('#hc_texto').val() || '';
        quillModal.root.innerHTML = texto;
    });
    async function getBase64FromUrl(url) {
        const res = await fetch(url);
        const blob = await res.blob();

        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onloadend = () => resolve(reader.result);
            reader.onerror = reject;
            reader.readAsDataURL(blob);
        });
    }
    
    $(document).ready(function () {

        $('#tablaHC').DataTable({
            order: [[1, "desc"]],
            dom: 'Bfrtip', // 🔥 IMPORTANTE
            buttons: [

                {
                    text: '<i class="fa fa-file-pdf"></i> PDF',
                    className: 'btn btn-danger btn-sm ml-2',
                    action: function () {
                        generarPDFHC();
                    }
                }

            ]
        });

    });
    async function generarPDFHC() {

        let tabla = $('.datatable').DataTable();
        let filas = tabla.rows({ order: 'applied', search: 'applied' }).nodes().toArray();

        let paciente = "<?= addslashes($pacienteNombre) ?>";
        let dni = "<?= addslashes($pacienteData['documento']) ?>";

        let logo = await getBase64FromUrl('images/logo_blanco.png');

        let html = `
    <div style="font-family: Arial; font-size:11px; color:#000;">

        <div style="display:flex;align-items:center;border-bottom:2px solid #0056b3;margin-bottom:15px;padding-bottom:10px;">
            <img src="${logo}" style="height:60px;margin-right:15px;">
            <div>
                <h2 style="margin:0;color:#0056b3;">Sala Bernardino Rivadavia</h2>
                <div>Historia Clínica</div>
            </div>
        </div>

        <div style="border:1px solid #ccc;border-left:4px solid #0056b3;padding:10px;margin-bottom:20px;background:#f9f9f9;">
            <b>Paciente:</b> ${paciente} &nbsp;&nbsp;
            <b>DNI:</b> ${dni}
        </div>
    `;

        for (let row of filas) {

            let tds = $(row).find('td');

            let consulta = tds.eq(0).html();
            let profesionalHTML = tds.eq(1);
            let firmaSrc = tds.eq(2).find('img').attr('src');

            let fecha = profesionalHTML.find('b').first().text();
            let profesionalTexto = profesionalHTML.text();

            let firmaImg = '';

            if (firmaSrc) {
                try {
                    firmaImg = await getBase64FromUrl(firmaSrc);
                } catch { }
            }

            html += `
        <div style="border:1px solid #bbb;margin-bottom:15px;border-radius:5px;page-break-inside:avoid;">

           

            <div style="padding:10px;">

                <div style="margin-bottom:10px;background:#f1f1f1;padding:8px; text-align: center;">
                    ${profesionalTexto}
                </div>

                <div>
                    ${consulta}
                </div>

                <div style="margin-top:15px;text-align:right;">
                    ${firmaImg ? `<img src="${firmaImg}" style="max-width:120px;">` : ''}
                    <div style="border-top:1px solid #000;width:200px;margin-left:auto;margin-top:5px;"></div>
                </div>

            </div>

        </div>
        `;
        }

        html += `</div>`;

        let element = document.createElement('div');
        element.innerHTML = html;

        html2pdf().set({
            margin: 10,
            filename: 'historia_clinica.pdf',
            image: { type: 'jpeg', quality: 1 },
            html2canvas: { scale: 2, useCORS: true },
            jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
        }).from(element).save();
    }
    function abrirHCModal(id) {
        $('#hcModal').modal('show');

        $.ajax({
            url: 'ajax/hc_get.php',
            method: 'GET',
            data: { id: id },
            dataType: 'json',
            success: function (hc) {
                if (!hc || !hc.Id) {
                    Swal.fire('Error', 'No se pudo cargar la HC', 'error');
                    return;
                }

                $('#hc_id').val(hc.Id);
                $('#hc_motivo').val(hc.motivo || '');
                $('#hc_sintomas').val(hc.sintomas || '');
                $('#hc_vitales').val(hc.vitales || '');
                $('#hc_examenes').val(hc.examenes || '');
                $('#hc_diagnostico').val(hc.diagnostico || '');
                $('#hc_medicamento').val(hc.medicamento || '');

                // Cargar texto antiguo correctamente
                if (hc.texto) {
                    // Detectar si es HTML o texto plano
                    const esHTML = /<\/?[a-z][\s\S]*>/i.test(hc.texto);
                    if (esHTML) {
                        quillModal.root.innerHTML = hc.texto;
                    } else {
                        quillModal.setText(hc.texto);
                    }
                } else {
                    quillModal.setText('');
                }

                if (hc.fecha) {
                    $('#hc_fecha').val(hc.fecha.split(' ')[0]);
                }
            },
            error: function (xhr) {
                console.log(xhr.responseText);
                Swal.fire('Error', 'Error en la petición AJAX', 'error');
            }
        });
    }

    // GUARDAR
    $('#formEditarHC').on('submit', function (e) {
        e.preventDefault();

        // Poner el contenido del editor en el input hidden
        $('#hc_texto').val(quillModal.getText().trim()); // HTML
        // Si querés texto plano: quillModal.getText().trim();

        $.ajax({
            url: 'ajax/hc_update.php',
            method: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function (r) {
                if (r.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Guardado correctamente',
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => location.reload());
                } else {
                    Swal.fire('Error', 'No se pudo guardar', 'error');
                }
            },
            error: function () {
                Swal.fire('Error', 'Error en el servidor', 'error');
            }
        });
    });

    // ELIMINAR
    function eliminarHC(id) {
        Swal.fire({
            title: '¿Eliminar registro?',
            text: 'Esta acción no se puede deshacer',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((r) => {
            if (r.isConfirmed) {
                $.post('ajax/hc_delete.php', { id: id }, function (resp) {
                    if (resp.success) {
                        Swal.fire('Eliminado', '', 'success').then(() => location.reload());
                    } else {
                        Swal.fire('Error', resp.message, 'error');
                    }
                }, 'json');
            }
        });
    }
    function imprimirHC() {

        let tabla = $('.datatable').DataTable();
        let filas = tabla.rows({ order: 'applied', search: 'applied' }).nodes().toArray();

        let paciente = "<?= addslashes($pacienteNombre) ?>";
        let dni = "<?= addslashes($pacienteData['documento']) ?>";

        let contenido = `
<html>
<head>
    <title>Historia Clínica</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12pt;
            margin: 30px;
            color: #333;
        }

        /* HEADER */
        .header {
            display: flex;
            align-items: center;
            border-bottom: 3px solid #0056b3;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }

        .header img {
            height: 70px;
            margin-right: 20px;
        }

        .header h1 {
            margin: 0;
            font-size: 22pt;
            color: #0056b3;
            text-transform: uppercase;
        }

        /* PACIENTE */
        .paciente {
            border: 1px solid #ccc;
            border-left: 5px solid #0056b3;
            padding: 12px;
            margin-bottom: 25px;
            background: #f9f9f9;
            border-radius: 5px;
        }

        /* BLOQUE HC */
        .hc-entry {
            border: 1px solid #bbb;
            border-radius: 6px;
            margin-bottom: 25px;
            overflow: hidden;
            page-break-inside: avoid;
        }

        /* CABECERA HC */
        .hc-header {
            background: #0056b3;
            color: #fff;
            padding: 8px 12px;
            font-weight: bold;
            font-size: 11pt;
        }

        /* CUERPO */
        .hc-body {
            padding: 12px;
        }

        /* PROFESIONAL */
        .hc-profesional {
            background: #f1f1f1;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 12px;
            font-size: 11pt;
        }

        /* CAMPOS */
   .hc-row {
    margin-bottom: 8px;
    padding-bottom: 4px;
    border-bottom: 1px dotted #ccc;
}

.hc-row b {
    display: inline-block;
    width: 170px;
    font-weight: 700;
    color: #003366;
}

        /* FIRMA */
        .firma {
            margin-top: 20px;
            text-align: right;
        }

        .firma img {
            max-width: 140px;
            display: block;
            margin-left: auto;
        }

        .firma-line {
            width: 200px;
            border-top: 1px solid #000;
            margin-top: 5px;
            margin-left: auto;
        }

        /* SALTO DE PAGINA LIMPIO */
        .hc-entry {
            page-break-inside: avoid;
        }

    </style>
</head>
<body>

<div class="header">
    <img src="images/logo_blanco.png">
    <h1>Sala Bernardino Rivadavia</h1>
</div>

<div class="paciente">
    <b>Paciente:</b> ${paciente} &nbsp;&nbsp;&nbsp;
    <b>DNI:</b> ${dni}
</div>
`;

        $(filas).each(function () {

            let tds = $(this).find('td');

            let consulta = tds.eq(0).html();
            let profesionalHTML = tds.eq(1).html();
            let firmaHTML = tds.eq(2).html();

            // Extraer fecha del HTML del profesional
            let fecha = $(tds.eq(1)).find('b').first().text();

            contenido += `
        <div class="hc-entry">

           

            <div class="hc-body">

                <div class="hc-profesional">
                    ${profesionalHTML}
                </div>

                <div class="hc-row">
                    ${consulta}
                </div>

                <div class="firma">
                    ${firmaHTML || ''}
                  
                </div>

            </div>

        </div>
        `;
        });

        contenido += `</body></html>`;

        let w = window.open('', '_blank');
        w.document.write(contenido);
        w.document.close();
        w.print();
    }
</script>