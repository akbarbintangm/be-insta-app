<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Auth\AuthController;

Route::controller(AuthController::class)->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('/register', 'register');
        Route::post('/login', 'login');
        Route::middleware('auth:api')->group(function () {
            Route::post('/logout', 'logout');
            Route::post('/refresh', 'refresh');
        });
        // Route::post('/logout', 'logout');
        // Route::post('/refresh', 'refresh');
    });
});
