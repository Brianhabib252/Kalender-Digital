<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Laravel\Jetstream\Jetstream;
use Stripe\Customer;

final class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        $validated = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'nip' => ['nullable', 'string', 'max:30', 'unique:users'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => Arr::get($input, 'password') ? $this->passwordRules() : 'sometimes',
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature() ? ['accepted', 'required'] : '',
        ])->validate();

        $nip = trim((string) Arr::get($validated, 'nip', ''));
        if ($nip === '') {
            do {
                $nip = sprintf('%018d', random_int(0, 999999999999999999));
            } while (User::query()->where('nip', $nip)->exists());
        }

        $phone = trim((string) Arr::get($validated, 'phone', ''));
        if ($phone === '') {
            $phone = sprintf('08%09d', random_int(0, 999999999));
        }

        return DB::transaction(fn () => tap(User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'nip' => $nip,
            'phone' => $phone,
            'password' => Arr::get($validated, 'password') ? Hash::make($validated['password']) : Str::random(12),
            'role' => $this->determineRole($validated['email']),
        ]), function (User $user): void {
            $this->createTeam($user);
            $this->createCustomer($user);
        }));
    }

    /**
     * Create a personal team for the user.
     */
    private function createTeam(User $user): void
    {
        $user->ownedTeams()->save(Team::query()->forceCreate([
            'user_id' => $user->id,
            'name' => explode(' ', $user->name, 2)[0]."'s Team",
            'personal_team' => true,
        ]));
    }

    /**
     * Create a billing customer for the user.
     */
    private function createCustomer(User $user): void
    {
        if (! Config::get('cashier.billing_enabled')) {
            return;
        }

        /** @var Customer $stripeCustomer */
        $stripeCustomer = $user->createOrGetStripeCustomer();

        $user->update([
            'stripe_id' => $stripeCustomer->id,
        ]);
    }

    private function determineRole(string $email): string
    {
        return strcasecmp($email, User::ADMIN_EMAIL) === 0
            ? User::ROLE_ADMIN
            : User::ROLE_VIEWER;
    }
}
