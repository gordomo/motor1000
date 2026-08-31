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

    /**
     * Corregir un cobro: el administrador cualquiera, y cada uno el que registró
     * él mismo. Equivocarse al cargar es normal y no tiene sentido que haya que
     * pedirle al dueño que arregle un "efectivo" que era transferencia.
     *
     * Los cobros sin autor registrado (anteriores a esto) solo los corrige el
     * administrador, porque no hay forma de saber quién los cargó.
     */
    public function update(User $user, Payment $payment): bool
    {
        if ($user->tenant_id !== $payment->tenant_id) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        return $user->hasRole('receptionist')
            && $payment->user_id !== null
            && $payment->user_id === $user->id;
    }

    /**
     * Borrar un cobro saca plata de los números y no deja rastro de por qué, así
     * que queda solo en el administrador. Para arreglar un error de carga está
     * update(), que sí puede usar quien lo registró.
     */
    public function delete(User $user, Payment $payment): bool
    {
        return $user->tenant_id === $payment->tenant_id
            && $user->hasRole('admin');
    }
}
