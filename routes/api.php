<?php

use App\Http\Controllers\Api\PublicAppointmentController;
use App\Http\Controllers\Api\PublicBranchController;
use Illuminate\Support\Facades\Route;

// Estas rutas se cargan con el prefijo /api (bootstrap/app.php → withRouting).

Route::get('/health', fn () => response()->json(['status' => 'ok']));

// API pública del turnero (landing → sistema). La API key identifica la marca;
// las sucursales bookeables se agrupan por brand_slug.
Route::middleware(['brand.apikey', 'throttle:public-booking'])
    ->prefix('public')
    ->group(function () {
        Route::get('/branches', [PublicBranchController::class, 'index']);
        Route::post('/appointments', [PublicAppointmentController::class, 'store']);
    });
