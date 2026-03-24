<?php
require_once "inc/db.php";

date_default_timezone_set('America/Argentina/Buenos_Aires');

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

/* =============================
   PROFESIONAL
=============================*/
$stmt = $conexion->prepare("
    SELECT p.*, e.especialidad
    FROM profesionales p
    LEFT JOIN especialidades e ON e.Id = p.especialidad_id
    WHERE p.Id = :id
");
$stmt->execute([':id' => $id]);

$profesional = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$profesional) {
    exit("Profesional inexistente");
}

$duracion = max(5, (int) ($profesional['duracion_turnos'] ?? 15));

/* =============================
   HORARIOS (UNA SOLA QUERY)
=============================*/
$stmt = $conexion->prepare("
    SELECT dia, hora_inicio, hora_fin
    FROM profesionales_horarios
    WHERE profesional_id = :id
");
$stmt->execute([':id' => $id]);

$horarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$horarios) {
    $horarios = [];
}

/* =============================
   PROCESAMIENTO
=============================*/
$dias_prof = [];
$horarios_js = [];
$businessHours = [];

$apertura = '23:59:59';
$cierre = '00:00:00';

foreach ($horarios as $h) {

    $dia = (int) $h['dia'];
    $inicio = substr($h['hora_inicio'], 0, 5);
    $fin = substr($h['hora_fin'], 0, 5);

    $dias_prof[] = $dia;

    $horarios_js[] = [
        "dia" => $dia,
        "inicio" => $inicio,
        "fin" => $fin
    ];

    $businessHours[] = [
        "daysOfWeek" => [$dia],
        "startTime" => $inicio,
        "endTime" => $fin
    ];

    // calcular rango dinámico
    if ($h['hora_inicio'] < $apertura) {
        $apertura = $h['hora_inicio'];
    }

    if ($h['hora_fin'] > $cierre) {
        $cierre = $h['hora_fin'];
    }
}

/* =============================
   AJUSTE HORA CIERRE
=============================*/
$cierre_dt = new DateTime($cierre);
$cierre_dt->modify("+{$duracion} minutes");
$cierre = $cierre_dt->format('H:i:s');

/* =============================
   DIAS OCULTOS
=============================*/
$dias_ocultos = array_values(array_diff(range(0, 6), $dias_prof));
?>

<div class="row mb-3">
    <div class="col-12">
        <div class="card card-info card-outline">
            <div class="card-header">

                <div class="col-sm-6">
                    <h1 class="m-0">
                        <?= htmlspecialchars($profesional['apellido'] . " " . $profesional['nombre']) ?>
                    </h1>
                    <small class="text-muted">
                        <?= htmlspecialchars($profesional['especialidad']) ?>
                    </small>
                </div>

                <div class="col-sm-6 text-right">
                    <div id="papelera" class="papelera-turnos">
                        <img src="images/papelera.png" alt="papelera">
                        <div class="textoPapelera">Soltar para eliminar</div>
                    </div>
                </div>

            </div>

            <div class="card-header">
                <h3 class="card-title">Comentario: <?= $profesional['comentario'] ?></h3>
            </div>

            <div class="card-body p-2">
                <div id="calendar"></div>
            </div>

        </div>
    </div>
</div>

