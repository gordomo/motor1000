<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Support\CurrentTenant;
use Illuminate\Database\Eloquent\Builder;
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
        'tax_id',
        'tax_id_type',
        'birthday',
        'birthday_contacted_at',
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
        'birthday_contacted_at' => 'datetime',
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

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(Inspection::class);
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

    public static function inactivityMonths(?Tenant $tenant = null): int
    {
        return max(1, (int) (($tenant ?? CurrentTenant::get())?->getSetting('inactive_customer_months', 12) ?? 12));
    }

    public function isInactive(?int $months = null): bool
    {
        $tenant = CurrentTenant::id() === $this->tenant_id ? CurrentTenant::get() : $this->tenant;

        return $this->last_visit_at !== null
            && $this->last_visit_at->lte(now()->subMonthsNoOverflow($months ?? self::inactivityMonths($tenant)));
    }

    public function relationshipStatus(): string
    {
        if ($this->last_visit_at === null) {
            return 'prospect';
        }

        if ($this->isInactive()) {
            return 'inactive';
        }

        return $this->status === 'vip' ? 'vip' : 'active';
    }

    public function scopeWithRelationshipStatus(Builder $query, string $status, ?int $months = null): Builder
    {
        $cutoff = now()->subMonthsNoOverflow($months ?? self::inactivityMonths());

        return match ($status) {
            'prospect' => $query->whereNull('last_visit_at'),
            'inactive' => $query->whereNotNull('last_visit_at')->where('last_visit_at', '<=', $cutoff),
            'vip' => $query->where('last_visit_at', '>', $cutoff)->where('status', 'vip'),
            'active' => $query->where('last_visit_at', '>', $cutoff)->where('status', '!=', 'vip'),
            default => $query->whereRaw('1 = 0'),
        };
    }

    public function scopeInactive(Builder $query, ?int $months = null): Builder
    {
        return $query->withRelationshipStatus('inactive', $months);
    }
}
