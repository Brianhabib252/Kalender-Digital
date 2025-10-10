<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateManagedUserRequest;
use App\Models\CalendarHoliday;
use App\Models\User;
use App\Models\UserChangeLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

final class UserManagementController extends Controller
{
    public function index(): Response
    {
        // Restrict to admin users only
        \abort_unless(auth()->user() instanceof User && auth()->user()->isAdmin(), 403);
        $users = User::query()
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'nip', 'phone', 'role', 'created_at']);

        $holidays = CalendarHoliday::query()
            ->orderBy('calendar_type')
            ->orderBy('gregorian_month')
            ->orderBy('gregorian_day')
            ->orderBy('hijri_month')
            ->orderBy('hijri_day')
            ->get(['id', 'name', 'calendar_type', 'gregorian_month', 'gregorian_day', 'gregorian_year', 'hijri_month', 'hijri_day', 'hijri_year']);

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'holidays' => $holidays,
            'roles' => [
                User::ROLE_INACTIVE,
                User::ROLE_VIEWER,
                User::ROLE_EDITOR,
                User::ROLE_ADMIN,
            ],
            'adminEmail' => User::ADMIN_EMAIL,
        ]);
    }

    public function update(UpdateManagedUserRequest $request, User $user): RedirectResponse
    {
        $validated = $request->validated();

        // capture original values to diff later
        $original = $user->only(['name','email','nip','phone','role']);

        $user->forceFill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'nip' => $validated['nip'] ?? null,
            'phone' => $validated['phone'] ?? null,
        ]);

        if (! empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        if ($user->email === User::ADMIN_EMAIL) {
            $user->role = User::ROLE_ADMIN;
        } elseif (isset($validated['role'])) {
            $user->role = $validated['role'];
        }

        $user->save();

        // build changes diff: field => [old, new]
        $now = $user->only(['name','email','nip','phone','role']);
        $changes = [];
        foreach ($now as $key => $val) {
            $old = Arr::get($original, $key);
            if ($old !== $val) {
                $changes[$key] = [$old, $val];
            }
        }
        if (! empty($changes) && $request->user() instanceof User) {
            // Soft-guard: only log if logs table exists
            if (Schema::hasTable('user_change_logs')) {
                UserChangeLog::query()->create([
                    'user_id' => $user->id,
                    'actor_id' => $request->user()->id,
                    'action' => 'update_user',
                    'changes' => $changes,
                ]);
            }
        }

        return back()->with('success', 'User updated successfully.');
    }
}
