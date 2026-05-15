<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'inventory_item_id',
        'type',
        'quantity',
        'unit_cost',
        'reason',
        'work_order_id',
        'user_id',
    ];

    protected $casts = [
        'quantity'  => 'decimal:2',
        'unit_cost' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::created(function (InventoryMovement $movement) {
            $item = $movement->inventoryItem;
            if ($movement->type === 'in') {
                $item->increment('stock_quantity', $movement->quantity);
            } elseif ($movement->type === 'out') {
                $item->decrement('stock_quantity', $movement->quantity);
            } else {
                $item->update(['stock_quantity' => $movement->quantity]);
            }
        });
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
