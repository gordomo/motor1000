<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

/**
 * Quién puede tocar los cobros.
 *
 * Registrar es trabajo del mostrador: el comercial cobra al entregar el auto.
 * Pero corregir o borrar un cobro es mover plata que ya está contada en los
 * números del taller, así que queda solo en manos del administrador.
 *
 * Se aplica por convención de Laravel, sin registro explícito, igual que las
 * policies de clientes y órdenes.
 */
class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'receptionist']);
    }

    public function view(User $user, Payment $payment): bool
    {
        return $user->tenant_id === $payment->tenant_id
            && $user->hasAnyRole(['admin', 'receptionist']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'receptionist']);
    }

    /** Corregir un cobro ya registrado: solo el administrador. */
    public function update(User $user, Payment $payment): bool
    {
        return $user->tenant_id === $payment->tenant_id
            && $user->hasRole('admin');
    }

    /** Borrar un cobro saca plata de los números: solo el administrador. */
    public function delete(User $user, Payment $payment): bool
    {
        return $user->tenant_id === $payment->tenant_id
            && $user->hasRole('admin');
    }
}
