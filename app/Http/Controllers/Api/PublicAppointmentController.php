<?php

namespace App\Http\Controllers\Api;

use App\Actions\Appointment\CreatePublicAppointmentAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePublicAppointmentRequest;
use Illuminate\Http\JsonResponse;

class PublicAppointmentController extends Controller
{
    public function store(
        StorePublicAppointmentRequest $request,
        CreatePublicAppointmentAction $action
    ): JsonResponse {
        $branches = $request->attributes->get('brand_branches', collect());

        $appointment = $action->execute($request->validated(), $branches);

        return response()->json([
            'ok'           => true,
            'id'           => $appointment->id,
            'scheduled_at' => $appointment->scheduled_at->toIso8601String(),
            'message'      => 'Turno solicitado. El taller lo confirmará a la brevedad.',
        ], 201);
    }
}
