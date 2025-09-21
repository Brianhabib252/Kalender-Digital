<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserChangeLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

final class UserLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // Only admins
        \abort_unless($request->user() instanceof User && $request->user()->isAdmin(), 403);

        $date = $request->string('date')->toString();
        $userId = $request->integer('user_id');

        $start = $date ? Carbon::parse($date)->startOfDay() : now()->startOfDay();
        $end = (clone $start)->endOfDay();

        $logs = UserChangeLog::query()
            ->when($userId > 0, fn($q) => $q->where('user_id', $userId))
            ->whereBetween('created_at', [$start, $end])
            ->with(['user:id,name,email', 'actor:id,name,email'])
            ->orderByDesc('created_at')
            ->get()
            ->map(function (UserChangeLog $log): array {
                return [
                    'id' => $log->id,
                    'action' => $log->action,
                    'changes' => $log->changes,
                    'at' => $log->created_at?->toIso8601String(),
                    'user' => $log->user ? [
                        'id' => $log->user->id,
                        'name' => $log->user->name,
                        'email' => $log->user->email,
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

