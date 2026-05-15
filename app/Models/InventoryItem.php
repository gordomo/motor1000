<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryItem extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'category',
        'brand',
        'unit',
        'cost_price',
        'sale_price',
        'stock_quantity',
        'min_stock',
        'location',
        'supplier',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'cost_price'     => 'decimal:2',
        'sale_price'     => 'decimal:2',
        'stock_quantity' => 'decimal:2',
        'min_stock'      => 'decimal:2',
        'is_active'      => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function isLowStock(): bool
    {
        return $this->stock_quantity <= $this->min_stock;
    }
}
