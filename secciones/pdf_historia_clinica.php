<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

require_once __DIR__ . '/../inc/db.php';

function base64($ruta)
{
    if (!file_exists($ruta))
        return '';
    $tipo = pathinfo($ruta, PATHINFO_EXTENSION);
    $data = file_get_contents($ruta);
    return 'data:image/' . $tipo . ';base64,' . base64_encode($data);
}

$id = (int) ($_GET['id'] ?? 0);

/* ================= PACIENTE ================= */
$stmt = $pdo->prepare("SELECT * FROM pacientes WHERE Id = :id");
$stmt->execute([':id' => $id]);
$paciente = $stmt->fetch(PDO::FETCH_ASSOC);

/* ================= LOGO ================= */
$logo = base64(__DIR__ . '/../images/logo_blanco.png');

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
    ORDER BY hc.fecha DESC, hc.Id DESC
");
$stmtHC->execute([':pid' => $id]);
$historias = $stmtHC->fetchAll(PDO::FETCH_ASSOC);


$historias = array_values($historias);
/* ================= PDF ================= */
$options = new Options();
$options->set('isRemoteEnabled', true);

$dompdf = new Dompdf($options);

/* ================= HTML ================= */
$html = '
<style>
@page {
    margin: 120px 40px 80px 40px;
}

body {
    font-family: Arial;
    font-size: 11px;
    color: #000;
}

/* HEADER */
.header {
    position: fixed;
    top: -100px;
    left: 0;
    right: 0;
    border-bottom: 2px solid #0056b3;
}

.header h1 {
    color: #0056b3;
    margin: 0;
}

/* FOOTER */
.footer {
    position: fixed;
    bottom: -60px;
    text-align: center;
    font-size: 10px;
    color: #666;
}

/* WATERMARK */
.watermark {
    position: fixed;
    top: 40%;
    left: 15%;
    font-size: 70px;
    color: rgba(0,0,0,0.05);
    transform: rotate(-30deg);
}

/* PACIENTE */
.paciente {
    border: 1px solid #ccc;
    padding: 10px;
    margin-bottom: 20px;
}

/* BLOQUE HC */
.hc {
    border: 1px solid #aaa;
    margin-bottom: 20px;
    page-break-inside: avoid;
}
.hc-header, .hc-body {
    display: block;
    width: 100%;
}
/* CABECERA */
.hc-header {
    background: #0056b3;
    color: #fff;
    padding: 6px;
    font-weight: bold;
}

/* CUERPO */
.hc-body {
    padding: 10px;
}

/* FIRMA */
.firma {
    margin-top: 20px;
    text-align: right;
}
</style>

<div class="header">
    <img src="' . $logo . '" style="height:60px;">
    <h1>Sala Bernardino Rivadavia</h1>
    <small>Historia Clínica</small>
</div>

<div class="watermark">CONFIDENCIAL</div>

<div class="footer">
    Página <span class="page"></span> de <span class="topage"></span>
</div>

<div class="paciente">
    <b>Paciente:</b> ' . $paciente['apellido'] . ' ' . $paciente['nombre'] . '<br>
    <b>DNI:</b> ' . $paciente['documento'] . '
</div>
';

/* ================= LOOP HISTORIAS ================= */
foreach ($historias as $hc) {

    // 🔥 FIRMA CORRECTA
    $firmaHTML = '';

    $firmaHTML = '';

    if (!empty($hc['profesionalfirma'])) {

        // limpiar ruta por seguridad
        $firmaBD = trim($hc['profesionalfirma']);
        $firmaBD = str_replace(['../', './'], '', $firmaBD);

        $rutaFirma = realpath(__DIR__ . '/../' . $firmaBD);

        if ($rutaFirma && file_exists($rutaFirma)) {
            $firmaHTML = '<img src="' . base64($rutaFirma) . '" style="height:100px;"><br>';
        } else {
            $firmaHTML = '<small>Firma no encontrada</small>';
        }
    }

    $html .= '
    <div class="hc">

        <div class="hc-header">
            Consulta - ' . date('d/m/Y H:i', strtotime($hc['fecha'])) . '
        </div>

        <div class="hc-body">

            <b>Profesional:</b> ' . $hc['profesionalapellido'] . ' ' . $hc['profesionalnombre'] . '<br>
            <b>Especialidad:</b> ' . $hc['especialidad'] . '<br>
            MN: ' . $hc['matricula_nacional'] . ' | MP: ' . $hc['matricula_provincial'] . '<br><br>

            <b>Motivo:</b> ' . $hc['motivo'] . '<br>
            <b>Síntomas:</b> ' . $hc['sintomas'] . '<br>
            <b>Signos vitales:</b> ' . $hc['vitales'] . '<br>
            <b>Examenes:</b> ' . $hc['examenes'] . '<br>
            <b>Diagnóstico:</b> ' . $hc['diagnostico'] . '<br>
            <b>Medicamento:</b> ' . $hc['medicamento'] . '<br>
            <b>Observaciones:</b> ' . $hc['texto'] . '<br>

            <div class="firma">
                ' . $firmaHTML . '
                ___________________________<br>
                ' . $hc['profesionalapellido'] . ' ' . $hc['profesionalnombre'] . '<br>
                MN: ' . $hc['matricula_nacional'] . ' | MP: ' . $hc['matricula_provincial'] . '
            </div>

        </div>

    </div>
    ';
}

/* ================= GENERAR PDF ================= */
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$dompdf->stream("historia_clinica.pdf", ["Attachment" => false]);