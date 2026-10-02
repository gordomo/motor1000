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
