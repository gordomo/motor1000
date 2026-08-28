<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable, SoftDeletes, HasRoles, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'password',
        'phone',
        'avatar',
        'is_active',
        'is_super_admin',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password'          => 'hashed',
        'is_active'         => 'boolean',
        'is_super_admin'    => 'boolean',
    ];

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'admin') {
            return $this->is_super_admin;
        }

        return $this->is_active && $this->tenant_id !== null && ! $this->is_super_admin;
    }

    /**
     * Trabaja en el taller y nada más: no administra ni atiende el mostrador.
     * Define qué ve en el panel (punto 7 del pedido del cliente).
     */
    public function isOnlyMechanic(): bool
    {
        return $this->hasRole('mechanic') && ! $this->hasAnyRole(['admin', 'receptionist']);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
