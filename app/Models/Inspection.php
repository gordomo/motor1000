<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Revisión de vehículo (pedido 17): el checklist del taller sin nada de precios.
 * Es el protocolo de revisión estandarizado, imprimible y guardado en el historial.
 */
class Inspection extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'code',
        'customer_id',
        'vehicle_id',
        'mileage',
        'checklist',
        'notes',
    ];

    protected $casts = [
        'checklist' => 'array',
        'mileage'   => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (Inspection $inspection) {
            if (! $inspection->code) {
                $inspection->code = static::generateCode($inspection->tenant_id);
            }

            if (empty($inspection->checklist)) {
                $inspection->checklist = ChecklistItem::blankChecklist();
            }
        });

        // Mismo criterio que la OT y el presupuesto: el KM del vehículo solo sube.
        static::saved(function (Inspection $inspection) {
            $km = (int) $inspection->mileage;

            if ($km > 0 && ($vehicle = $inspection->vehicle) && $km > (int) $vehicle->mileage) {
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

        $next = $last ? ((int) substr($last, 4)) + 1 : 1;

        return 'REV-' . str_pad($next, 5, '0', STR_PAD_LEFT);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /** Puntos efectivamente revisados (con estado cargado). */
    public function checkedItems(): array
    {
        return collect($this->checklist ?? [])
            ->filter(fn ($p): bool => ! empty($p['estado']))
            ->values()
            ->all();
    }
}
