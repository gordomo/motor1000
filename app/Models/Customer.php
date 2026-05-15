<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'phone',
        'whatsapp',
        'document',
        'document_type',
        'birthday',
        'address',
        'city',
        'state',
        'zip',
        'status',
        'notes',
        'tags',
        'last_visit_at',
        'whatsapp_opted_in',
        'email_opted_in',
    ];

    protected $casts = [
        'birthday'         => 'date',
        'last_visit_at'    => 'datetime',
        'tags'             => 'array',
        'whatsapp_opted_in' => 'boolean',
        'email_opted_in'   => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class);
    }

    public function communications(): HasMany
    {
        return $this->hasMany(Communication::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function getFullContactAttribute(): string
    {
        return "{$this->name} ({$this->phone})";
    }

    public function isBirthdayToday(): bool
    {
        return $this->birthday && $this->birthday->format('m-d') === now()->format('m-d');
    }

    public function isInactive(int $months = 6): bool
    {
        if (! $this->last_visit_at) {
            return true;
        }

        return $this->last_visit_at->lt(now()->subMonths($months));
    }
}
