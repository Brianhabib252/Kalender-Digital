<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\EventController;

// Note: routes/api.php is already registered with '/api' prefix by RouteServiceProvider.
// So we register endpoints here without an extra '/api' prefix.
Route::get('/events', [EventController::class, 'index']);
Route::post('/events', [EventController::class, 'store']);
Route::put('/events/{event}', [EventController::class, 'update']);
Route::delete('/events/{event}', [EventController::class, 'destroy']);
