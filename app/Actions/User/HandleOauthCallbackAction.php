<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Models\User;
use App\Traits\AsFakeAction;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use App\Actions\Fortify\CreateNewUser;
use App\Exceptions\OAuthAccountLinkingException;
use App\Jobs\User\UpdateUserProfileInformationJob;
use InvalidArgumentException;
use Laravel\Socialite\Two\User as SocialiteUser;

final readonly class HandleOauthCallbackAction
{
    use AsFakeAction;

    public function handle(string $provider, SocialiteUser $socialiteUser, ?User $authenticatedUser = null): User
    {
        return match (true) {
            $authenticatedUser instanceof User => $this->handleAuthenticatedUser($provider, $socialiteUser, $authenticatedUser),
            default => $this->handleUnauthenticatedUser($provider, $socialiteUser),
        };
    }

    private function handleAuthenticatedUser(string $provider, SocialiteUser $socialiteUser, User $user): User
    {
        $this->validateAuthenticatedUserConnection($provider, $socialiteUser, $user);

        $this->updateUserProfile($user, $socialiteUser, $provider);

        return $user;
    }

    private function handleUnauthenticatedUser(string $provider, SocialiteUser $socialiteUser): User
    {
        return DB::transaction(function () use ($provider, $socialiteUser): User {
            $existingUser = User::query()
                ->whereEmail($socialiteUser->getEmail())
                ->first();

            return match (true) {
                $existingUser instanceof User => $this->handleExistingUser($existingUser, $provider, $socialiteUser),
                default => $this->createNewUser($socialiteUser, $provider),
            };
        });
    }

    private function validateAuthenticatedUserConnection(string $provider, SocialiteUser $socialiteUser, User $user): void
    {
        try {
            Validator::validate([
                'provider' => $provider,
                'provider_id' => $socialiteUser->getId(),
                'email' => $socialiteUser->getEmail(),
            ], [
                'provider' => [
                    'required',
                    'max:255',
                    Rule::unique('oauth_connections')->where(
                        fn (Builder $query) => $query->where('provider', $provider)
                            ->where('provider_id', $socialiteUser->getId())
                    ),
                ],
                'email' => ['required', 'email', Rule::in([$user->email])],
            ], [
                'provider.unique' => __('This account from :provider is already connected to another account.', ['provider' => $provider]),
                'email.in' => __('The email address from this :provider does not match your account email.', ['provider' => $provider]),
            ]);
        } catch (ValidationException) {
            throw_if($socialiteUser->getEmail() !== $user->email, OAuthAccountLinkingException::emailMismatch($provider));

            throw new InvalidArgumentException(__('Validation error try again later.'));
        }
    }

    private function handleExistingUser(User $user, string $provider, SocialiteUser $socialiteUser): User
    {
        throw_unless(
            $user->oauthConnections()->where('provider', $provider)->exists(),
            OAuthAccountLinkingException::existingConnection()
        );

        $this->updateUserProfile($user, $socialiteUser, $provider);

        return $user;
    }

    private function createNewUser(SocialiteUser $socialiteUser, string $provider): User
    {
        $generatedNip = sprintf('%018d', random_int(0, 999999999999999999));
        $generatedPhone = sprintf('08%09d', random_int(0, 999999999));

        $profile = (array) ($socialiteUser->user ?? []);
        $raw = method_exists($socialiteUser, 'getRaw') ? (array) $socialiteUser->getRaw() : [];

        $user = (new CreateNewUser())->create([
            'name' => (string) $socialiteUser->getName(),
            'email' => (string) $socialiteUser->getEmail(),
            'nip' => (string) (Arr::get($profile, 'nip') ?? Arr::get($raw, 'nip') ?? $generatedNip),
            'phone' => (string) (Arr::get($profile, 'phone') ?? Arr::get($raw, 'phone') ?? Arr::get($raw, 'phone_number') ?? $generatedPhone),
            'terms' => (string) true,
        ]);

        $this->updateUserProfile($user, $socialiteUser, $provider);

        return $user;
    }

    private function updateUserProfile(User $user, SocialiteUser $socialiteUser, string $provider): void
    {
        dispatch_sync(new UpdateUserProfileInformationJob($user, $socialiteUser, $provider));
    }
}