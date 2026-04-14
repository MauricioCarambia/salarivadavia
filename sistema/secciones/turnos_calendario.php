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
/* =============================
   DIAS ANULADOS
=============================*/
$stmt = $conexion->prepare("
    SELECT fecha
    FROM dias_anulados
    WHERE profesional_id = :id
");
$stmt->execute([':id' => $id]);
$dias_anulados = $stmt->fetchAll(PDO::FETCH_COLUMN, 0); // obtenemos solo las fechas
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
        const DIAS_ANULADOS = <?= json_encode($dias_anulados) ?>;

        // 🔥 Agrupar horarios por día
        const HORARIOS_POR_DIA = {};
        HORARIOS.forEach(h => {
            if (!HORARIOS_POR_DIA[h.dia]) HORARIOS_POR_DIA[h.dia] = [];
            HORARIOS_POR_DIA[h.dia].push(h);
        });
        for (let dia in HORARIOS_POR_DIA) {
            HORARIOS_POR_DIA[dia].sort((a, b) => a.inicio.localeCompare(b.inicio));
        }

        let eventoArrastrado = null;
        const SwalInstance = window.parent?.Swal || window.Swal;

        const formatTime = (date) => date.toLocaleTimeString('es-AR', {
            hour: '2-digit',
            minute: '2-digit'
        });

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

        const ANULADOS_BACKGROUND = DIAS_ANULADOS.map(fecha => ({
            start: fecha,
            end: fecha,
            display: 'background',
            className: 'dia-anulado',
            title: 'Día Anulado'
        }));

        const calendar = new FullCalendar.Calendar(document.getElementById('calendar'), {
            locale: 'es',
            timeZone: 'local',
            initialView: 'timeGridWeek',
            height: 'auto',
            nowIndicator: true,
            slotDuration: `00:${String(DURACION).padStart(2,'0')}:00`,
            snapDuration: `00:${String(DURACION).padStart(2,'0')}:00`,
            slotLabelInterval: `00:${String(DURACION).padStart(2,'0')}:00`,
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
            businessHours: <?= json_encode($businessHours) ?>,
            selectConstraint: "businessHours",
            eventConstraint: "businessHours",
            displayEventTime: false,
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
                        window.open(`secciones/agenda_imprimir.php?profesional=<?= $id ?>&fecha=${fecha}`, '_blank');
                    }
                }
            },

            eventSources: [
                { events: HORARIOS_BACKGROUND },
                { events: ANULADOS_BACKGROUND },
                {
                    url: 'secciones/turnos_eventos.php',
                    method: 'GET',
                    extraParams: { profesional_id: <?= $id ?> }
                }
            ],

            // 🔥 RENDERIZADO DE CONTENIDO (Corregido para 1 sola línea)
            eventContent: function(arg) {
                if (arg.event.display === 'background') return;

                const props = arg.event.extendedProps;
                const nombre = arg.event.title;

                // Lógica de Pago: Usamos 'abono' si lo agregaste al PHP, sino 'asistio'
                const pagoStatus = props.asistio == '1' ?
                    '<span style="color:#ffffff; font-weight:bold; background:#28a745; padding:1px 4px; border-radius:3px; font-size:0.8em;">PAGÓ</span>' :
                    '<span style="color:#ffffff; font-weight:bold; background:#dc3545; padding:1px 4px; border-radius:3px; font-size:0.8em;">NO PAGÓ</span>';

                // Formatear Hora de Recepción (HH:mm)
               let horaRecepcion = '';

if (props.asistio == 1 && props.fecha_actual && props.fecha_actual.includes(' ')) {
    horaRecepcion = ` | Rec: ${props.fecha_actual.split(' ')[1].slice(0, 5)}`;
}

                let cont = document.createElement('div');
                cont.className = 'fc-content-custom';
                cont.style.padding = '2px';
                cont.style.fontSize = '0.85em';
                cont.style.whiteSpace = 'nowrap';
                cont.style.overflow = 'hidden';
                cont.style.textOverflow = 'ellipsis';

                cont.innerHTML = `<b>${nombre}</b> | DNI: ${props.documento || '---'} ${pagoStatus}${horaRecepcion}`;

                return { domNodes: [cont] };
            },

            // 🔥 COLORES DE FONDO (Corregido)
            eventDidMount: (info) => {
                if (info.event.display === 'background') return;

              if (info.event.extendedProps.sobreturno == 1) {
        info.el.classList.add('evento-sobreturno');
        // Opcional: un borde punteado para diferenciarlo visualmente más allá del color
        info.el.style.borderStyle = 'dashed';
    }
                // Hacer que todo el evento sea un link
                info.el.style.cursor = 'pointer';
            },

            selectAllow: function(selectInfo) {
                const dia = selectInfo.start.getDay();
                const hora = selectInfo.start.toTimeString().slice(0, 5);
                const rangos = HORARIOS_POR_DIA[dia] || [];
                const permitidoHorario = rangos.some(r => hora >= r.inicio && hora < r.fin);
                const fechaStr = selectInfo.start.toISOString().slice(0, 10);
                const estaAnulado = DIAS_ANULADOS.includes(fechaStr);
                return permitidoHorario && !estaAnulado;
            },

            eventAllow: function(dropInfo) {
                const dia = dropInfo.start.getDay();
                const hora = dropInfo.start.toTimeString().slice(0, 5);
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
                // Abrir en la misma pestaña o pestaña nueva según prefieras
                window.location.href = `./?seccion=turnos_ver&id=${info.event.id}`;
            },

          // ... dentro de la configuración de FullCalendar ...

