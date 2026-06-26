<?php

namespace App\Actions\Appointment;

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Tenant;
use App\Services\Booking\SlotAvailability;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Crea una cita desde el turnero público (landing). Resuelve la sucursal,
 * valida el horario (no pasado, slot libre), busca o crea el cliente por
 * WhatsApp y crea la Appointment en estado "scheduled".
 */
class CreatePublicAppointmentAction
{
    public function __construct(private SlotAvailability $availability)
    {
    }

    /**
     * @param  array<string,mixed>  $data
     * @param  Collection<int,Tenant>  $branches  Sucursales bookeables de la marca.
     */
    public function execute(array $data, Collection $branches): Appointment
    {
        $branch = $this->resolveBranch($data['branch_id'] ?? null, $branches);

        $tz = $branch->timezone ?: config('app.timezone');

        try {
            $scheduledAt = Carbon::createFromFormat('Y-m-d H:i', $data['fecha'] . ' ' . $data['hora'], $tz);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages(['fecha' => 'Fecha u hora inválida.']);
        }

        // Tenant = sucursal: lo fijamos para que el scope multi-tenant aplique.
        app()->instance('current.tenant', $branch);

        // Disponibilidad real según la config del taller: el horario debe estar
        // dentro de la grilla (horarios + duración de franja), dentro de la
        // ventana de anticipación y con capacidad libre (turnos < slot_capacity).
        if (! $this->availability->canBook($branch, $scheduledAt)) {
            throw ValidationException::withMessages(['hora' => 'Ese horario no está disponible. Elegí otro.']);
        }

        $customer = $this->resolveCustomer($branch, $data);

        $servicios = collect($data['servicios'])->map(fn ($s) => trim((string) $s))->filter()->values();
        $title = $servicios->implode(', ');

        $descParts = [];
        if (! empty($data['vehiculo'])) {
            $descParts[] = 'Auto: ' . $data['vehiculo'];
        }
        if (! empty($data['comentario'])) {
            $descParts[] = 'Comentario: ' . $data['comentario'];
        }
        $descParts[] = 'Turno solicitado desde la web.';

        return Appointment::create([
            'tenant_id'        => $branch->id,
            'customer_id'      => $customer->id,
            'title'            => $title !== '' ? $title : 'Turno web',
            'description'      => implode("\n", $descParts),
            'status'           => 'scheduled',
            'source'           => 'web_turnero',
            'scheduled_at'     => $scheduledAt,
            'ends_at'          => $scheduledAt->copy()->addMinutes(30),
            'duration_minutes' => 30,
        ]);
    }

    /**
     * @param  Collection<int,Tenant>  $branches
     */
    private function resolveBranch(mixed $branchId, Collection $branches): Tenant
    {
        if ($branches->isEmpty()) {
            throw ValidationException::withMessages(['branch_id' => 'No hay sucursales disponibles para reservar.']);
        }

        if ($branchId === null || $branchId === '') {
            if ($branches->count() === 1) {
                return $branches->first();
            }
            throw ValidationException::withMessages(['branch_id' => 'Elegí una sucursal.']);
        }

        $branch = $branches->firstWhere('id', (int) $branchId);

        if (! $branch) {
            throw ValidationException::withMessages(['branch_id' => 'Sucursal inválida.']);
        }

        return $branch;
    }

    /**
     * @param  array<string,mixed>  $data
     */
    private function resolveCustomer(Tenant $branch, array $data): Customer
    {
        $digits = preg_replace('/\D/', '', (string) $data['whatsapp']);

        if ($digits !== '') {
            $customer = Customer::query()
                ->where('tenant_id', $branch->id)
                ->where(function ($q) use ($digits) {
                    $q->whereRaw("REPLACE(REPLACE(REPLACE(COALESCE(whatsapp,''),' ',''),'-',''),'+','') LIKE ?", ["%{$digits}%"])
                        ->orWhereRaw("REPLACE(REPLACE(REPLACE(COALESCE(phone,''),' ',''),'-',''),'+','') LIKE ?", ["%{$digits}%"]);
                })
                ->first();

            if ($customer) {
                return $customer;
            }
        }

        return Customer::create([
            'tenant_id'         => $branch->id,
            'name'              => $data['nombre'],
            'whatsapp'          => $data['whatsapp'],
            'phone'             => $data['whatsapp'],
            'status'            => 'active',
            'whatsapp_opted_in' => true,
        ]);
    }
}
