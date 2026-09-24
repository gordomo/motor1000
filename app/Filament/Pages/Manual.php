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
     * Qué es cada rol, qué pantallas ve y qué puede hacer.
     *
     * No incluye al super administrador a propósito: no usa este panel ni ve
     * datos del taller, así que para quien trabaja acá es ruido.
     *
     * Lo que dice esta lista está verificado contra las clases que deciden el
     * acceso real (ver ManualTest), así que no puede quedar desactualizada en
     * silencio.
     */
    public function getTiposDeUsuarioProperty(): array
    {
        return [
            [
                'nombre'    => __('Administrador'),
                'quien'     => __('El dueño o el encargado.'),
                'color'     => 'primary',
                'pantallas' => __('Todas.'),
                'puede'     => [
                    __('Todo lo que hace el comercial'),
                    __('Dar de alta usuarios y asignarles el rol'),
                    __('Configurar los puntos de revisión del checklist'),
                    __('Editar los datos del taller, el logo y los horarios'),
                    __('Corregir cualquier cobro, incluso el que cargó otro'),
                    __('Borrar cobros'),
                    __('Mover una orden por cualquier estado, si falta alguien'),
                ],
                'no_puede'  => [
                    __('Ver otros talleres: solo administra el suyo'),
                ],
            ],
            [
                'nombre'    => __('Comercial'),
                'quien'     => __('El mostrador: quien atiende al cliente.'),
                'color'     => 'warning',
                'pantallas' => __('Tablero de Órdenes, Presupuestos, Revisiones, Clientes, Vehículos, Inventario, Turnos y calendario, Facturas, Recordatorios, Tareas, Plantillas, Mecánicos, Centro de Operaciones y Órdenes cerradas.'),
                'puede'     => [
                    __('Crear presupuestos, revisiones y órdenes de trabajo'),
                    __('Cargar clientes, autos e inventario'),
                    __('Entregar la orden, que es donde se registra el cobro'),
                    __('Corregir los cobros que registró él mismo'),
                    __('Borrar clientes y órdenes'),
                    __('Ver los números del taller y el informe de cerradas'),
                ],
                'no_puede'  => [
                    __('Entrar a Equipo, Puntos de revisión ni Mi Taller'),
                    __('Borrar cobros, ni corregir los de otra persona'),
                    __('Poner una orden en reparación o darla por completada: eso es del mecánico'),
                ],
            ],
            [
                'nombre'    => __('Mecánico'),
                'quien'     => __('El taller: quien trabaja los autos.'),
                'color'     => 'success',
                'pantallas' => __('Solo el Tablero del taller. Entra directo ahí y no tiene menú lateral.'),
                'puede'     => [
                    __('Tomar una orden con "Me pongo a trabajar"'),
                    __('Hacerse cargo de una orden que quedó sin dueño'),
                    __('Avisar que no puede empezar y dejar el motivo'),
                    __('Marcar cada punto como hecho o como no realizado'),
                    __('Cerrar la orden escribiendo el trabajo realizado'),
                    __('Cambiar su propia contraseña'),
                ],
                'no_puede'  => [
                    __('Ver ningún precio, ni el total del auto que trabaja'),
                    __('Crear, borrar ni entregar órdenes'),
                    __('Entrar a presupuestos, facturación, inventario ni clientes'),
                ],
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
