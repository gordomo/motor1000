<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Ingreso de dinero de una orden de trabajo: el adelanto que deja el cliente al
 * aprobar el presupuesto, o el cobro al entregar el vehículo.
 */
class Payment extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    public const METHODS = [
        'efectivo'      => 'Efectivo',
        'transferencia' => 'Transferencia',
        'debito'        => 'Tarjeta de débito',
        'credito'       => 'Tarjeta de crédito',
        'mercadopago'   => 'Mercado Pago',
        'cheque'        => 'Cheque',
        'otro'          => 'Otro',
    ];

    public const TYPES = [
        'adelanto' => 'Adelanto',
        'saldo'    => 'Saldo',
        'ajuste'   => 'Ajuste',
    ];

    protected $fillable = [
        'tenant_id',
        'work_order_id',
        'type',
        'amount',
        'method',
        'installments',
        'surcharge',
        'paid_at',
        'user_id',
        'notes',
    ];

    protected $casts = [
        'amount'  => 'decimal:2',
        'surcharge' => 'decimal:2',
        'installments' => 'integer',
        'paid_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Payment $payment) {
            $payment->paid_at ??= now();
            $payment->user_id ??= auth()->id();
        });

        // El estado de pago de la orden se deriva de los cobros, no se carga a mano.
        static::saved(fn (Payment $payment) => $payment->workOrder?->syncPaymentStatus());
        static::deleted(fn (Payment $payment) => $payment->workOrder?->syncPaymentStatus());
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function methodLabel(): string
    {
        $nombre = __(self::METHODS[$this->method] ?? $this->method);

        return $this->installments > 1 ? $nombre . ' (' . $this->installments . ' ' . __('cuotas') . ')' : $nombre;
    }

    /** Lo que se descuenta del saldo de la orden: lo pagado menos el recargo. */
    public function montoAplicado(): float
    {
        return round((float) $this->amount - (float) $this->surcharge, 2);
    }

    public function typeLabel(): string
    {
        return __(self::TYPES[$this->type] ?? $this->type);
    }
}
