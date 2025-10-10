<?php

declare(strict_types=1);

namespace App\Providers;

use Inertia\Inertia;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Laravel\Fortify\Fortify;
use Illuminate\Support\Facades\Route;
use App\Actions\Fortify\CreateNewUser;
use App\Models\User;
use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Actions\User\ActiveOauthProviderAction;
use App\Actions\Fortify\UpdateUserProfileInformation;

final class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::authenticateUsing(function (Request $request): ?User {
            $email = (string) $request->string(Fortify::username());
            $user = User::query()->where('email', $email)->first();

            if (! $user) {
                return null;
            }

            $hashedPassword = $user->password;
            $plainPassword = (string) $request->input('password');

            if (! is_string($hashedPassword) || $hashedPassword === '' || $plainPassword === '') {
                return null;
            }

            if (! Hash::check($plainPassword, $hashedPassword)) {
                return null;
            }

            if ($user->isInactive()) {
                throw ValidationException::withMessages([
                    Fortify::username() => 'Mohon Maaf Akun Anda Belum di Aktifkan, Mohon Hubungi Admin Untuk Mengaktifkan Akun',
                ]);
            }

            return $user;
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower((string) $request->string(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('two-factor', fn (Request $request) => Limit::perMinute(5)->by($request->session()->get('login.id')));

        $this->configureLoginView();
        $this->configureRegisterView();
        $this->configurePasswordResetViews();
        $this->configureEmailVerificationView();
        $this->configureConfirmPasswordView();
        $this->configureTwoFactorChallengeView();
    }

    private function configureLoginView(): void
    {
        Fortify::loginView(fn () => Inertia::render('Auth/Login', [
            'availableOauthProviders' => (new ActiveOauthProviderAction())->handle(),
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]));
    }

    private function configureRegisterView(): void
    {
        Fortify::registerView(fn () => Inertia::render('Auth/Register', [
            'canLogin' => Route::has('login'),
        ]));
    }

    private function configurePasswordResetViews(): void
    {
        Fortify::requestPasswordResetLinkView(fn () => Inertia::render('Auth/ForgotPassword', [
            'status' => session('status'),
        ]));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('Auth/ResetPassword', [
            'email' => trim((string) $request->input('email', '')),
            'token' => (string) $request->route('token'),
        ]));
    }

    private function configureEmailVerificationView(): void
    {
        Fortify::verifyEmailView(fn () => Inertia::render('Auth/VerifyEmail', [
            'status' => session('status'),
        ]));
    }

    private function configureConfirmPasswordView(): void
    {
        Fortify::confirmPasswordView(fn () => Inertia::render('Auth/ConfirmPassword'));
    }

    private function configureTwoFactorChallengeView(): void
    {
        Fortify::twoFactorChallengeView(fn () => Inertia::render('Auth/TwoFactorChallenge'));
    }
}


