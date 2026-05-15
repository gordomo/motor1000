@php
    $routeName = request()->route()?->getName() ?? '';
    $logoPath = \App\Helpers\TenantBranding::logoPath();
    $primaryColor = \App\Helpers\TenantBranding::primary();

    $module = match (true) {
        str_contains($routeName, 'dashboard') => 'Centro de Operaciones',
        str_contains($routeName, 'work-orders') => 'Órdenes de servicio',
        str_contains($routeName, 'customers') => 'Clientes',
        str_contains($routeName, 'vehicles') => 'Vehículos',
        str_contains($routeName, 'appointments') => 'Citas',
        str_contains($routeName, 'inventory-items') => 'Inventario',
        str_contains($routeName, 'tasks') => 'Tareas',
        str_contains($routeName, 'reminders') => 'Recordatorios',
        str_contains($routeName, 'invoices') => 'Facturas',
        str_contains($routeName, 'mechanics') => 'Mecánicos',
        str_contains($routeName, 'communication-templates') => 'Plantillas de comunicación',
        default => 'Panel',
    };
@endphp

<div class="m1-topbar-context" style="--context-color: {{ $primaryColor }};">
    @if ($logoPath)
        <img src="{{ $logoPath }}" alt="Logo" class="m1-topbar-context__logo">
    @endif
    <span class="m1-topbar-context__eyebrow">Módulo activo</span>
    <span class="m1-topbar-context__value">{{ $module }}</span>
</div>