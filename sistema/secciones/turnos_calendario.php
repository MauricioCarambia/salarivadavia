<?php
require_once __DIR__ . '/../inc/db.php';

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
if (!$horarios) $horarios = [];

/* =============================
   PROCESAMIENTO
=============================*/
$dias_prof     = [];
$horarios_js   = [];
$businessHours = [];

$apertura = '23:59:59';
$cierre   = '00:00:00';

foreach ($horarios as $h) {
    $dia    = (int) $h['dia'];
    $inicio = substr($h['hora_inicio'], 0, 5);
    $fin    = substr($h['hora_fin'],    0, 5);

    $dias_prof[]   = $dia;
    $horarios_js[] = ["dia" => $dia, "inicio" => $inicio, "fin" => $fin];

    $businessHours[] = [
        "daysOfWeek" => [$dia],
        "startTime"  => $inicio,
        "endTime"    => $fin
    ];

    if ($h['hora_inicio'] < $apertura) $apertura = $h['hora_inicio'];
    if ($h['hora_fin']    > $cierre)   $cierre   = $h['hora_fin'];
}

/* =============================
   AJUSTE HORA CIERRE
   (suma un slot para que el ultimo turno se vea completo)
=============================*/
$cierre_dt = new DateTime($cierre);
$cierre_dt->modify("+{$duracion} minutes");
$cierre = $cierre_dt->format('H:i:s');

/* =============================
   DIAS OCULTOS
=============================*/
$dias_ocultos = array_values(array_diff(range(0, 6), $dias_prof));

