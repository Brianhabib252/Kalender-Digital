<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Admin\UserLogController;
use App\Http\Controllers\Admin\CalendarHolidayController;
use App\Http\Controllers\Admin\EventLogController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\User\OauthController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\User\LoginLinkController;

// Redirect root to the login page
Route::get('/', fn () => redirect()->route('login'))->name('home');

Route::prefix('auth')->group(
    function () {
        // OAuth
        Route::get('/redirect/{provider}', [OauthController::class, 'redirect'])->name('oauth.redirect');
        Route::get('/callback/{provider}', [OauthController::class, 'callback'])->name('oauth.callback');
        // Magic Link
        Route::middleware('throttle:login-link')->group(function () {
            Route::post('/login-link', [LoginLinkController::class, 'store'])->name('login-link.store');
            Route::get('/login-link/{token}', [LoginLinkController::class, 'login'])
                ->name('login-link.login')
                ->middleware('signed');
        });
    }
);

Route::middleware(['auth:sanctum', config('jetstream.auth_session')])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::delete('/auth/destroy/{provider}', [OauthController::class, 'destroy'])->name('oauth.destroy');

    Route::get('/chat', [ChatController::class, 'index'])->name('chat.index');

    Route::resource('/subscriptions', SubscriptionController::class)
        ->names('subscriptions')
        ->only(['index', 'create', 'store', 'show']);

    // Admin: User management (roles)
    Route::get('/admin/users', [UserManagementController::class, 'index'])->name('admin.users.index');
    Route::put('/admin/users/{user}', [UserManagementController::class, 'update'])->name('admin.users.update');
    Route::get('/admin/users/logs', [UserLogController::class, 'index'])->name('admin.users.logs');
    Route::get('/admin/events/logs', [EventLogController::class, 'index'])->name('admin.events.logs');
    Route::post('/admin/holidays', [CalendarHolidayController::class, 'store'])->name('admin.holidays.store');
    Route::delete('/admin/holidays/{holiday}', [CalendarHolidayController::class, 'destroy'])->name('admin.holidays.destroy');
});


// Add calendar routes
require __DIR__.'/calendar.php';
