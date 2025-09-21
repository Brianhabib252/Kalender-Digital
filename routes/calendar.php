<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CalendarController;

Route::middleware(['auth:sanctum', config('jetstream.auth_session')])->group(function (): void {
    Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar.index');
});


