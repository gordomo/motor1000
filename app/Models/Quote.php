<?php

namespace App\Models;

use App\Enums\QuoteStatus;
use App\Enums\QuoteType;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Quote extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'code',
        'customer_id',
        'vehicle_id',
        'mileage',
        'detected_fault',
        'status',
        'type',
        'checklist',
        'items',
        'subtotal',
        'tax',
        'discount',
        'total',
        'notes',
        'sent_at',
        'accepted_at',
        'rejected_at',
    ];

    protected $casts = [
        'status'      => QuoteStatus::class,
        'type'        => QuoteType::class,
        'mileage'     => 'integer',
        'checklist'   => 'array',
        'items'       => 'array',
        'subtotal'    => 'decimal:2',
        'tax'         => 'decimal:2',
        'discount'    => 'decimal:2',
        'total'       => 'decimal:2',
        'sent_at'     => 'datetime',
        'accepted_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    /**
     * Checklist en blanco según los puntos que tenga configurados el taller
     * (pedido 2). Antes eran 20 puntos fijos escritos acá; ahora el catálogo vive
     * en checklist_items y se administra desde el panel. Lo que se guarda en
     * quotes.checklist sigue siendo un snapshot: los presupuestos ya emitidos no
     * cambian si el taller edita sus puntos.
     */
    public static function defaultChecklist(): array
    {
        return ChecklistItem::blankChecklist();
    }

    // ─── Boot ─────────────────────────────────────────────────────────────────
    protected static function booted(): void
    {
        // Calcula los totales del lado del servidor (los campos del form son
        // disabled y no se persisten). Garantiza item.total, subtotal y total.
        static::saving(function (Quote $quote) {
            $items = collect($quote->items ?? [])->map(function ($i) {
                $cant = (float) ($i['cantidad'] ?? 0);
                $pu   = (float) ($i['precio_unitario'] ?? 0);
                $i['total'] = round($cant * $pu, 2);
                return $i;
            });

            $quote->items = $items->all();
            $subtotal = (float) $items->sum(fn ($i) => $i['total']);
            $quote->subtotal = $subtotal;
            $quote->total = max(0, $subtotal + (float) $quote->tax - (float) $quote->discount);
        });

        static::creating(function (Quote $quote) {
            if (! $quote->code) {
                $quote->code = static::generateCode($quote->tenant_id);
            }
            // Solo los presupuestos con revisión arrancan con el checklist cargado
            // (pedido 2). Sin este guard, los "sin revisión" guardaban 20 puntos
            // vacíos que después aparecían en el PDF.
            $type = $quote->type ?? QuoteType::ConChecklist;

            if (empty($quote->checklist) && $type->requiresChecklist()) {
                $quote->checklist = static::defaultChecklist();
            }
        });

        static::updating(function (Quote $quote) {
            if ($quote->isDirty('status')) {
                $now = now();
                if ($quote->status === QuoteStatus::Accepted && ! $quote->accepted_at) {
                    $quote->accepted_at = $now;
                }
                if ($quote->status === QuoteStatus::Rejected && ! $quote->rejected_at) {
                    $quote->rejected_at = $now;
                }
            }
        });

        // Pedido 11: el KM del presupuesto mantiene actualizado el del vehículo.
        // Solo hacia arriba, igual que en la orden de trabajo.
        static::saved(function (Quote $quote) {
            $km = (int) $quote->mileage;

            if ($km > 0 && ($vehicle = $quote->vehicle) && $km > (int) $vehicle->mileage) {
                $vehicle->updateQuietly(['mileage' => $km]);
            }
        });
    }

    public static function generateCode(int $tenantId): string
    {
        $last = static::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->orderByDesc('id')
            ->value('code');

        $next = $last ? ((int) substr($last, 5)) + 1 : 1;

        return 'PRES-' . str_pad($next, 5, '0', STR_PAD_LEFT);
    }

    // ─── Relaciones ───────────────────────────────────────────────────────────
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function workOrder(): HasOne
    {
        return $this->hasOne(WorkOrder::class);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────
    public function recalculateTotal(): void
    {
        $items = collect($this->items ?? []);
        $subtotal = $items->sum(fn($i) => ($i['cantidad'] ?? 0) * ($i['precio_unitario'] ?? 0));
        $this->subtotal = $subtotal;
        $this->total    = max(0, $subtotal + $this->tax - $this->discount);
        $this->saveQuietly();
    }

    public function isAccepted(): bool
    {
        return $this->status === QuoteStatus::Accepted;
    }

    public function hasWorkOrder(): bool
    {
        return $this->workOrder()->exists();
    }
}
