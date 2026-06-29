<?php

namespace App\Models;

use App\Enums\QuoteStatus;
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
        'detected_fault',
        'status',
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

    // ─── Checklist default de 20 puntos ──────────────────────────────────────
    public static function defaultChecklist(): array
    {
        return [
            ['id_punto' => 1,  'categoria' => 'Frenos',        'nombre_item' => 'Pastillas de freno delanteras',   'estado' => null, 'aclaracion' => ''],
            ['id_punto' => 2,  'categoria' => 'Frenos',        'nombre_item' => 'Pastillas de freno traseras',      'estado' => null, 'aclaracion' => ''],
            ['id_punto' => 3,  'categoria' => 'Frenos',        'nombre_item' => 'Líquido de frenos',                'estado' => null, 'aclaracion' => ''],
            ['id_punto' => 4,  'categoria' => 'Suspensión',    'nombre_item' => 'Amortiguadores delanteros',        'estado' => null, 'aclaracion' => ''],
            ['id_punto' => 5,  'categoria' => 'Suspensión',    'nombre_item' => 'Amortiguadores traseros',          'estado' => null, 'aclaracion' => ''],
            ['id_punto' => 6,  'categoria' => 'Neumáticos',    'nombre_item' => 'Presión neumáticos',               'estado' => null, 'aclaracion' => ''],
            ['id_punto' => 7,  'categoria' => 'Neumáticos',    'nombre_item' => 'Desgaste de neumáticos',           'estado' => null, 'aclaracion' => ''],
            ['id_punto' => 8,  'categoria' => 'Fluidos',       'nombre_item' => 'Nivel de aceite de motor',         'estado' => null, 'aclaracion' => ''],
            ['id_punto' => 9,  'categoria' => 'Fluidos',       'nombre_item' => 'Líquido refrigerante',             'estado' => null, 'aclaracion' => ''],
            ['id_punto' => 10, 'categoria' => 'Fluidos',       'nombre_item' => 'Líquido de dirección',             'estado' => null, 'aclaracion' => ''],
            ['id_punto' => 11, 'categoria' => 'Fluidos',       'nombre_item' => 'Líquido de transmisión',           'estado' => null, 'aclaracion' => ''],
            ['id_punto' => 12, 'categoria' => 'Luces',         'nombre_item' => 'Luces delanteras (bajas/altas)',   'estado' => null, 'aclaracion' => ''],
            ['id_punto' => 13, 'categoria' => 'Luces',         'nombre_item' => 'Luces traseras y stop',            'estado' => null, 'aclaracion' => ''],
            ['id_punto' => 14, 'categoria' => 'Luces',         'nombre_item' => 'Luces de giro (intermitentes)',    'estado' => null, 'aclaracion' => ''],
            ['id_punto' => 15, 'categoria' => 'Motor',         'nombre_item' => 'Correa de distribución',           'estado' => null, 'aclaracion' => ''],
            ['id_punto' => 16, 'categoria' => 'Motor',         'nombre_item' => 'Filtro de aire',                   'estado' => null, 'aclaracion' => ''],
            ['id_punto' => 17, 'categoria' => 'Carrocería',    'nombre_item' => 'Limpiaparabrisas',                 'estado' => null, 'aclaracion' => ''],
            ['id_punto' => 18, 'categoria' => 'Carrocería',    'nombre_item' => 'Estado general de carrocería',     'estado' => null, 'aclaracion' => ''],
            ['id_punto' => 19, 'categoria' => 'Seguridad',     'nombre_item' => 'Cinturones de seguridad',          'estado' => null, 'aclaracion' => ''],
            ['id_punto' => 20, 'categoria' => 'Seguridad',     'nombre_item' => 'Bocina',                           'estado' => null, 'aclaracion' => ''],
        ];
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
            if (empty($quote->checklist)) {
                $quote->checklist = static::defaultChecklist();
            }
        });

        static::updating(function (Quote $quote) {
            if ($quote->isDirty('status')) {
                $now = now();
                if ($quote->status === QuoteStatus::Sent && ! $quote->sent_at) {
                    $quote->sent_at = $now;
                }
                if ($quote->status === QuoteStatus::Accepted && ! $quote->accepted_at) {
                    $quote->accepted_at = $now;
                }
                if ($quote->status === QuoteStatus::Rejected && ! $quote->rejected_at) {
                    $quote->rejected_at = $now;
                }
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
