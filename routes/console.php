<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Http\Request;
use App\Models\User;
use App\Http\Controllers\Admin\EventLogController;

Schedule::daily()
    ->onOneServer()
    ->group(fn () => [
        Schedule::command('sitemap:generate'),
    ]);

Artisan::command('debug:event-logs', function () {
    $user = User::where('role', 'admin')->first();
    if (! $user) {
        $this->error('No admin user found.');
        return;
    }

    $request = Request::create('/admin/events/logs', 'GET', ['date' => '2025-10-05']);
    $request->setUserResolver(fn () => $user);

    try {
        $response = app(EventLogController::class)->index($request);
        $this->info($response->getContent());
    } catch (Throwable $e) {
        dump(get_class($e), $e->getMessage());
    }
});
