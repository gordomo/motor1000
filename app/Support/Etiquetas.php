<?php

namespace App\Support;

use App\Enums\CommunicationChannel;
use App\Enums\CustomerStatus;
use App\Enums\ReminderType;

/**
 * Textos en español para los valores que se guardan en inglés en la base
 * ('pending', 'oil_change', 'urgent'...). Varias tablas, fichas y PDFs los
 * mostraban crudos. Solo traduce lo que se muestra: los datos no cambian.
 * Un valor desconocido se devuelve tal cual, para no romper ninguna pantalla.
 */
class Etiquetas
{
    public static function estadoCita(?string $valor): ?string
    {
        return self::de($valor, [
            'scheduled'   => __('Programada'),
            'confirmed'   => __('Confirmada'),
            'in_progress' => __('En progreso'),
            'completed'   => __('Completada'),
            'cancelled'   => __('Cancelada'),
            'no_show'     => __('No asistió'),
        ]);
    }

    public static function estadoRecordatorio(?string $valor): ?string
    {
        return self::de($valor, [
            'pending'   => __('Pendiente'),
            'sent'      => __('Enviado'),
            'dismissed' => __('Descartado'),
            'completed' => __('Completado'),
        ]);
    }

    public static function tipoRecordatorio(?string $valor): ?string
    {
        return ReminderType::tryFrom((string) $valor)?->getLabel() ?? $valor;
    }

    public static function estadoCliente(?string $valor): ?string
    {
        return CustomerStatus::tryFrom((string) $valor)?->getLabel() ?? $valor;
    }

    public static function canal(?string $valor): ?string
    {
        return CommunicationChannel::tryFrom((string) $valor)?->getLabel() ?? $valor;
    }

    public static function estadoComunicacion(?string $valor): ?string
    {
        return self::de($valor, [
            'pending' => __('Pendiente'),
            'sent'    => __('Enviado'),
            'failed'  => __('Fallido'),
        ]);
    }

    public static function estadoFactura(?string $valor): ?string
    {
        return self::de($valor, [
            'draft'    => __('Borrador'),
            'pending'  => __('Pendiente'),
            'paid'     => __('Pagada'),
            'overdue'  => __('Vencida'),
            'canceled' => __('Cancelada'),
        ]);
    }

    public static function prioridad(?string $valor): ?string
    {
        return self::de($valor, [
            'low'    => __('Baja'),
            'normal' => __('Normal'),
            'high'   => __('Alta'),
            'urgent' => __('Urgente'),
        ]);
    }

    /**
     * Combustibles de Argentina. El sistema venía de Brasil (Gasolina, Etanol,
     * Flex). La clave 'gasoline' se mantiene para no tocar los datos guardados:
     * ahora se muestra como Nafta.
     *
     * @return array<string, string>
     */
    public static function opcionesCombustible(): array
    {
        return [
            'gasoline'  => __('Nafta'),
            'diesel'    => __('Diésel'),
            'gnc'       => __('GNC'),
            'nafta_gnc' => __('Nafta/GNC'),
            'electric'  => __('Eléctrico'),
            'hybrid'    => __('Híbrido'),
        ];
    }

    public static function combustible(?string $valor): ?string
    {
        // Etanol y Flex quedaron de cuando el sistema era para Brasil ('flex'
        // era el valor por defecto): no se sabe qué combustible es en realidad.
        return self::de($valor, self::opcionesCombustible() + [
            'flex'    => __('Sin definir'),
            'ethanol' => __('Sin definir'),
        ]);
    }

    public static function transmision(?string $valor): ?string
    {
        return self::de($valor, [
            'manual'    => __('Manual'),
            'automatic' => __('Automática'),
            'cvt'       => __('CVT'),
        ]);
    }

    public static function tipoItem(?string $valor): ?string
    {
        return self::de($valor, [
            'labor' => __('Mano de obra'),
            'part'  => __('Pieza'),
            'other' => __('Otro'),
        ]);
    }

    /** Formas de pago de facturas y órdenes (las dos listas usan claves distintas). */
    public static function formaDePago(?string $valor): ?string
    {
        return self::de($valor, [
            'cash'          => __('Efectivo'),
            'credit_card'   => __('Tarjeta de Crédito'),
            'debit_card'    => __('Tarjeta de Débito'),
            'bank_transfer' => __('Transferencia bancaria'),
            'mercado_pago'  => __('Mercado Pago'),
            'check'         => __('Cheque'),
            // Medios de Brasil que ya no se ofrecen; se siguen mostrando en datos viejos.
            'pix'           => __('PIX'),
            'bank_slip'     => __('Boleto'),
        ]);
    }

    private static function de(?string $valor, array $textos): ?string
    {
        return $textos[$valor] ?? $valor;
    }
}
