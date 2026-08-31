<x-filament-panels::page>
    @php($rol = $this->rol)

    {{-- Manual dentro del sistema. Usa los componentes del panel para que se vea
         como el resto y no como una página pegada aparte. --}}

    {{-- Antes esto era una x-filament::section con encabezado y sin cuerpo, y el
         recuadro vacío parecía un error de la página. --}}
    <p class="text-sm text-gray-600 dark:text-gray-400">
        {{ __('Estás entrando como :rol. Abajo está tu guía, y también lo que hacen los demás, para saber a quién pedirle cada cosa.', [
            'rol' => match ($rol) {
                'admin'     => __('Administrador'),
                'comercial' => __('Comercial'),
                default     => __('Mecánico'),
            },
        ]) }}
    </p>

    {{-- ── Tu guía, primero ──────────────────────────────────────────── --}}

    @if ($rol === 'mecanico')
        <x-filament::section icon="heroicon-o-wrench" icon-color="success">
            <x-slot name="heading">{{ __('Tu guía: el Tablero del taller') }}</x-slot>
            <x-slot name="description">{{ __('Es tu única pantalla. Los autos aparecen agrupados por su estado y la patente va en grande.') }}</x-slot>

            <ol class="ml-4 list-decimal space-y-3 text-sm">
                <li>
                    <strong>{{ __('Elegí un auto de "Recibido"') }}</strong><br>
                    {{ __('Tocá "Me pongo a trabajar". El sistema pregunta quién sos y muestra los nombres en botones grandes. El auto queda a tu cargo.') }}
                </li>
                <li>
                    <strong>{{ __('Si no podés arrancar, avisá') }}</strong><br>
                    {{ __('Tocá "No puedo empezar" y escribí qué falta: un repuesto, una herramienta. La orden queda marcada en rojo y el mostrador lo ve.') }}
                </li>
                <li>
                    <strong>{{ __('Marcá cada punto mientras trabajás') }}</strong><br>
                    {{ __('Cada punto tiene "Hecho" y "No se pudo". Si elegís "No se pudo", el motivo es obligatorio. Arriba de la lista se ve de dónde salen los puntos y cuántos llevás.') }}
                </li>
                <li>
                    <strong>{{ __('Cerrá el trabajo') }}</strong><br>
                    {{ __('Con todos los puntos marcados se habilita "Terminé el trabajo". Te pide contar qué hiciste, y eso queda en el comprobante del cliente.') }}
                </li>
            </ol>

            <div class="mt-4 rounded-lg border-l-4 border-danger-500 bg-danger-50 p-3 text-sm dark:bg-danger-500/10">
                <p class="font-semibold text-danger-700 dark:text-danger-400">{{ __('Ojo con este botón') }}</p>
                <p class="text-gray-700 dark:text-gray-300">
                    {{ __('"Terminé el trabajo" le avisa al cliente que su auto está listo. No lo uses para probar.') }}
                </p>
            </div>

            <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">
                {{ __('Todo lo demás se puede volver atrás: si marcaste un punto por error, la flecha que aparece al lado lo devuelve a sin marcar. Marcar puntos no mueve la orden ni le avisa a nadie.') }}
            </p>
        </x-filament::section>
    @endif

    @if ($rol === 'comercial' || $rol === 'admin')
        <x-filament::section icon="heroicon-o-clipboard-document-list" icon-color="warning">
            <x-slot name="heading">{{ __('Presupuestar') }}</x-slot>

            <p class="text-sm">{{ __('Al crear un presupuesto elegís el tipo:') }}</p>
            <ul class="ml-4 mt-2 list-disc space-y-2 text-sm">
                <li>{{ __('Con revisión: incluye el checklist y hay que completarlo. Cada punto se marca BIEN, REGULAR o MAL, y en los dos últimos se aclara qué se vio.') }}</li>
                <li>{{ __('Sin revisión: presupuesto directo, sin checklist. Para un cambio de aceite o un trabajo simple.') }}</li>
            </ul>
            <p class="mt-3 text-sm">{{ __('El kilometraje es obligatorio y queda impreso en el PDF, así se sabe con qué kilometraje se cotizó.') }}</p>
            <p class="mt-3 text-sm">
                <strong>{{ __('Del presupuesto a la orden:') }}</strong>
                {{ __('cuando el cliente aprueba, la orden hereda solo los puntos que quedaron en REGULAR o MAL. Eso es lo que el mecánico va a trabajar; lo que estaba bien no se toca.') }}
            </p>
        </x-filament::section>

        <x-filament::section icon="heroicon-o-banknotes" icon-color="success">
            <x-slot name="heading">{{ __('Entregar y cobrar') }}</x-slot>
            <x-slot name="description">{{ __('Una orden completada es trabajo terminado listo para cobrar. Al entregarla se registra el ingreso.') }}</x-slot>

            <ul class="ml-4 list-disc space-y-2 text-sm">
                <li>{{ __('Cada cobro queda registrado con su fecha, su monto y la forma de pago. En los números aparece en la fecha en que entró la plata.') }}</li>
                <li>{{ __('Si el cliente paga una parte, la orden queda como pago parcial y el saldo sigue contando en "Por cobrar", incluso si ya se llevó el auto.') }}</li>
                <li>{{ __('Una orden sin cargo se entrega sin pedir nada y se cuenta aparte, no como plata.') }}</li>
            </ul>
        </x-filament::section>

        <x-filament::section icon="heroicon-o-chart-bar" icon-color="info">
            <x-slot name="heading">{{ __('Los números') }}</x-slot>
            <x-slot name="description">{{ __('En el Centro de Operaciones, con el filtro de fechas arriba. Arranca en el mes actual.') }}</x-slot>

            <ul class="ml-4 list-disc space-y-2 text-sm">
                <li><strong>{{ __('Presupuestado:') }}</strong> {{ __('cuánto se cotizó, cuántos se aprobaron y el porcentaje de conversión.') }}</li>
                <li><strong>{{ __('Por cobrar:') }}</strong> {{ __('todo lo que el taller tiene para cobrar: el trabajo terminado que no se entregó, más los autos ya entregados con saldo pendiente.') }}</li>
                <li><strong>{{ __('Cobrado:') }}</strong> {{ __('la plata que entró de verdad, separada por forma de pago.') }}</li>
                <li><strong>{{ __('Por rubro:') }}</strong> {{ __('mano de obra, repuestos y otros, con los descuentos aparte.') }}</li>
            </ul>
            <p class="mt-3 text-sm">{{ __('En Órdenes cerradas ves cuántas se cerraron hoy, esta semana y este mes, con promedios. Se baja en PDF para imprimir o en Excel para trabajar los datos.') }}</p>
        </x-filament::section>

        <x-filament::section icon="heroicon-o-cube" icon-color="gray">
            <x-slot name="heading">{{ __('Inventario') }}</x-slot>
            <p class="text-sm">
                {{ __('Al cargar una pieza en una orden podés elegir el repuesto del inventario: completa la descripción y el precio, y el stock baja solo cuando la orden pasa a Completado. Si reabrís una orden cerrada, los repuestos vuelven al stock. Cada mañana a las 8 llega un aviso a la campanita con los repuestos por debajo del mínimo.') }}
            </p>
        </x-filament::section>
    @endif

    @if ($rol === 'admin')
        <x-filament::section icon="heroicon-o-users" icon-color="primary">
            <x-slot name="heading">{{ __('Solo vos: el equipo y la configuración') }}</x-slot>

            <div class="space-y-4 text-sm">
                <div>
                    <p class="font-semibold">{{ __('Equipo') }}</p>
                    <p>{{ __('En Equipo → Crear das de alta a cada persona con su correo, su contraseña y su rol. Podés asignar más de un rol. No podés borrarte a vos mismo, para que el taller no quede sin administrador.') }}</p>
                </div>

                <div class="rounded-lg bg-gray-50 p-3 dark:bg-white/5">
                    <p class="font-semibold">{{ __('Usuario y mecánico son dos cosas distintas') }}</p>
                    <p>{{ __('El usuario es quien entra al sistema con correo y contraseña. El mecánico es el nombre que aparece cuando alguien toca "Me pongo a trabajar", y se carga en Mecánicos. Para una tablet compartida alcanza un usuario, y un mecánico cargado por cada persona: así la sesión es una sola pero cada uno queda identificado en el auto que trabajó.') }}</p>
                </div>

                <div>
                    <p class="font-semibold">{{ __('Puntos de revisión') }}</p>
                    <p>{{ __('En Configuraciones → Puntos de revisión definís tu propia lista: agregar, editar, cambiar el orden arrastrando y desactivar los que no uses. Los presupuestos ya emitidos no cambian nunca: cada uno sigue mostrando lo que se revisó ese día.') }}</p>
                </div>

                <div>
                    <p class="font-semibold">{{ __('Mi Taller') }}</p>
                    <p>{{ __('Los datos del taller, el logo y los horarios de atención. Los horarios son los que se usan para ofrecer turnos.') }}</p>
                </div>
            </div>
        </x-filament::section>
    @endif

    {{-- ── El circuito, igual para todos ─────────────────────────────── --}}

    <x-filament::section icon="heroicon-o-arrow-path" collapsible>
        <x-slot name="heading">{{ __('El recorrido de una orden') }}</x-slot>
        <x-slot name="description">{{ __('Cuatro estados, siempre en el mismo orden. Se llaman igual en todas las pantallas.') }}</x-slot>

        <div class="space-y-3">
            @foreach ($this->circuito as $paso)
                <div class="flex flex-col gap-1 border-l-4 border-{{ $paso['color'] }}-500 bg-gray-50 py-2 pl-4 dark:bg-white/5 sm:flex-row sm:items-baseline sm:gap-4">
                    <div class="sm:w-48 sm:shrink-0">
                        <p class="font-semibold">{{ $paso['estado'] }}</p>
                        <p class="text-xs uppercase tracking-wide text-gray-500">{{ $paso['quien'] }}</p>
                    </div>
                    <p class="text-sm text-gray-700 dark:text-gray-300">{{ $paso['que'] }}</p>
                </div>
            @endforeach
        </div>

        <p class="mt-4 text-sm text-gray-600 dark:text-gray-400">
            <strong>{{ __('"Trabada" no es un estado.') }}</strong>
            {{ __('Es una marca sobre la orden, para que el mostrador vea que falta algo sin que el auto cambie de columna. Cuando se resuelve, se destraba y el trabajo arranca.') }}
        </p>
    </x-filament::section>

    {{-- ── Quién ve qué ──────────────────────────────────────────────── --}}

    <x-filament::section icon="heroicon-o-lock-closed" collapsible collapsed>
        <x-slot name="heading">{{ __('Quién ve qué') }}</x-slot>
        <x-slot name="description">{{ __('Si una pantalla no aparece en tu menú, es porque tu usuario no la necesita.') }}</x-slot>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[34rem] text-sm">
                <thead>
                    <tr class="border-b-2 border-gray-200 text-left text-xs uppercase tracking-wide text-gray-500 dark:border-white/10">
                        <th class="py-2 pr-4 font-medium">{{ __('Pantalla') }}</th>
                        <th class="px-3 py-2 text-center font-medium">{{ __('Administrador') }}</th>
                        <th class="px-3 py-2 text-center font-medium">{{ __('Comercial') }}</th>
                        <th class="px-3 py-2 text-center font-medium">{{ __('Mecánico') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->permisos as [$pantalla, $admin, $comercial, $mecanico])
                        <tr class="border-b border-gray-100 dark:border-white/5">
                            <td class="py-2 pr-4">{{ $pantalla }}</td>
                            @foreach ([$admin, $comercial, $mecanico] as $valor)
                                <td class="px-3 py-2 text-center">
                                    @if ($valor === true)
                                        <span class="font-semibold text-success-600">{{ __('Sí') }}</span>
                                    @elseif ($valor === false)
                                        <span class="text-gray-300 dark:text-gray-600">—</span>
                                    @else
                                        <span class="text-xs font-semibold text-warning-600">{{ $valor }}</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">
            {{ __('Borrar clientes y órdenes lo pueden hacer el administrador y el comercial. El mecánico no borra nada.') }}
        </p>
    </x-filament::section>

    {{-- ── Dudas frecuentes ──────────────────────────────────────────── --}}

    <x-filament::section icon="heroicon-o-question-mark-circle" collapsible collapsed>
        <x-slot name="heading">{{ __('Dudas frecuentes') }}</x-slot>

        <div class="space-y-3 text-sm">
            @foreach ([
                [__('¿Por qué no puedo cerrar una orden?'), __('Por una de dos razones: quedan puntos sin marcar, o falta escribir el trabajo realizado. Si algún punto quedó como "No se pudo", tiene que tener el motivo escrito.')],
                [__('Una orden tiene puntos que no tienen que ver con la falla'), __('Son órdenes viejas, creadas antes de este circuito y sin presupuesto: el sistema les había puesto una lista genérica. Arriba de la lista, la tarjeta aclara si los puntos vienen de un presupuesto o si estaban cargados en la orden.')],
                [__('¿Puedo probar los botones sin romper nada?'), __('Sí. Marcar puntos, desmarcarlos y tomar una orden se deshace y no le avisa a nadie. La única excepción es "Terminé el trabajo", que avanza la orden y le notifica al cliente.')],
                [__('¿Dónde cambio mi contraseña?'), __('Arriba a la derecha, en el menú con la inicial de tu nombre. Y si te la olvidaste, en la pantalla de ingreso está el link para recuperarla por correo.')],
                [__('¿Por qué el mecánico no ve los precios?'), __('Porque no los necesita para trabajar, y su tablero está pensado para una tablet compartida en el taller.')],
                [__('Los presupuestos ya no dicen "Borrador"'), __('Ahora hay tres estados: Pendiente de aprobación, Aprobado y Rechazado. Los que estaban en Borrador o Enviado pasaron a Pendiente de aprobación. No se perdió ninguno.')],
                [__('¿Cómo se marca que un trabajo fue sin cargo?'), __('No hace falta marcar nada: si la orden no tiene importe, el sistema la toma como sin cargo. Se entrega sin pedir forma de pago y en los números aparece contada aparte.')],
                [__('Las fotos del auto, ¿para qué sirven?'), __('Son el respaldo ante un reclamo. Hasta 20 fotos al ingreso y otras 20 en la entrega. Sacale fotos a los rayones y golpes cuando recibís el auto.')],
            ] as [$pregunta, $respuesta])
                <details class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
                    <summary class="cursor-pointer font-semibold">{{ $pregunta }}</summary>
                    <p class="mt-2 text-gray-700 dark:text-gray-300">{{ $respuesta }}</p>
                </details>
            @endforeach
        </div>
    </x-filament::section>

    <p class="text-sm text-gray-500">
        {{ __('Si algo no funciona como dice acá, avisá: puede ser un error del sistema o del manual, y los dos se arreglan.') }}
    </p>
</x-filament-panels::page>
