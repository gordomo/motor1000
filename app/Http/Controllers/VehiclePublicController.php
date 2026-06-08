<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use Illuminate\Http\Response;
use Illuminate\View\View;

class VehiclePublicController extends Controller
{
    public function __invoke(string $token): View|Response
    {
        $vehicle = Vehicle::where('public_token', $token)
            ->with([
                'customer',
                'tenant',
                'workOrders' => fn ($q) => $q->orderByDesc('created_at')->limit(10),
                'quotes'     => fn ($q) => $q->orderByDesc('created_at')->limit(10),
                'reminders'  => fn ($q) => $q->where('status', 'pending')->orderBy('due_at')->limit(5),
            ])
            ->firstOrFail();

        return view('public.vehicle', compact('vehicle'));
    }
}