// 1. Asignar clase visual para identificar sobreturnos
eventDidMount: (info) => {
    if (info.event.display === 'background') return;
    
    if (info.event.extendedProps.sobreturno == 1) {
        info.el.classList.add('evento-sobreturno');
        // Opcional: un borde punteado para diferenciarlo visualmente más allá del color
        info.el.style.borderStyle = 'dashed';
    }
    info.el.style.cursor = 'pointer';
},

// 2. Lógica inteligente al soltar el evento
eventDrop: (info) => {
    const nombre = info.event.title;
    const hora = formatTime(info.event.start);
    
    // 🔥 DETECCIÓN DE SOLAPAMIENTO:
    // Buscamos si en el nuevo horario existen otros eventos (que no sean el mismo que arrastramos)
    const eventosEnEseHorario = calendar.getEvents().filter(e => {
        return e.id !== info.event.id && 
               e.display !== 'background' &&
               ((info.event.start >= e.start && info.event.start < e.end) || 
                (info.event.end > e.start && info.event.end <= e.end));
    });

    let nuevoSobreTurno = info.event.extendedProps.sobreturno;
    let mensajeExtra = "";

    // Si era sobreturno y ahora el lugar está vacío -> Cambiar a 0
    if (nuevoSobreTurno == 1 && eventosEnEseHorario.length === 0) {
        nuevoSobreTurno = 0;
        mensajeExtra = "<br><small class='text-success'>(Se convertirá en turno normal)</small>";
    } 
    // Si era normal y ahora hay alguien -> Cambiar a 1
    else if (nuevoSobreTurno == 0 && eventosEnEseHorario.length > 0) {
        nuevoSobreTurno = 1;
        mensajeExtra = "<br><small class='text-warning'>(Se convertirá en sobreturno)</small>";
    }

    SwalInstance.fire({
        icon: 'question',
        title: 'Mover turno',
        html: `¿Mover turno de <b>${nombre}</b> a las <b>${hora}</b>? ${mensajeExtra}`,
        showCancelButton: true,
        confirmButtonText: 'Mover'
    }).then(result => {
        if (!result.isConfirmed) return info.revert();

        const inicio = formatFechaSQL(info.event.start);
        
        // Enviamos el nuevo valor de sobreturno al servidor
        fetch('secciones/turno_mover.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `id=${info.event.id}&fecha=${inicio}&sobreturno=${nuevoSobreTurno}`
        }).then(r => r.json()).then(resp => {
            if (!resp.ok) {
                info.revert();
                return SwalInstance.fire('Error', resp.error, 'error');
            }
            
            // Actualizamos el objeto en el calendario sin recargar
            info.event.setExtendedProp('sobreturno', nuevoSobreTurno);
            
            // Si cambió a normal, actualizamos el color (asumiendo azul por defecto)
            if (nuevoSobreTurno == 0) {
                info.event.setProp('backgroundColor', '#3a87ad');
                info.event.setProp('borderColor', '#3a87ad');
            } else {
                info.event.setProp('backgroundColor', '#ffb606');
                info.event.setProp('borderColor', '#ffb606');
            }

            SwalInstance.fire({ icon: 'success', title: 'Turno actualizado', timer: 1500, showConfirmButton: false });
        }).catch(() => info.revert());
    });
},

// 3. Forzar orden de visualización
eventOrder: "sobreturno", // Esto hará que el que tenga sobreturno: 1 vaya al final (derecha)

            eventDragStart: (info) => {
                eventoArrastrado = info.event;
                document.getElementById('papelera').classList.add('visible');
            },

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
                    }).then(r => r.json()).then(resp => {
                        if (!resp.ok) return SwalInstance.fire('Error', resp.error, 'error');
                        eventoArrastrado.remove();
                        SwalInstance.fire({ icon: 'success', title: 'Turno eliminado', timer: 1500, showConfirmButton: false });
                    });
                });
            }
        });

        calendar.render();

        // 🔥 PAPELERA hover logic
        document.addEventListener("dragover", (e) => {
            const papelera = document.getElementById("papelera");
            if (!papelera) return;
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