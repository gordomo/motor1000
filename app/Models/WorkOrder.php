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

    protected $fillable = [
        'tenant_id',
        'number',
        'customer_id',
        'vehicle_id',
        'mechanic_id',
        'status',
        'priority',
        'complaint',
        'diagnosis',
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
    ];

    protected $casts = [
        'estimated_at'  => 'datetime',
        'started_at'    => 'datetime',
        'completed_at'  => 'datetime',
        'delivered_at'  => 'datetime',
        'checklist'     => 'array',
        'labor_cost'    => 'decimal:2',
        'parts_cost'    => 'decimal:2',
        'discount'      => 'decimal:2',
        'total'         => 'decimal:2',
        'status'        => WorkOrderStatus::class,
    ];

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
                ]);
            }
        });
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

    public function recalculateTotal(): void
    {
        $this->labor_cost = $this->items()->where('type', 'labor')->sum('total');
        $this->parts_cost = $this->items()->where('type', 'part')->sum('total');
        $this->total = $this->labor_cost + $this->parts_cost - $this->discount;
        $this->saveQuietly();
    }
}
