<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkOrderItem extends Model
{
    protected $fillable = [
        'work_order_id',
        'type',
        'description',
        'quantity',
        'unit_price',
        'total',
        'inventory_item_id',
    ];

    protected $casts = [
        'quantity'   => 'decimal:2',
        'unit_price' => 'decimal:2',
        'total'      => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saving(function (WorkOrderItem $item) {
            $item->total = $item->quantity * $item->unit_price;
        });

        static::saved(function (WorkOrderItem $item) {
            $item->workOrder->recalculateTotal();
        });

        static::deleted(function (WorkOrderItem $item) {
            $item->workOrder->recalculateTotal();
        });
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }
}
