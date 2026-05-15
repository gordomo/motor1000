<x-filament-panels::page>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css">

    <div wire:ignore class="fi-section rounded-xl border border-gray-200 bg-white p-3" style="min-height: 760px;">
        <div id="appointments-calendar"></div>
    </div>

    <style>
        #appointments-calendar {
            min-height: 700px;
        }

        .fc .fc-toolbar-title {
            font-size: 1.1rem;
            font-weight: 700;
        }

        .fc .fc-button {
            border-radius: 10px;
            border: 1px solid rgba(0, 0, 0, 0.08);
            background: var(--tenant-primary, #0f766e);
            color: #fff;
            box-shadow: none;
        }

        .fc .fc-button:not(:disabled):hover {
            filter: brightness(0.95);
        }

        .fc .fc-event {
            border: 0;
            border-radius: 8px;
            padding: 2px 4px;
            background: rgba(var(--tenant-primary-rgb, 15, 118, 110), 0.14);
            color: #0f172a;
        }

        .fc .fc-daygrid-event-dot {
            border-color: var(--tenant-primary, #0f766e);
        }
    </style>

    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
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

            const calendar = new FullCalendar.Calendar(el, {
                locale: 'es',
                initialView: 'timeGridWeek',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
                },
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
        });
    </script>
</x-filament-panels::page>
