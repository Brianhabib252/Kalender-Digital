<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use App\Actions\Jetstream\DeleteUser;
use App\Actions\Fortify\CreateNewUser;
use Illuminate\Database\Eloquent\Collection;
use App\Actions\Fortify\UpdateUserProfileInformation;
use Illuminate\Validation\Rule;

/**
 * @group User management
 *
 * APIs for managing users
 */
final class ApiUserController extends Controller
{
    /**
     * Display a paginated list of users.
     *
     * @return Collection<int, User>|null
     *
     * @authenticated
     */
    public function index(): ?Collection
    {
        Gate::authorize('viewAny', User::class);

        return type(Auth::user())->as(User::class)->currentTeam?->users;
    }

    /**
     * Create a new user.
     *
     * @authenticated
     */
    public function store(Request $request): User
    {
        Gate::authorize('create', User::class);

        return app(CreateNewUser::class)->create([
            'name' => (string) $request->input('name'),
            'email' => (string) $request->input('email'),
            'nip' => (string) $request->input('nip'),
            'phone' => (string) $request->input('phone'),
            'password' => (string) $request->input('password'),
            'password_confirmation' => (string) $request->input('password_confirmation'),
            'terms' => 'true',
        ]);
    }

    /**
     * Get a specific user by ID.
     */
    public function show(User $user): User
    {
        Gate::authorize('view', $user);

        return $user;
    }

    /**
     * Update user profile information.
     *
     * @authenticated
     */
    public function update(Request $request, User $user): User
    {
        Gate::authorize('update', $user);

        app(UpdateUserProfileInformation::class)->update($user, [
            'name' => (string) ($request->input('name') ?? $user->name),
            'email' => (string) ($request->input('email') ?? $user->email),
            'nip' => (string) ($request->input('nip') ?? $user->nip),
            'phone' => (string) ($request->input('phone') ?? $user->phone),
        ]);

        if ($request->filled('role')) {
            abort_unless($request->user() instanceof User && $request->user()->isAdmin(), 403);

            $validatedRole = $request->validate([
                'role' => [Rule::in([User::ROLE_VIEWER, User::ROLE_EDITOR])],
            ]);

            if ($user->email === User::ADMIN_EMAIL) {
                $validatedRole['role'] = User::ROLE_ADMIN;
            }

            $user->forceFill(['role' => $validatedRole['role']])->save();
        }

        return $user->refresh();
    }

    /**
     * Delete a user.
     *
     * @authenticated
     */
    public function destroy(User $user): Response
    {
        Gate::authorize('delete', $user);
        app(DeleteUser::class)->delete($user);

        return response()->noContent();
    }
}
