<?php

use App\Http\Controllers\WorkOrderPdfController;
use App\Http\Controllers\InvoicePdfController;
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
