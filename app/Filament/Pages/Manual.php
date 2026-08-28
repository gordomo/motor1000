<?php

namespace App\Filament\Pages;

use App\Enums\WorkOrderStatus;
use Filament\Pages\Page;

/**
 * Manual de uso, dentro del sistema.
 *
 * Vive acá y no en un documento aparte para que no se desincronice: los estados,
 * los nombres de los botones y los permisos que muestra salen del código, no de
 * una copia escrita a mano.
 *
 * Entran los tres roles. A cada uno le muestra primero la guía de su rol.
 */
class Manual extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-question-mark-circle';

    protected static ?int $navigationSort = 99;

    protected static string $view = 'filament.pages.manual';

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return __('Manual de uso');
    }

    public static function getNavigationLabel(): string
    {
        return __('Manual de uso');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Ayuda');
    }

    /** Lo puede leer cualquiera que use el sistema. */
    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'receptionist', 'mechanic']) ?? false;
    }

    /**
     * El mecánico usa el panel sin barra lateral, así que necesita una salida
     * explícita para volver a su pantalla de trabajo.
     */
    protected function getHeaderActions(): array
    {
        if (! auth()->user()?->isOnlyMechanic()) {
            return [];
        }

        return [
            \Filament\Actions\Action::make('volver')
                ->label(__('Volver al tablero'))
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn (): string => MechanicBoard::getUrl()),
        ];
    }

    /** El rol con el que se abre el manual, para mostrar su guía primero. */
    public function getRolProperty(): string
    {
        $user = auth()->user();

        return match (true) {
            $user?->hasRole('admin')        => 'admin',
            $user?->hasRole('receptionist') => 'comercial',
            $user?->hasRole('mechanic')     => 'mecanico',
            default                         => 'comercial',
        };
    }

    /**
     * Los pasos del circuito, armados desde el enum de estados: si mañana cambia
     * un nombre de estado, el manual cambia solo.
     */
    public function getCircuitoProperty(): array
    {
        return [
            [
                'estado' => WorkOrderStatus::Received->getLabel(),
                'quien'  => __('Se crea en el mostrador'),
                'que'    => __('Con el cliente, el auto, el kilometraje y qué se le va a hacer. El kilometraje es obligatorio: se completa solo con el último registrado.'),
                'color'  => 'gray',
            ],
            [
                'estado' => WorkOrderStatus::Repairing->getLabel(),
                'quien'  => __('Lo mueve el mecánico'),
                'que'    => __('Toca "Me pongo a trabajar" y elige su nombre. Si le falta algo, usa "No puedo empezar": la orden queda marcada con el motivo y no se mueve de lugar.'),
                'color'  => 'info',
            ],
            [
                'estado' => WorkOrderStatus::Completed->getLabel(),
                'quien'  => __('Lo mueve el mecánico'),
                'que'    => __('Hay que marcar todos los puntos y escribir el trabajo realizado. El trabajo queda listo para cobrar y los repuestos se descuentan del stock.'),
                'color'  => 'success',
            ],
            [
                'estado' => WorkOrderStatus::Delivered->getLabel(),
                'quien'  => __('Lo mueve el comercial'),
                'que'    => __('Pide la forma de pago y el monto. Ese es el momento en que la plata queda registrada como cobrada. Si la orden es sin cargo, no pide nada.'),
                'color'  => 'warning',
            ],
        ];
    }

    /**
     * Qué ve cada rol. Se consulta a las mismas clases que deciden el acceso
     * real, así la tabla no puede mentir.
     */
    public function getPermisosProperty(): array
    {
        return [
            [__('Tablero del taller'),                 true,  false, true],
            [__('Órdenes de trabajo'),                 true,  true,  __('Desde el tablero')],
            [__('Presupuestos'),                       true,  true,  false],
            [__('Revisiones'),                         true,  true,  false],
            [__('Clientes y vehículos'),               true,  true,  false],
            [__('Inventario'),                         true,  true,  false],
            [__('Turnos y calendario'),                true,  true,  false],
            [__('Facturación'),                        true,  true,  false],
            [__('Centro de Operaciones (los números)'), true,  true,  false],
            [__('Órdenes cerradas (informe)'),         true,  true,  false],
            [__('Equipo (usuarios)'),                  true,  false, false],
            [__('Puntos de revisión'),                 true,  false, false],
            [__('Mi Taller'),                          true,  false, false],
            [__('Cambiar su propia contraseña'),       true,  true,  true],
        ];
    }
}
