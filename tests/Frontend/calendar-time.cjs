const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

// Ejecuta los callbacks reales del calendario sin depender de la red/CDN.
const blade = fs.readFileSync('resources/views/filament/pages/appointments-calendar.blade.php', 'utf8');
const source = blade.slice(blade.lastIndexOf('<script>') + 8, blade.lastIndexOf('</script>'))
    .replace('const events = @js($events);', 'const events = [];')
    .replace(/const createUrl = .*;/, 'const createUrl = "/panel/appointments/create";')
    .replace(/const componentId = .*;/, 'const componentId = "test";')
    .replace(/now: @js\(.*\),/, 'now: "2026-11-13T09:00:00",');

for (const timezone of ['America/Argentina/Buenos_Aires', 'Europe/Madrid', 'America/Los_Angeles']) {
    test(`seleccionar y arrastrar conserva la hora del taller desde ${timezone}`, async () => {
        process.env.TZ = timezone;
        let options;
        const calls = [];
        const window = {
            matchMedia: () => ({ matches: false }),
            location: { href: '' },
            Livewire: { find: () => ({ call: async (...args) => calls.push(args) }) },
        };
        const context = {
            window, Date, URLSearchParams,
            document: {
                getElementById: () => ({}),
                addEventListener: (_, callback) => callback(),
            },
            FullCalendar: { Calendar: class {
                constructor(_, config) { options = config; }
                render() {}
            } },
            ResizeObserver: class { observe() {} },
        };
        vm.runInNewContext(source, context);
        assert.equal(options.timeZone, 'UTC');
        const start = new Date('2026-11-16T09:30:00Z');
        options.select({ start, end: new Date('2026-11-16T10:30:00Z'), allDay: false });
        let url = new URL(window.location.href, 'http://localhost');
        assert.equal(url.searchParams.get('scheduled_at'), '2026-11-16 09:30:00');
        assert.equal(url.searchParams.get('duration_minutes'), '60');

        options.select({ start: new Date('2026-11-16T00:00:00Z'), allDay: true });
        url = new URL(window.location.href, 'http://localhost');
        assert.equal(url.searchParams.get('scheduled_at'), '2026-11-16 09:00:00');

        await options.eventDrop({
            event: { id: '1', start, end: new Date('2026-11-16T10:30:00Z') },
            revert: () => assert.fail('No debería revertirse el cambio'),
        });
        assert.deepEqual(calls[0], ['updateAppointmentSchedule', '1', '2026-11-16 09:30:00', '2026-11-16 10:30:00']);
    });
}
