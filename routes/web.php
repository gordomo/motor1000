<?php

use App\Http\Controllers\ConfirmAppointmentController;
use App\Http\Controllers\WorkOrderPdfController;
use App\Http\Controllers\InvoicePdfController;
use App\Http\Controllers\QuotePdfController;
use App\Http\Controllers\VehiclePublicController;
use App\Http\Controllers\VehicleQrCardController;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

Route::get('/', function () {
    return redirect()->route('filament.app.auth.login');
});

Route::get('/health', function () {
    return response()->json(['status' => 'ok', 'timestamp' => now()->toIso8601String()]);
});

// Fallback for environments where Livewire login submit degrades to plain POST.
Route::post('/painel/login', function (Request $request) {
    $email = $request->input('data.email')
        ?? $request->input('data_email')
        ?? $request->input('email');

    $password = $request->input('data.password')
        ?? $request->input('data_password')
        ?? $request->input('password');

    $remember = (bool) (
        $request->input('data.remember')
        ?? $request->input('data_remember')
        ?? $request->boolean('remember')
    );

    if (! is_string($email) || ! is_string($password) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw ValidationException::withMessages([
            'email' => __('auth.failed'),
        ]);
    }

    if (! Auth::attempt(['email' => $email, 'password' => $password], $remember)) {
        throw ValidationException::withMessages([
            'email' => __('auth.failed'),
        ]);
    }

    $request->session()->regenerate();

    return redirect()->intended(Filament::getPanel('app')->getUrl());
});

Route::get('/work-orders/{workOrder}/pdf', WorkOrderPdfController::class)
    ->name('work-orders.pdf');

Route::get('/invoices/{invoice}/pdf', InvoicePdfController::class)
    ->name('invoices.pdf');

// Presupuesto PDF (requiere login)
Route::get('/quotes/{quote}/pdf', [QuotePdfController::class, '__invoke'])
    ->name('quotes.pdf');
Route::get('/quotes/{quote}/pdf/stream', [QuotePdfController::class, 'stream'])
    ->name('quotes.pdf.stream');

// Confirmación de turno por el cliente (link firmado del email, sin login)
Route::get('/turno/{appointment}/confirmar', ConfirmAppointmentController::class)
    ->name('public.appointments.confirm')
    ->middleware('signed');

// Página pública del vehículo (por QR, sin login)
Route::get('/v/{token}', [VehiclePublicController::class, '__invoke'])
    ->name('vehicle.public');

// Ficha QR imprimible (requiere login)
Route::get('/vehicles/{vehicle}/qr-card', VehicleQrCardController::class)
    ->name('vehicles.qr-card');
