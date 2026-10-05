<script defer src="https://unpkg.com/lucide@latest"></script>
<script>
    const initializeMotor1000Ui = () => {
        if (window.lucide) {
            window.lucide.createIcons();
        }
    };

    document.addEventListener('DOMContentLoaded', initializeMotor1000Ui);
    document.addEventListener('livewire:navigated', initializeMotor1000Ui);
</script>
<script>
    // Livewire trae en inglés el aviso de sesión vencida ("This page has expired").
    // Aparece sobre todo al volver con la flecha del navegador a una página vieja.
    const avisoPaginaVencidaEnEspanol = () => {
        window.Livewire.hook('request', ({ fail }) => {
            fail(({ status, preventDefault }) => {
                if (status !== 419) {
                    return;
                }

                preventDefault();

                if (confirm('La página quedó desactualizada.\n¿Querés recargarla?')) {
                    window.location.reload();
                }
            });
        });
    };

    if (window.Livewire) {
        avisoPaginaVencidaEnEspanol();
    } else {
        document.addEventListener('livewire:init', avisoPaginaVencidaEnEspanol);
    }
</script>
<script>
    // La ruedita del mouse sobre un campo numérico o de fecha/hora le cambiaba
    // el valor (un monto, el horario del turno) sin que el usuario se diera
    // cuenta. Algunos navegadores lo hacen con solo tener el mouse encima, sin
    // clic: por eso se cancela siempre, y la página (o el modal) se scrollea a
    // mano para que no quede trabada al pasar por el campo.
    const camposSinRueda = ['number', 'time', 'date', 'datetime-local', 'month', 'week'];

    const contenedorScrolleable = (desde) => {
        for (let el = desde.parentElement; el; el = el.parentElement) {
            const overflow = getComputedStyle(el).overflowY;
            if (/(auto|scroll)/.test(overflow) && el.scrollHeight > el.clientHeight) {
                return el;
            }
        }

        return document.scrollingElement;
    };

    document.addEventListener('wheel', (evento) => {
        const campo = evento.target;

        if (! (campo instanceof HTMLInputElement) || ! camposSinRueda.includes(campo.type)) {
            return;
        }

        evento.preventDefault();

        if (document.activeElement === campo) {
            campo.blur();
        }

        contenedorScrolleable(campo)?.scrollBy({ top: evento.deltaY, left: evento.deltaX });
    }, { capture: true, passive: false });
</script>
