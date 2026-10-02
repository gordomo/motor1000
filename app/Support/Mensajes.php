<?php

namespace App\Support;

use App\Models\CommunicationTemplate;
use Illuminate\Support\Str;

/**
 * Los textos de los mensajes que el sistema arma para el cliente.
 *
 * Cada uno tiene un texto por defecto. Si el taller cargó una plantilla activa
 * para ese evento y canal (Plantillas de comunicación), se usa esa: así el
 * comercial puede cambiar los textos sin tocar el código. Las variables van
 * entre llaves: {nombre}, {taller}...
 *
 * Cuando se conecte WhatsApp (docs/WHATSAPP.md), estos mismos textos son los
 * que hay que mandar a Meta para que los apruebe como plantillas.
 */
class Mensajes
{
    /**
     * @return array<string, array{nombre: string, canales: array<string, array{cuerpo: string, asunto?: string}>, variables: array<string, string>}>
     */
    public static function catalogo(): array
    {
        return [
            'cumpleanos' => [
                'nombre'    => __('Saludo de cumpleaños'),
                'variables' => ['nombre' => __('Nombre del cliente'), 'taller' => __('Nombre del taller')],
                'canales'   => [
                    'whatsapp' => ['cuerpo' => '¡Hola {nombre}! Desde {taller} te deseamos un muy feliz cumpleaños. ¡Que lo pases genial!'],
                ],
            ],
            'recordatorio' => [
                'nombre'    => __('Recordatorio de mantenimiento'),
                'variables' => [
                    'nombre' => __('Nombre del cliente'), 'taller' => __('Nombre del taller'),
                    'recordatorio' => __('Título del recordatorio (ej. Cambio de aceite)'), 'patente' => __('Patente del vehículo'),
                ],
                'canales' => [
                    'whatsapp' => ['cuerpo' => 'Hola {nombre}, te escribimos de {taller}. Te recordamos: {recordatorio} ({patente}). ¿Querés que te reservemos un turno?'],
                ],
            ],
            'turno_manana' => [
                'nombre'    => __('Aviso del turno de mañana'),
                'variables' => [
                    'nombre' => __('Nombre del cliente'), 'taller' => __('Nombre del taller'),
                    'hora' => __('Hora del turno'), 'servicio' => __('Servicio del turno'),
                ],
                'canales' => [
                    'whatsapp' => ['cuerpo' => 'Hola {nombre}, te escribimos de {taller} para recordarte tu turno de mañana a las {hora} ({servicio}). ¿Nos confirmás que venís?'],
                ],
            ],
            'presupuesto' => [
                'nombre'    => __('Envío de presupuesto'),
                'variables' => [
                    'nombre' => __('Nombre del cliente'), 'taller' => __('Nombre del taller'), 'codigo' => __('Número del presupuesto'),
                    'vehiculo' => __('Vehículo'), 'link' => __('Link al PDF del presupuesto'),
                ],
                'canales' => [
                    'whatsapp' => ['cuerpo' => "Hola {nombre}, le enviamos el presupuesto {codigo} de {vehiculo}.\n\nPuede verlo aquí: {link}"],
                ],
            ],
            // La clave 'vehicle_ready' es la que ya usaban las plantillas viejas.
            'vehicle_ready' => [
                'nombre'    => __('Vehículo listo para retirar'),
                'variables' => [
                    'nombre' => __('Nombre del cliente'), 'taller' => __('Nombre del taller'),
                    'vehiculo' => __('Vehículo'), 'orden' => __('Número de orden'),
                ],
                'canales' => [
                    'whatsapp' => ['cuerpo' => 'Hola {nombre}, tu vehículo {vehiculo} ya está listo para retirar. Orden: {orden}'],
                    'email'    => [
                        'asunto' => 'Tu vehículo está listo - Orden {orden}',
                        'cuerpo' => "Hola {nombre},\n\nTu vehículo {vehiculo} ya está listo para retirar. Orden: {orden}.\n\n{taller}",
                    ],
                ],
            ],
        ];
    }

    /** @return array<string, string> [evento => nombre] */
    public static function opciones(): array
    {
        return collect(self::catalogo())->map(fn (array $m): string => $m['nombre'])->all();
    }

    public static function cuerpo(string $evento, string $canal, array $variables): string
    {
        $texto = self::plantilla($evento, $canal)?->body
            ?? self::catalogo()[$evento]['canales'][$canal]['cuerpo']
            ?? '';

        return self::completar($texto, $variables);
    }

    public static function asunto(string $evento, string $canal, array $variables): ?string
    {
        $texto = self::plantilla($evento, $canal)?->subject
            ?: (self::catalogo()[$evento]['canales'][$canal]['asunto'] ?? null);

        return $texto === null ? null : self::completar($texto, $variables);
    }

    /** La plantilla activa del taller actual para ese evento y canal, si hay. */
    public static function plantilla(string $evento, string $canal): ?CommunicationTemplate
    {
        return CommunicationTemplate::query()
            ->where('event', $evento)
            ->where('channel', $canal)
            ->where('is_active', true)
            ->latest('updated_at')
            ->first();
    }

    /** @return list<array{evento: string, canal: string}> mensajes sin ninguna plantilla en el taller actual */
    public static function faltantes(): array
    {
        $existentes = CommunicationTemplate::query()
            ->get(['event', 'channel'])
            ->map(fn (CommunicationTemplate $t): string => $t->event . '|' . $t->channel)
            ->all();

        $faltan = [];
        foreach (self::catalogo() as $evento => $mensaje) {
            foreach (array_keys($mensaje['canales']) as $canal) {
                if (! in_array($evento . '|' . $canal, $existentes, true)) {
                    $faltan[] = ['evento' => $evento, 'canal' => $canal];
                }
            }
        }

        return $faltan;
    }

    /** Crea las plantillas que faltan, con el texto de siempre. Devuelve cuántas creó. */
    public static function crearFaltantes(): int
    {
        $faltan = self::faltantes();

        foreach ($faltan as ['evento' => $evento, 'canal' => $canal]) {
            $mensaje = self::catalogo()[$evento];

            CommunicationTemplate::create([
                'tenant_id' => CurrentTenant::id(),
                'name'      => $mensaje['nombre'] . ' (' . ($canal === 'email' ? 'Email' : 'WhatsApp') . ')',
                'slug'      => self::slugNuevo($evento, $canal),
                'channel'   => $canal,
                'event'     => $evento,
                'subject'   => $mensaje['canales'][$canal]['asunto'] ?? null,
                'body'      => $mensaje['canales'][$canal]['cuerpo'],
                'is_active' => true,
            ]);
        }

        return count($faltan);
    }

    public static function slugNuevo(string $evento, string $canal): string
    {
        return Str::slug($evento . '-' . $canal) . '-' . Str::lower(Str::random(6));
    }

    /**
     * Reemplaza {variable} y limpia lo que quedó vacío: "( )" si no había
     * patente o servicio, y espacios dobles.
     */
    public static function completar(string $texto, array $variables): string
    {
        foreach ($variables as $clave => $valor) {
            $texto = str_replace('{' . $clave . '}', (string) $valor, $texto);
        }

        $texto = preg_replace('/\s*\(\s*\)/u', '', $texto);

        return trim(preg_replace('/[ \t]{2,}/', ' ', $texto));
    }
}
