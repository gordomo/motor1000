<?php

namespace App\Models;

use App\Enums\WorkOrderStatus;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkOrder extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    /**
     * Estado de cada punto del checklist de la orden.
     *
     * En el presupuesto los puntos describen CÓMO ESTÁ el auto (BIEN/REGULAR/MAL);
     * en la orden describen SI SE HIZO el trabajo. La orden hereda del presupuesto
     * solo los puntos que quedaron en REGULAR o MAL: los que estaban bien no
     * tienen nada que trabajar.
     */
    public const PUNTO_HECHO      = 'HECHO';
    public const PUNTO_NO_SE_PUDO = 'NO_SE_PUDO';

    public const PUNTO_ESTADOS = [
        self::PUNTO_HECHO      => 'Hecho',
        self::PUNTO_NO_SE_PUDO => 'No se pudo',
    ];

    protected $fillable = [
        'tenant_id',
        'number',
        'customer_id',
        'vehicle_id',
        'mechanic_id',
        'status',
        'blocked_reason',
        'blocked_at',
        'priority',
        'work_type',
        'complaint',
        'diagnosis',
        'work_performed',
        'internal_notes',
        'customer_notes',
        'mileage_in',
        'mileage_out',
        'labor_cost',
        'parts_cost',
        'discount',
        'total',
        'payment_status',
        'payment_method',
        'estimated_at',
        'started_at',
        'completed_at',
        'delivered_at',
        'checklist',
        'photos_in',
        'photos_out',
        'quote_id',
    ];

    protected $casts = [
        'blocked_at'    => 'datetime',
        'estimated_at'  => 'datetime',
        'started_at'    => 'datetime',
        'completed_at'  => 'datetime',
        'delivered_at'  => 'datetime',
        'checklist'     => 'array',
        'photos_in'     => 'array',
        'photos_out'    => 'array',
        'labor_cost'    => 'decimal:2',
        'parts_cost'    => 'decimal:2',
        'discount'      => 'decimal:2',
        'total'         => 'decimal:2',
        'status'        => WorkOrderStatus::class,
    ];

    /**
     * Mecánico que está ejecutando el cambio de estado, elegido en el totem del
     * taller. No es una columna: es contexto de la operación, y queda guardado en
     * el historial. En el totem la sesión es compartida, así que user_id no
     * alcanza para saber quién trabajó el auto.
     */
    public ?int $actingMechanicId = null;

    protected static function booted(): void
    {
        static::creating(function (WorkOrder $order) {
            if (! $order->number) {
                $order->number = static::generateNumber($order->tenant_id);
            }
        });

        static::updating(function (WorkOrder $order) {
            if ($order->isDirty('status')) {
                WorkOrderStatusHistory::create([
                    'work_order_id' => $order->id,
                    'from_status'   => $order->getOriginal('status'),
                    'to_status'     => $order->status,
                    'user_id'       => auth()->id(),
                    'mechanic_id'   => $order->actingMechanicId ?? $order->mechanic_id,
                ]);
            }
        });

        // Pedido 11: el KM de la orden mantiene actualizado el del vehículo.
        // Solo hacia arriba: un KM menor es un error de tipeo, no un dato nuevo.
        static::saved(function (WorkOrder $order) {
            $order->syncVehicleMileage();
        });
    }

    /**
     * Sube el kilometraje del vehículo al de la orden si este es mayor.
     * Nunca lo baja: el odómetro no vuelve atrás.
     */
    public function syncVehicleMileage(): void
    {
        $km = (int) ($this->mileage_out ?: $this->mileage_in);

        if ($km <= 0) {
            return;
        }

        $vehicle = $this->vehicle;

        if ($vehicle && $km > (int) $vehicle->mileage) {
            $vehicle->updateQuietly(['mileage' => $km]);
        }
    }

    public static function generateNumber(int $tenantId): string
    {
        $last = static::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->max('id') ?? 0;

        return 'WO-' . str_pad($last + 1, 5, '0', STR_PAD_LEFT);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function mechanic(): BelongsTo
    {
        return $this->belongsTo(Mechanic::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(WorkOrderItem::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(WorkOrderStatusHistory::class)->orderBy('created_at');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('paid_at');
    }

    // ─── Plata ────────────────────────────────────────────────────────────────

    /** Es gratis si no hay nada que cobrar. Deriva del monto, no de un flag. */
    public function isFree(): bool
    {
        return (float) $this->total <= 0;
    }

    public function totalPaid(): float
    {
        return (float) $this->payments()->sum('amount');
    }

    public function balance(): float
    {
        return round((float) $this->total - $this->totalPaid(), 2);
    }

    /**
     * payment_status deriva de los cobros registrados: nadie lo carga a mano.
     * Una orden gratis queda como pagada, no como pendiente eterna.
     */
    public function syncPaymentStatus(): void
    {
        $pagado = $this->totalPaid();

        $status = match (true) {
            $this->isFree()                 => 'paid',
            $pagado <= 0                    => 'pending',
            $pagado + 0.01 >= (float) $this->total => 'paid',
            default                         => 'partial',
        };

        if ($this->payment_status !== $status) {
            $this->forceFill(['payment_status' => $status])->saveQuietly();
        }
    }

    // ─── Checklist de trabajo ─────────────────────────────────────────────────

    /**
     * Arma el checklist de trabajo a partir del checklist del presupuesto,
     * tomando solo los puntos que necesitan intervención.
     *
     * @param  array<int, array<string, mixed>>|null  $quoteChecklist
     * @return list<array<string, mixed>>
     */
    public static function buildChecklistFromQuote(?array $quoteChecklist): array
    {
        return collect($quoteChecklist ?? [])
            ->filter(fn ($punto): bool => in_array($punto['estado'] ?? null, ['REGULAR', 'MAL'], true))
            ->values()
            ->map(fn (array $punto): array => [
                'id_punto'           => $punto['id_punto'] ?? null,
                'categoria'          => $punto['categoria'] ?? '',
                'nombre_item'        => $punto['nombre_item'] ?? '',
                // Cómo lo encontró el presupuesto, para que el mecánico sepa qué esperar.
                'estado_presupuesto' => $punto['estado'] ?? null,
                'observacion_previa' => $punto['aclaracion'] ?? '',
                // Lo que carga el mecánico.
                'estado'             => null,
                'aclaracion'         => '',
            ])
            ->all();
    }

    /**
     * Puntos a trabajar, siempre con la misma forma, venga el checklist del
     * formato nuevo o del viejo.
     *
     * Las órdenes anteriores a este flujo guardaron el checklist como
     * item / done / note, con 4 puntos fijos que traía el formulario
     * (Luces y señales, Nivel de fluidos, Frenos, Presión de neumáticos). Esas
     * órdenes existen y se siguen trabajando, así que se traducen en lugar de
     * ignorarse: la vista mostraba filas en blanco porque buscaba los campos
     * nuevos.
     *
     * @return list<array<string, mixed>>
     */
    public function workChecklist(): array
    {
        return collect($this->checklist ?? [])
            ->filter(fn ($punto): bool => is_array($punto))
            ->map(function (array $punto): array {
                $esViejo = ! array_key_exists('nombre_item', $punto) && array_key_exists('item', $punto);

                if (! $esViejo) {
                    return [
                        'nombre_item'        => $punto['nombre_item'] ?? '',
                        'categoria'          => $punto['categoria'] ?? '',
                        'estado_presupuesto' => $punto['estado_presupuesto'] ?? null,
                        'observacion_previa' => $punto['observacion_previa'] ?? '',
                        'estado'             => $punto['estado'] ?? null,
                        'aclaracion'         => $punto['aclaracion'] ?? '',
                        'id_punto'           => $punto['id_punto'] ?? null,
                    ];
                }

                return [
                    'nombre_item'        => $punto['item'] ?? '',
                    'categoria'          => '',
                    'estado_presupuesto' => null,
                    'observacion_previa' => $punto['note'] ?? '',
                    // Lo que antes era un tilde "revisado" ahora es "hecho".
                    'estado'             => ($punto['done'] ?? false) ? self::PUNTO_HECHO : null,
                    'aclaracion'         => '',
                    'id_punto'           => null,
                ];
            })
            // Un punto sin nombre no se puede trabajar ni mostrar.
            ->filter(fn (array $punto): bool => filled($punto['nombre_item']))
            ->values()
            ->all();
    }

    /** De dónde salieron los puntos, para poder explicarlo en pantalla. */
    public function checklistOrigen(): ?string
    {
        if (blank($this->checklist)) {
            return null;
        }

        if ($this->quote_id && $this->quote) {
            return __('Del presupuesto :code', ['code' => $this->quote->code]);
        }

        return __('Checklist cargado en la orden');
    }

    /** Puntos que el mecánico no pudo hacer. */
    public function issuePoints(): array
    {
        return collect($this->workChecklist())
            ->filter(fn (array $punto): bool => $punto['estado'] === self::PUNTO_NO_SE_PUDO)
            ->values()
            ->all();
    }

    /**
     * La orden tiene novedades si quedó algo sin hacer. El cliente pidió que se
     * note: se muestra en el tablero y en el listado.
     */
    public function hasIssues(): bool
    {
        return $this->issuePoints() !== [];
    }

    // ─── Bloqueo ──────────────────────────────────────────────────────────────

    public function isBlocked(): bool
    {
        return filled($this->blocked_reason);
    }

    public function block(string $reason): void
    {
        $this->forceFill(['blocked_reason' => $reason, 'blocked_at' => now()])->saveQuietly();
    }

    public function unblock(): void
    {
        $this->forceFill(['blocked_reason' => null, 'blocked_at' => null])->saveQuietly();
    }

    public function recalculateTotal(): void
    {
        $this->labor_cost = $this->items()->where('type', 'labor')->sum('total');
        $this->parts_cost = $this->items()->where('type', 'part')->sum('total');

        // Los ítems de tipo 'other' existen en el form pero quedaban fuera del
        // total: la orden se facturaba por menos de lo cargado. No tienen columna
        // propia, así que se suman al total sin desglosar.
        $otherCost = (float) $this->items()->where('type', 'other')->sum('total');

        $this->total = (float) $this->labor_cost + (float) $this->parts_cost + $otherCost - (float) $this->discount;
        $this->saveQuietly();
    }

    /** Total de los ítems que no son mano de obra ni repuestos. */
    public function otherCost(): float
    {
        return (float) $this->items()->where('type', 'other')->sum('total');
    }
}