<!-- MODAL -->
<div class="modal fade" id="modalTurno">
    <div class="modal-dialog modal-lg" style="width:80%">
        <div class="modal-content">
            <div class="modal-body p-0">
                <iframe id="iframeTurno" width="100%" height="700"></iframe>
            </div>
        </div>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', () => {

        const DURACION = <?= $duracion ?>;
        const HORARIOS = <?= json_encode($horarios_js) ?>;

        // 🔥 agrupar por día
        const HORARIOS_POR_DIA = {};

        HORARIOS.forEach(h => {
            if (!HORARIOS_POR_DIA[h.dia]) {
                HORARIOS_POR_DIA[h.dia] = [];
            }
            HORARIOS_POR_DIA[h.dia].push(h);
        });

        // ordenar por hora
        for (let dia in HORARIOS_POR_DIA) {
            HORARIOS_POR_DIA[dia].sort((a, b) => a.inicio.localeCompare(b.inicio));
        }

        let eventoArrastrado = null;

        // 🔥 Swal compatible con iframe o normal
        const SwalInstance = window.parent?.Swal || window.Swal;

        const formatTime = (date) => {
            return date.toLocaleTimeString('es-AR', {
                hour: '2-digit',
                minute: '2-digit'
            });
        };

        // 🔥 FORMATO FECHA SIN PROBLEMAS DE TIMEZONE
        const formatFechaSQL = (date) => {
            return date.getFullYear() + '-' +
                String(date.getMonth() + 1).padStart(2, '0') + '-' +
                String(date.getDate()).padStart(2, '0') + ' ' +
                String(date.getHours()).padStart(2, '0') + ':' +
                String(date.getMinutes()).padStart(2, '0') + ':00';
        };

        const HORARIOS_BACKGROUND = HORARIOS.map(h => ({
            daysOfWeek: [h.dia],
            startTime: h.inicio,
            endTime: h.fin,
            display: 'background',
            className: 'horario-disponible'
        }));

        const calendar = new FullCalendar.Calendar(
            document.getElementById('calendar'),
            {
                locale: 'es',
                timeZone: 'local',
                initialView: 'timeGridWeek',
                height: 'auto',

                nowIndicator: true,

                slotDuration: `00:${String(DURACION).padStart(2, '0')}:00`,
                snapDuration: `00:${String(DURACION).padStart(2, '0')}:00`,

                slotLabelInterval: `00:${String(DURACION).padStart(2, '0')}:00`,
                slotLabelFormat: {
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: false
                },

                slotMinTime: '<?= $apertura ?>',
                slotMaxTime: '<?= $cierre ?>',

                hiddenDays: <?= json_encode($dias_ocultos) ?>,

                allDaySlot: false,
                selectable: true,
                editable: true,
                eventDurationEditable: false,
                selectMirror: false,
                expandRows: true,
                dateClick: null,
                businessHours: <?= json_encode($businessHours) ?>,
                selectConstraint: "businessHours",
                eventConstraint: "businessHours",

                headerToolbar: {
                    left: 'prev,next today imprimirAgenda',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },

                customButtons: {
                    imprimirAgenda: {
                        text: '🖨 Imprimir Día',
                        click: () => {
                            const fecha = calendar.getDate().toISOString().split('T')[0];
                            window.open(
                                `secciones/agenda_imprimir.php?profesional=<?= $id ?>&fecha=${fecha}`,
                                '_blank'
                            );
                        }
                    }
                },

                displayEventTime: false,
                selectable: true,
                selectMirror: false,
                dateClick: null,

                select: function (info) {

                    const dia = info.start.getDay();

                    const hora = info.start.getHours().toString().padStart(2, '0') + ':' +
                        info.start.getMinutes().toString().padStart(2, '0');

                    const rangos = HORARIOS_POR_DIA[dia] || [];

                    const permitido = rangos.some(r => hora >= r.inicio && hora < r.fin);

                    if (!permitido) {
                        calendar.unselect();
                        return;
                    }

                    const fecha = formatFechaSQL(info.start);

                    document.getElementById('iframeTurno').src =
                        `index_clean.php?seccion=turnos_asignar&p=<?= $id ?>&fecha=${encodeURIComponent(fecha)}`;

                    $('#modalTurno').modal('show');
                },

                datesSet: (info) => {

                    const btn = document.querySelector('.fc-imprimirAgenda-button');
                    if (btn) {
                        btn.style.display =
                            info.view.type === 'timeGridDay'
                                ? 'inline-block'
                                : 'none';
                    }

                    if (info.view.type === 'timeGridDay') {

                        const fecha = info.start;
                        const dia = fecha.getDay();

                        const rangos = HORARIOS_POR_DIA[dia] || [];

                        if (rangos.length) {

                            // 🔥 rango total visible (mín y máx del día)
                            const min = rangos[0].inicio;
                            const max = rangos[rangos.length - 1].fin;

                            calendar.setOption('slotMinTime', min + ':00');
                            calendar.setOption('slotMaxTime', max + ':00');

                            // 🔥 generar huecos (bloqueados)
                            let bloqueos = [];

                            for (let i = 0; i < rangos.length - 1; i++) {

                                bloqueos.push({
                                    startTime: rangos[i].fin,
                                    endTime: rangos[i + 1].inicio,
                                    daysOfWeek: [dia],
                                    display: 'background',
                                    className: 'fc-no-laboral'
                                });
                            }

                            // 🔥 actualizar eventos de fondo
                            calendar.setOption('eventSources', [
                                { events: HORARIOS_BACKGROUND }, // horarios válidos
                                { events: bloqueos }, // huecos bloqueados
                                {
                                    url: 'secciones/turnos_eventos.php',
                                    method: 'GET',
                                    extraParams: { profesional_id: <?= $id ?> }
                                }
                            ]);

                        }

                    } else {

                        // 🔥 volver a normal en semana/mes
                        calendar.setOption('slotMinTime', '<?= $apertura ?>');
                        calendar.setOption('slotMaxTime', '<?= $cierre ?>');

                        calendar.setOption('eventSources', [
                            { events: HORARIOS_BACKGROUND },
                            {
                                url: 'secciones/turnos_eventos.php',
                                method: 'GET',
                                extraParams: { profesional_id: <?= $id ?> }
                            }
                        ]);
                    }
                },

                /* =============================
                   CREAR TURNO
                =============================*/

                /* =============================
                   VER TURNO
                =============================*/
                eventClick: (info) => {
                    window.location.href = `./?seccion=turnos_ver&id=${info.event.id}`;
                },

                /* =============================
                   EVENTOS
                =============================*/
                eventSources: [
                    { events: HORARIOS_BACKGROUND },
                    {
                        url: 'secciones/turnos_eventos.php',
                        method: 'GET',
                        extraParams: { profesional_id: <?= $id ?> }
                    }
                ],
                selectAllow: function (selectInfo) {

                    const dia = selectInfo.start.getDay();

                    const hora = selectInfo.start.getHours().toString().padStart(2, '0') + ':' +
                        selectInfo.start.getMinutes().toString().padStart(2, '0');

                    const rangos = HORARIOS_POR_DIA[dia] || [];

                    return rangos.some(r => hora >= r.inicio && hora < r.fin);
                },
                eventAllow: (dropInfo, draggedEvent) => {

                    const dia = dropInfo.start.getDay();
                    const hora = dropInfo.start.toTimeString().slice(0, 5);

                    const rangos = HORARIOS_POR_DIA[dia] || [];

                    return rangos.some(r => hora >= r.inicio && hora < r.fin);
                },
                /* =============================
                   MOVER TURNO
                =============================*/
                eventDrop: (info) => {

                    const nombre = info.event.title;
                    const hora = formatTime(info.event.start);

                    SwalInstance.fire({
                        icon: 'question',
                        title: 'Mover turno',
                        html: `¿Mover turno de <b>${nombre}</b> a las <b>${hora}</b>?`,
                        showCancelButton: true,
                        confirmButtonText: 'Mover'
                    }).then(result => {

                        if (!result.isConfirmed) return info.revert();

                        const inicio = formatFechaSQL(info.event.start);

                        fetch('secciones/turno_mover.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: `id=${info.event.id}&fecha=${inicio}`
                        })
                            .then(r => r.json())
                            .then(resp => {

                                if (!resp.ok) {
                                    info.revert();
                                    return SwalInstance.fire('Error', resp.error, 'error');
                                }

                                SwalInstance.fire({
                                    icon: 'success',
                                    title: 'Turno movido',
                                    timer: 1500,
                                    showConfirmButton: false
                                });
                            })
                            .catch(() => info.revert());
                    });
                },

                /* =============================
                   DRAG START
                =============================*/
                eventDragStart: (info) => {
                    eventoArrastrado = info.event;
                    document.getElementById('papelera').classList.add('visible');
                },

                /* =============================
                   ELIMINAR CON PAPELERA
                =============================*/
                eventDragStop: (info) => {

                    const papelera = document.getElementById('papelera');
                    const rect = papelera.getBoundingClientRect();

                    papelera.classList.remove('visible', 'activa');

                    const dentro =
                        info.jsEvent.clientX >= rect.left &&
                        info.jsEvent.clientX <= rect.right &&
                        info.jsEvent.clientY >= rect.top &&
                        info.jsEvent.clientY <= rect.bottom;

                    if (!dentro) return;

                    SwalInstance.fire({
                        icon: 'warning',
                        title: 'Eliminar turno',
                        text: '¿Eliminar este turno?',
                        showCancelButton: true,
                        confirmButtonText: 'Eliminar'
                    }).then(result => {

                        if (!result.isConfirmed) return;

                        fetch('secciones/turno_eliminar.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: `id=${eventoArrastrado.id}`
                        })
                            .then(r => r.json())
                            .then(resp => {

                                if (!resp.ok) {
                                    return SwalInstance.fire('Error', resp.error, 'error');
                                }

                                eventoArrastrado.remove();

                                SwalInstance.fire({
                                    icon: 'success',
                                    title: 'Turno eliminado',
                                    timer: 1500,
                                    showConfirmButton: false
                                });
                            });
                    });
                },
                eventDidMount: function (info) {

                    let evento = info.event;
                    let url = `./?seccion=turnos_ver&id=${evento.id}`;

                    // 🔥 Convertir el evento en link real
                    let link = document.createElement('a');
                    link.href = url;
                    link.target = '_blank';
                    link.rel = 'noopener noreferrer';
                    link.style.display = 'block';
                    link.style.width = '100%';
                    link.style.height = '100%';
                    link.style.color = 'inherit';
                    link.style.textDecoration = 'none';

                    // mover contenido del evento dentro del link
                    while (info.el.firstChild) {
                        link.appendChild(info.el.firstChild);
                    }

                    info.el.appendChild(link);

                    // 🔥 TOOLTIP
                    let contenido = `
        <b>${evento.title}</b><br>
        Hora: ${evento.start.toLocaleTimeString('es-AR', { hour: '2-digit', minute: '2-digit' })}<br>
        ${evento.extendedProps.documento ? 'DNI: ' + evento.extendedProps.documento + '<br>' : ''}
        ${evento.extendedProps.telefono ? 'Tel: ' + evento.extendedProps.telefono + '<br>' : ''}
        ${evento.extendedProps.estudio ? 'Estudio: ' + evento.extendedProps.estudio : ''}
    `;

                    $(info.el).tooltip({
                        title: contenido,
                        html: true,
                        placement: 'top',
                        container: 'body',
                        trigger: 'hover'
                    });
                }
            }
        );

        calendar.render();

        /* =============================
           PAPELERA HOVER
        =============================*/
        document.addEventListener("dragover", (e) => {

            const papelera = document.getElementById("papelera");
            const rect = papelera.getBoundingClientRect();

            const dentro =
                e.clientX >= rect.left &&
                e.clientX <= rect.right &&
                e.clientY >= rect.top &&
                e.clientY <= rect.bottom;

            papelera.classList.toggle("activa", dentro);
        });

    });
</script>