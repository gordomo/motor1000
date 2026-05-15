<?php

use Illuminate\Support\Facades\Route;

Route::middleware('api')->prefix('api')->group(function () {
    Route::get('/health', fn() => response()->json(['status' => 'ok']));
});
