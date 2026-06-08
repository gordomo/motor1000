<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'license_plate',
        'brand',
        'model',
        'year',
        'color',
        'vin',
        'mileage',
        'fuel_type',
        'transmission',
        'engine',
        'notes',
        'last_service_at',
        'public_token',
    ];

    protected $casts = [
        'last_service_at' => 'datetime',
        'mileage'         => 'integer',
        'year'            => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (Vehicle $vehicle) {
            if (! $vehicle->public_token) {
                $vehicle->public_token = (string) \Illuminate\Support\Str::uuid();
            }
            if ($vehicle->license_plate) {
                $vehicle->license_plate = strtoupper($vehicle->license_plate);
            }
        });

        static::updating(function (Vehicle $vehicle) {
            if ($vehicle->isDirty('license_plate') && $vehicle->license_plate) {
                $vehicle->license_plate = strtoupper($vehicle->license_plate);
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class);
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    public function getDisplayNameAttribute(): string
    {
        return "{$this->year} {$this->brand} {$this->model} - {$this->license_plate}";
    }
}
