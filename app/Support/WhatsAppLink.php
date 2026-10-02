<?php

namespace App\Support;

/**
 * Link wa.me para que el usuario abra el chat con el mensaje ya escrito.
 *
 * wa.me necesita el número internacional sin signos. Los números se cargan de
 * muchas formas ("223 555-1234", "+54 9 223...", "0223..."), así que se
 * normalizan a Argentina (549 + área + número). Si no se puede saber el número
 * completo (por ejemplo sin código de área, o con el "15"), devuelve null y la
 * pantalla muestra el teléfono para llamar, en vez de abrir un chat equivocado.
 */
class WhatsAppLink
{
    public static function para(?string $numero, string $mensaje): ?string
    {
        $internacional = self::normalizar($numero);

        return $internacional
            ? 'https://wa.me/' . $internacional . '?text=' . rawurlencode($mensaje)
            : null;
    }

    public static function normalizar(?string $numero): ?string
    {
        $d = preg_replace('/\D/', '', (string) $numero);

        if (str_starts_with($d, '00')) {
            $d = substr($d, 2);
        }

        if (str_starts_with($d, '54')) {
            $resto = ltrim(substr($d, 2), '0');

            // Celulares de Argentina por WhatsApp: 54 9 + área + número (10 dígitos).
            if (str_starts_with($resto, '9')) {
                $resto = substr($resto, 1);
            }

            return strlen($resto) === 10 ? '549' . $resto : null;
        }

        // Número local: área + número son 10 dígitos (sin el 0 ni el 15).
        $d = ltrim($d, '0');

        return strlen($d) === 10 ? '549' . $d : null;
    }
}
