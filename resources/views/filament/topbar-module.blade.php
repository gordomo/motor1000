@php
    $routeName = request()->route()?->getName() ?? '';
    $logoPath = \App\Helpers\TenantBranding::logoPath();
    $primaryColor = \App\Helpers\TenantBranding::primary();

    $module = match (true) {
        str_contains($routeName, 'dashboard') => __('Centro de Operaciones'),
        str_contains($routeName, 'work-orders') => __('Órdenes de servicio'),
        str_contains($routeName, 'customers') => __('Clientes'),
        str_contains($routeName, 'vehicles') => __('Vehículos'),
        str_contains($routeName, 'appointments') => __('Citas'),
        str_contains($routeName, 'inventory-items') => __('Inventario'),
        str_contains($routeName, 'tasks') => __('Tareas'),
        str_contains($routeName, 'reminders') => __('Recordatorios'),
        str_contains($routeName, 'invoices') => __('Facturas'),
        str_contains($routeName, 'mechanics') => __('Mecánicos'),
        str_contains($routeName, 'communication-templates') => __('Plantillas de comunicación'),
        default => __('Panel'),
    };
@endphp

<div class="m1-topbar-context" style="--context-color: {{ $primaryColor }};">
    @if ($logoPath)
        <img src="{{ $logoPath }}" alt="Logo" class="m1-topbar-context__logo">
    @endif
    <span class="m1-topbar-context__eyebrow">{{ __('Módulo activo') }}</span>
    <span class="m1-topbar-context__value">{{ $module }}</span>
</div>