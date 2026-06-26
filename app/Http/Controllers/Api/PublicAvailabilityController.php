<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Booking\SlotAvailability;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class PublicAvailabilityController extends Controller
{
    /**
     * Slots disponibles de una sucursal para una fecha (?branch_id&date=Y-m-d).
     * La landing lo consulta al elegir un día.
     */
    public function index(Request $request, SlotAvailability $availability): JsonResponse
    {
        /** @var Collection $branches */
        $branches = $request->attributes->get('brand_branches', collect());

        $branchId = $request->query('branch_id');
        $branch = $branchId
            ? $branches->firstWhere('id', (int) $branchId)
            : ($branches->count() === 1 ? $branches->first() : null);

        if (! $branch) {
            return response()->json(['ok' => false, 'message' => 'Sucursal inválida.'], 422);
        }

        $dateStr = (string) $request->query('date');
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) {
            return response()->json(['ok' => false, 'message' => 'Fecha inválida (Y-m-d).'], 422);
        }

        $slots = $availability->forDate($branch, Carbon::parse($dateStr));

        return response()->json([
            'ok'    => true,
            'date'  => $dateStr,
            'slots' => $slots,
        ]);
    }
}
