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
        // Imprimir o guardar en PDF: el manual es también el papel que se le deja
        // a alguien que recién entra al taller.
        $imprimir = \Filament\Actions\Action::make('imprimir')
            ->label(__('Imprimir'))
            ->icon('heroicon-o-printer')
            ->color('gray')
            ->extraAttributes(['onclick' => 'window.print()']);

        if (! auth()->user()?->isOnlyMechanic()) {
            return [$imprimir];
        }

        return [
            $imprimir,
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
     * Los tipos de usuario del sistema y para qué sirve cada uno.
     *
     * Incluye al super admin, que no aparecía en ningún lado del manual: no usa
     * este panel (entra por /admin), pero el administrador del taller tiene que
     * saber que existe y qué hace.
     */
    public function getTiposDeUsuarioProperty(): array
    {
        return [
            [
                'nombre' => __('Administrador'),
                'donde'  => __('Este panel'),
                'para'   => __('El dueño o encargado. Hace todo lo del taller y además maneja el equipo, los puntos de revisión y los datos del taller. Es el único que borra cobros y que corrige los cobros que registró otra persona.'),
                'color'  => 'primary',
            ],
            [
                'nombre' => __('Comercial'),
                'donde'  => __('Este panel'),
                'para'   => __('El mostrador. Atiende clientes, presupuesta, factura, maneja inventario y turnos, y ve los números. Entrega las órdenes, que es el paso donde se registra el cobro. Puede corregir sus propios cobros, no los de otros.'),
                'color'  => 'warning',
            ],
            [
                'nombre' => __('Mecánico'),
                'donde'  => __('Este panel'),
                'para'   => __('El taller. Solo ve el tablero con los autos: toma una orden, marca los puntos y la cierra. No ve ningún precio, ni siquiera el total del auto que está trabajando.'),
                'color'  => 'success',
            ],
            [
                'nombre' => __('Super administrador'),
                'donde'  => __('Otro panel (/admin)'),
                'para'   => __('No es del taller: administra la plataforma y puede dar de alta talleres y usuarios. No ve órdenes, presupuestos ni clientes de nadie. Normalmente lo usa solo quien mantiene el sistema.'),
                'color'  => 'gray',
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
            [__('Registrar cobros'),                   true,  true,  false],
            [__('Corregir un cobro'),                  true,  __('Los propios'), false],
            [__('Borrar un cobro'),                    true,  false, false],
            [__('Equipo (usuarios)'),                  true,  false, false],
            [__('Puntos de revisión'),                 true,  false, false],
            [__('Mi Taller'),                          true,  false, false],
            [__('Cambiar su propia contraseña'),       true,  true,  true],
        ];
    }
}
