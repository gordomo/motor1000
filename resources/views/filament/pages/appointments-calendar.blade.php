<x-filament-panels::page>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css">

    <div wire:ignore class="fi-section rounded-xl border border-gray-200 bg-white p-2 sm:p-3">
        <div class="calendario-leyenda">
            <span><i class="evento-turno"></i> {{ __('Turno') }}</span>
            <span><i class="evento-entrega"></i> {{ __('Entrega prevista') }}</span>
            <span><i class="evento-cancelado"></i> {{ __('Cancelado / no asistió') }}</span>
        </div>
        <div id="appointments-calendar"></div>
    </div>

    <style>
        /* Colores de los eventos: sólidos y con texto de alto contraste. Antes el
           fondo era el color del taller al 14% con texto blanco y casi no se leía. */
        #appointments-calendar {
            --fc-event-bg-color: var(--tenant-primary, #0f766e);
            --fc-event-border-color: var(--tenant-primary, #0f766e);
            --fc-event-text-color: #fff;
            --fc-today-bg-color: rgba(var(--tenant-primary-rgb, 15, 118, 110), 0.06);
        }

        .fc .fc-toolbar-title {
            font-size: 1.1rem;
            font-weight: 700;
        }

        .fc .fc-toolbar-title::first-letter {
            text-transform: uppercase;
        }

        .fc .fc-button {
            border-radius: 10px;
            border: 1px solid rgba(0, 0, 0, 0.08);
            background: var(--tenant-primary, #0f766e);
            color: #fff;
            box-shadow: none;
            text-transform: capitalize;
        }

        .fc .fc-button:not(:disabled):hover {
            filter: brightness(0.95);
        }

        .fc .fc-event {
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
        }

        .fc .fc-event .fc-event-main {
            padding: 1px 4px;
        }

        .fc .fc-event-time {
            font-weight: 700;
            flex-shrink: 0; /* en el mes, la hora no se recorta: se acorta el título */
        }

        /* Entregas previstas de órdenes: ámbar con texto oscuro. */
        .fc .fc-event.evento-entrega,
        .calendario-leyenda i.evento-entrega {
            background-color: #f59e0b;
            border-color: #d97706;
        }

        .fc .fc-event.evento-entrega,
        .fc .fc-event.evento-entrega .fc-event-main {
            color: #1f2937;
        }

        /* Cancelados y ausentes: gris y tachado, para que no se confundan con los vigentes. */
        .fc .fc-event.evento-cancelado,
        .calendario-leyenda i.evento-cancelado {
            background-color: #e5e7eb;
            border-color: #d1d5db;
        }

        .fc .fc-event.evento-cancelado,
        .fc .fc-event.evento-cancelado .fc-event-main {
            color: #6b7280;
            text-decoration: line-through;
        }

        /* Vista lista: el puntito toma el color de cada tipo. */
        .fc .fc-list-event-dot {
            border-color: var(--tenant-primary, #0f766e);
        }

        .fc .evento-entrega .fc-list-event-dot {
            border-color: #f59e0b;
        }

        .fc .evento-cancelado .fc-list-event-dot {
            border-color: #9ca3af;
        }

        /* En la agenda el color va solo en el puntito, no en toda la fila. */
        .fc .fc-list-event.evento-entrega,
        .fc .fc-list-event.evento-cancelado {
            background: none;
        }

        .fc .fc-list-event.evento-entrega td {
            color: #1f2937;
        }

        .calendario-leyenda {
            display: flex;
            flex-wrap: wrap;
            gap: 0.25rem 1rem;
            margin-bottom: 0.75rem;
            font-size: 0.8rem;
            color: #4b5563;
        }

        .calendario-leyenda span {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
        }

        .calendario-leyenda i {
            width: 0.75rem;
            height: 0.75rem;
            border-radius: 3px;
            border: 1px solid transparent;
        }

        .calendario-leyenda i.evento-turno {
            background-color: var(--tenant-primary, #0f766e);
        }

        /* Celular: barra en filas (navegación+título arriba, vistas abajo) y textos más chicos. */
        @media (max-width: 767px) {
            .fc .fc-toolbar.fc-header-toolbar {
                margin-bottom: 0.5rem;
            }

            .fc .fc-toolbar-title {
                font-size: 1rem;
                text-align: center;
            }

            .fc .fc-button {
                padding: 0.3rem 0.6rem;
                font-size: 0.85rem;
            }

            .fc .fc-toolbar.fc-footer-toolbar {
                margin-top: 0.5rem;
            }

            .fc .fc-col-header-cell-cushion,
            .fc .fc-daygrid-day-number {
                font-size: 0.75rem;
                padding: 2px;
            }

            .fc .fc-daygrid-event {
                font-size: 0.7rem;
            }
        }
    </style>

    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
    {{-- Sin este archivo, locale: 'es' no tiene efecto y los botones quedan en inglés (today, week, day...). --}}
    <script src="https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.15/locales/es.global.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const el = document.getElementById('appointments-calendar');
            if (!el) {
                return;
            }

            const events = @js($events);
            const createUrl = @js(\App\Filament\Resources\AppointmentResource::getUrl('create'));
            const componentId = @js($this->getId());
            const getWire = () => window.Livewire?.find(componentId) ?? null;

            const formatDateTimeForQuery = function (date) {
                const pad = (value) => String(value).padStart(2, '0');

                return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}:00`;
            };

            const persistEventChange = async function (info) {
                const wire = getWire();

                if (!wire) {
                    info.revert();
                    return;
                }

                try {
                    const start = info.event.start ? info.event.start.toISOString() : null;
                    const end = info.event.end ? info.event.end.toISOString() : null;

                    if (!start) {
                        info.revert();
                        return;
                    }

                    await wire.call('updateAppointmentSchedule', info.event.id, start, end);
                } catch (e) {
                    info.revert();
                }
            };

            // En el celular la semana (7 columnas) no se lee: se ofrecen mes, día y lista,
            // y los botones de vista pasan abajo para que el título tenga su fila.
            const esCelular = () => window.matchMedia('(max-width: 767px)').matches;
            const barras = () => esCelular()
                ? {
                    headerToolbar: { left: 'prev,next', center: 'title', right: 'today' },
                    footerToolbar: { center: 'dayGridMonth,timeGridDay,listWeek' },
                }
                : {
                    headerToolbar: {
                        left: 'prev,next today',
                        center: 'title',
                        right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek',
                    },
                    footerToolbar: false,
                };

            const calendar = new FullCalendar.Calendar(el, {
                locale: 'es',
                initialView: 'dayGridMonth',
                ...barras(),
                windowResize: function () {
                    const b = barras();
                    calendar.setOption('headerToolbar', b.headerToolbar);
                    calendar.setOption('footerToolbar', b.footerToolbar);
                },
                height: 'auto',
                allDaySlot: false,
                // Mes: tarjetas de color (no puntitos) y "+N más" si el día se llena.
                eventDisplay: 'block',
                dayMaxEvents: true,
                // Tocar el número de un día abre ese día (cómodo en el celular).
                navLinks: true,
                slotMinTime: '07:00:00',
                slotMaxTime: '21:00:00',
                nowIndicator: true,
                editable: true,
                eventStartEditable: true,
                eventDurationEditable: true,
                selectable: true,
                selectMirror: true,
                eventTimeFormat: {
                    hour: '2-digit',
                    minute: '2-digit',
                    meridiem: false,
                },
                events,
                select: function (info) {
                    const start = new Date(info.start);

                    if (info.allDay) {
                        start.setHours(9, 0, 0, 0);
                    }

                    const end = info.end ? new Date(info.end) : new Date(start.getTime() + 60 * 60 * 1000);

                    let durationMinutes = Math.max(15, Math.round((end.getTime() - start.getTime()) / 60000));

                    if (info.allDay) {
                        durationMinutes = 60;
                    }

                    const params = new URLSearchParams({
                        scheduled_at: formatDateTimeForQuery(start),
                        duration_minutes: String(durationMinutes),
                    });

                    window.location.href = `${createUrl}?${params.toString()}`;
                },
                eventDrop: persistEventChange,
                eventResize: persistEventChange,
                eventClick: function (info) {
                    if (info.event.url) {
                        info.jsEvent.preventDefault();
                        window.location.href = info.event.url;
                    }
                },
                eventDidMount: function (info) {
                    const props = info.event.extendedProps || {};
                    const details = [
                        'Estado: ' + (props.estado || '-'),
                        'Mecánico: ' + (props.mecanico || '-'),
                        'Vehículo: ' + (props.vehiculo || '-')
                    ];

                    info.el.title = details.join(' | ');
                },
            });

            calendar.render();

            // FullCalendar mide el ancho al arrancar (antes de que el panel acomode el menú
            // lateral) y solo vuelve a medir si cambia la ventana: el mes quedaba cortado.
            // Se recalcula cada vez que cambia el tamaño del contenedor.
            new ResizeObserver(() => calendar.updateSize()).observe(el);
        });
    </script>
</x-filament-panels::page>