/* =============================
   DIAS ANULADOS
=============================*/
$stmt = $conexion->prepare("
    SELECT fecha
    FROM dias_anulados
    WHERE profesional_id = :id
");
$stmt->execute([':id' => $id]);
$dias_anulados = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
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
                        <?= htmlspecialchars($profesional['especialidad'] ?? '') ?>
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
                <h3 class="card-title">Comentario: <?= htmlspecialchars($profesional['comentario'] ?? '') ?></h3>
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
    const getHoraActual = () => {
        const ahora = new Date();
        return String(ahora.getHours()).padStart(2, '0') + ':' +
               String(ahora.getMinutes()).padStart(2, '0') + ':00';
    };

    document.addEventListener('DOMContentLoaded', () => {

        const DURACION      = <?= $duracion ?>;
        const HORARIOS      = <?= json_encode($horarios_js) ?>;
        const DIAS_ANULADOS = <?= json_encode($dias_anulados) ?>;

        /* ------------------------------------------------
           Agrupar horarios por dia de semana y ordenar
        ------------------------------------------------ */
        const HORARIOS_POR_DIA = {};
        HORARIOS.forEach(h => {
            if (!HORARIOS_POR_DIA[h.dia]) HORARIOS_POR_DIA[h.dia] = [];
            HORARIOS_POR_DIA[h.dia].push(h);
        });
        for (let dia in HORARIOS_POR_DIA) {
            HORARIOS_POR_DIA[dia].sort((a, b) => a.inicio.localeCompare(b.inicio));
        }

        /*
         * Retorna { min, max } para el dia de semana (0-6).
         * min = primer inicio, max = ultimo fin.
         * Retorna null si el profesional no atiende ese dia.
         */
        const getRangoDia = (dow) => {
            const rangos = HORARIOS_POR_DIA[dow] || [];
            if (!rangos.length) return null;
            return {
                min: rangos[0].inicio,
                max: rangos[rangos.length - 1].fin
            };
        };

        let eventoArrastrado = null;
        const SwalInstance = window.parent?.Swal || window.Swal;

        const formatTime = (date) => date.toLocaleTimeString('es-AR', {
            hour: '2-digit', minute: '2-digit'
        });

        const formatFechaSQL = (date) => {
            return date.getFullYear() + '-' +
                String(date.getDate()).padStart(2, '0') + ' ' +
                String(date.getHours()).padStart(2, '0') + ':' +
                String(date.getMinutes()).padStart(2, '0') + ':00';
        };

        const HORARIOS_BACKGROUND = HORARIOS.map(h => ({
            daysOfWeek: [h.dia],
            startTime:  h.inicio,
            endTime:    h.fin,
            display:    'background',
            className:  'horario-disponible'
        }));

        const ANULADOS_BACKGROUND = DIAS_ANULADOS.map(fecha => ({
            start:     fecha,
            end:       fecha,
            display:   'background',
            className: 'dia-anulado',
            title:     'Dia Anulado'
        }));

        const calendar = new FullCalendar.Calendar(document.getElementById('calendar'), {
            locale:      'es',
            timeZone:    'local',
            initialView: 'timeGridWeek',
            height:      'auto',
            nowIndicator: true,

            slotDuration:      `00:${String(DURACION).padStart(2, '0')}:00`,
            snapDuration:      `00:${String(DURACION).padStart(2, '0')}:00`,
            slotLabelInterval: `00:${String(DURACION).padStart(2, '0')}:00`,
            slotLabelFormat: { hour: '2-digit', minute: '2-digit', hour12: false },

            scrollTime:      getHoraActual(),
            scrollTimeReset: false,

            // Rango global (vista semanal/mensual)
            slotMinTime: '<?= $apertura ?>',
            slotMaxTime: '<?= $cierre ?>',

            hiddenDays:   <?= json_encode($dias_ocultos) ?>,
            allDaySlot:   false,
            selectable:   true,
            editable:     true,
            eventDurationEditable: false,
            selectMirror: false,
            expandRows:   true,

            businessHours:    <?= json_encode($businessHours) ?>,
            selectConstraint: 'businessHours',
            eventConstraint:  'businessHours',
            displayEventTime: false,
  buttonText: {
        today: 'Hoy',
        week: 'Semana',
        day: 'Día'
    },
            headerToolbar: {
                left:   'prev,next today imprimirAgenda',
                center: 'title',
                right:  'timeGridWeek,timeGridDay'
            },

            customButtons: {
                imprimirAgenda: {
                    text: '🖨 Imprimir Dia',
                    click: () => {
                        const fecha = calendar.getDate().toISOString().split('T')[0];
                        window.open(`secciones/agenda_imprimir.php?profesional=<?= $id ?>&fecha=${fecha}`, '_blank');
                    }
                }
            },

            eventSources: [
                { events: HORARIOS_BACKGROUND },
                { events: ANULADOS_BACKGROUND },
                {
                    url:    'secciones/turnos_eventos.php',
                    method: 'GET',
                    extraParams: { profesional_id: <?= $id ?> }
                }
            ],

            /* --------------------------------------------------
               VISTA DIARIA: ajusta slotMinTime/slotMaxTime al
               rango real de atencion de ese dia de semana.
               Al salir de la vista diaria, restaura el global.
            -------------------------------------------------- */
            datesSet: (info) => {
                if (info.view.type === 'timeGridDay') {
                    const dow   = info.view.currentStart.getDay(); // 0=Dom … 6=Sab
                    const rango = getRangoDia(dow);

                    if (rango) {
                        // Sumar 1 slot al cierre para ver el ultimo turno completo
                        const [hh, mm] = rango.max.split(':').map(Number);
                        const cierreDt  = new Date(2000, 0, 1, hh, mm + DURACION);
                        const cierreStr = String(cierreDt.getHours()).padStart(2, '0') + ':' +
                                          String(cierreDt.getMinutes()).padStart(2, '0') + ':00';

                        calendar.setOption('slotMinTime', rango.min + ':00');
                        calendar.setOption('slotMaxTime', cierreStr);
                    }
                } else {
                    // Restaurar rango global al volver a semana/mes
                    calendar.setOption('slotMinTime', '<?= $apertura ?>');
                    calendar.setOption('slotMaxTime', '<?= $cierre ?>');
                }

                // Scroll inteligente
                const hoy             = new Date().toISOString().split('T')[0];
                const fechaCalendario = info.view.currentStart.toISOString().split('T')[0];
                const destino = (hoy === fechaCalendario || info.view.type === 'timeGridWeek')
                    ? getHoraActual()
                    : '<?= $apertura ?>';

                setTimeout(() => calendar.scrollToTime(destino), 100);
            },

            eventContent: function(arg) {
                if (arg.event.display === 'background') return;

                const props  = arg.event.extendedProps;
                const nombre = arg.event.title;

                const pagoStatus = props.asistio == '1'
                    ? '<span style="color:#fff;font-weight:bold;background:#28a745;padding:1px 4px;border-radius:3px;font-size:0.8em;">PAGO</span>'
                    : '<span style="color:#fff;font-weight:bold;background:#dc3545;padding:1px 4px;border-radius:3px;font-size:0.8em;">NO PAGO</span>';

                let horaRecepcion = '';
                if (props.asistio == 1 && props.fecha_actual && props.fecha_actual.includes(' ')) {
                    horaRecepcion = ` | Rec: ${props.fecha_actual.split(' ')[1].slice(0, 5)}`;
                }

                const atendidoStatus = props.atendido == 1
                    ? '<span title="Atendido" style="font-size:1.1em;margin-right:2px;">&#x2705;</span>'
                    : '<span title="Pendiente" style="font-size:1.1em;margin-right:2px;opacity:0.5;">&#x274C;</span>';

                const cont = document.createElement('div');
                cont.className = 'fc-content-custom';
                cont.style.cssText = 'padding:2px;font-size:0.85em;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;';
                cont.innerHTML = `${atendidoStatus} <b>${nombre}</b> | DNI: ${props.documento || '---'} ${pagoStatus}${horaRecepcion}`;

                return { domNodes: [cont] };
            },

            // Un solo eventDidMount (sin duplicados)
            eventDidMount: (info) => {
                if (info.event.display === 'background') return;

                if (info.event.extendedProps.sobreturno == 1) {
                    info.el.classList.add('evento-sobreturno');
                    info.el.style.borderStyle = 'dashed';
                }
                info.el.style.cursor = 'pointer';
            },

            selectAllow: function(selectInfo) {
                const dia    = selectInfo.start.getDay();
                const hora   = selectInfo.start.toTimeString().slice(0, 5);
                const rangos = HORARIOS_POR_DIA[dia] || [];
                const ok     = rangos.some(r => hora >= r.inicio && hora < r.fin);
                const fechaStr    = selectInfo.start.toISOString().slice(0, 10);
                const estaAnulado = DIAS_ANULADOS.includes(fechaStr);
                return ok && !estaAnulado;
            },

            eventAllow: function(dropInfo) {
                const dia    = dropInfo.start.getDay();
                const hora   = dropInfo.start.toTimeString().slice(0, 5);
                const rangos = HORARIOS_POR_DIA[dia] || [];
                return rangos.some(r => hora >= r.inicio && hora < r.fin);
            },

            select: function(info) {
                const fechaStr = info.start.toISOString().slice(0, 10);
                if (DIAS_ANULADOS.includes(fechaStr)) return calendar.unselect();

                const fecha = formatFechaSQL(info.start);
                document.getElementById('iframeTurno').src =
                    `index_clean.php?seccion=turnos_asignar&p=<?= $id ?>&fecha=${encodeURIComponent(fecha)}`;
                $('#modalTurno').modal('show');
            },

            eventClick: (info) => {
                if (info.event.display === 'background') return;
                window.location.href = `./?seccion=turnos_ver&id=${info.event.id}`;
            },

            // Sobreturnos al final (derecha), turnos normales primero
            eventOrder: function(a, b) {
                return (a.extendedProps?.sobreturno || 0) - (b.extendedProps?.sobreturno || 0);
            },

            eventDragStart: (info) => {
                eventoArrastrado = info.event;
                document.getElementById('papelera').classList.add('visible');
            },

            eventDragStop: (info) => {
                const papelera = document.getElementById('papelera');
                const rect     = papelera.getBoundingClientRect();
                papelera.classList.remove('visible', 'activa');

                const dentro =
                    info.jsEvent.clientX >= rect.left &&
                    info.jsEvent.clientX <= rect.right &&
                    info.jsEvent.clientY >= rect.top  &&
                    info.jsEvent.clientY <= rect.bottom;

                if (!dentro) return;

                SwalInstance.fire({
                    icon:  'warning',
                    title: 'Eliminar turno',
                    text:  'Eliminar este turno?',
                    showCancelButton:  true,
                    confirmButtonText: 'Eliminar'
                }).then(result => {
                    if (!result.isConfirmed) return;
                    fetch('secciones/turno_eliminar.php', {
                        method:  'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body:    `id=${eventoArrastrado.id}`
                    })
                    .then(r => r.json())
                    .then(resp => {
                        if (!resp.ok) return SwalInstance.fire('Error', resp.error, 'error');
                        eventoArrastrado.remove();
                        SwalInstance.fire({ icon: 'success', title: 'Turno eliminado', timer: 1500, showConfirmButton: false });
                    });
                });
            },

            eventDrop: (info) => {
                const nombre = info.event.title;
                const hora   = formatTime(info.event.start);

                const eventosEnEseHorario = calendar.getEvents().filter(e =>
                    e.id !== info.event.id &&
                    e.display !== 'background' &&
                    ((info.event.start >= e.start && info.event.start < e.end) ||
                     (info.event.end   >  e.start && info.event.end  <= e.end))
                );

                let nuevoSobreTurno = info.event.extendedProps.sobreturno;
                let mensajeExtra    = '';

                if (nuevoSobreTurno == 1 && eventosEnEseHorario.length === 0) {
                    nuevoSobreTurno = 0;
                    mensajeExtra = "<br><small class='text-success'>(Se convertira en turno normal)</small>";
                } else if (nuevoSobreTurno == 0 && eventosEnEseHorario.length > 0) {
                    nuevoSobreTurno = 1;
                    mensajeExtra = "<br><small class='text-warning'>(Se convertira en sobreturno)</small>";
                }

                SwalInstance.fire({
                    icon:  'question',
                    title: 'Mover turno',
                    html:  `Mover turno de <b>${nombre}</b> a las <b>${hora}</b>? ${mensajeExtra}`,
                    showCancelButton:  true,
                    confirmButtonText: 'Mover'
                }).then(result => {
                    if (!result.isConfirmed) return info.revert();

                    const inicio = formatFechaSQL(info.event.start);

                    fetch('secciones/turno_mover.php', {
                        method:  'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body:    `id=${info.event.id}&fecha=${inicio}&sobreturno=${nuevoSobreTurno}`
                    })
                    .then(r => r.json())
                    .then(resp => {
                        if (!resp.ok) {
                            info.revert();
                            return SwalInstance.fire('Error', resp.error, 'error');
                        }

                        info.event.setExtendedProp('sobreturno', nuevoSobreTurno);
                        const color = nuevoSobreTurno == 0 ? '#3a87ad' : '#ffb606';
                        info.event.setProp('backgroundColor', color);
                        info.event.setProp('borderColor',     color);

                        SwalInstance.fire({ icon: 'success', title: 'Turno actualizado', timer: 1500, showConfirmButton: false });
                    })
                    .catch(() => info.revert());
                });
            }
        });

        calendar.render();

        // Papelera: hover visual mientras se arrastra
        document.addEventListener('dragover', (e) => {
            const papelera = document.getElementById('papelera');
            if (!papelera) return;
            const rect = papelera.getBoundingClientRect();
            const dentro =
                e.clientX >= rect.left &&
                e.clientX <= rect.right &&
                e.clientY >= rect.top  &&
                e.clientY <= rect.bottom;
            papelera.classList.toggle('activa', dentro);
        });
    });
</script>