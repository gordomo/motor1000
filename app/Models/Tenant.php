<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'brand_slug',
        'accepts_online_booking',
        'booking_settings',
        'public_api_key',
        'email',
        'phone',
        'whatsapp',
        'logo',
        'logo_path',
        'primary_color',
        'secondary_color',
        'address',
        'city',
        'state',
        'zip',
        'country',
        'timezone',
        'currency',
        'settings',
        'is_active',
        'trial_ends_at',
        'subscribed_at',
    ];

    protected $casts = [
        'settings'                => 'array',
        'booking_settings'        => 'array',
        'is_active'               => 'boolean',
        'accepts_online_booking'  => 'boolean',
        'trial_ends_at'  => 'datetime',
        'subscribed_at'  => 'datetime',
    ];

    /** Config de reservas por defecto (se mergea con booking_settings del taller). */
    public const BOOKING_DEFAULTS = [
        'slot_capacity'     => 1,   // turnos simultáneos por franja
        'slot_minutes'      => 30,  // duración de la franja
        'min_advance_hours' => 0,   // anticipación mínima (0 = mismo día)
        'max_advance_days'  => 60,  // hasta cuántos días a futuro se puede reservar
        'hours'             => [
            'mon' => ['open' => true,  'from' => '08:00', 'to' => '17:00'],
            'tue' => ['open' => true,  'from' => '08:00', 'to' => '17:00'],
            'wed' => ['open' => true,  'from' => '08:00', 'to' => '17:00'],
            'thu' => ['open' => true,  'from' => '08:00', 'to' => '17:00'],
            'fri' => ['open' => true,  'from' => '08:00', 'to' => '17:00'],
            'sat' => ['open' => true,  'from' => '08:00', 'to' => '13:00'],
            'sun' => ['open' => false, 'from' => null,    'to' => null],
        ],
    ];

    /** Config de reservas efectiva (defaults + lo guardado en el taller). */
    public function bookingConfig(): array
    {
        $cfg = $this->booking_settings ?? [];
        $merged = array_merge(self::BOOKING_DEFAULTS, $cfg);
        $merged['hours'] = array_merge(self::BOOKING_DEFAULTS['hours'], $cfg['hours'] ?? []);

        return $merged;
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    public function mechanics(): HasMany
    {
        return $this->hasMany(Mechanic::class);
    }

    public function getSetting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }
}
