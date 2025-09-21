<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EventChangeLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

final class EventLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        \abort_unless($request->user() instanceof User && $request->user()->isAdmin(), 403);

        $date = $request->string('date')->toString();
        $start = $date ? Carbon::parse($date)->startOfDay() : now()->startOfDay();
        $end = (clone $start)->endOfDay();

        $logs = EventChangeLog::query()
            ->whereBetween('created_at', [$start, $end])
            ->with(['event:id,title', 'actor:id,name,email'])
            ->orderByDesc('created_at')
            ->get()
            ->map(function (EventChangeLog $log): array {
                return [
                    'id' => $log->id,
                    'action' => $log->action,
                    'changes' => $log->changes,
                    'at' => $log->created_at?->toIso8601String(),
                    'event' => $log->event ? [
                        'id' => $log->event->id,
                        'title' => $log->event->title,
                    ] : null,
                    'actor' => $log->actor ? [
                        'id' => $log->actor->id,
                        'name' => $log->actor->name,
                        'email' => $log->actor->email,
                    ] : null,
                ];
            });

        return response()->json(['data' => $logs]);
    }
}
