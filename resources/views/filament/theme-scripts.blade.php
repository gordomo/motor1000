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
