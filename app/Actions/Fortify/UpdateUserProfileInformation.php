<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

final class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    /**
     * Validate and update the given user's profile information.
     *
     * @param  array<string, mixed>  $input
     */
    public function update(User $user, array $input): void
    {
        $validated = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'nip' => ['nullable', 'string', 'max:30', Rule::unique('users', 'nip')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'photo' => ['nullable', 'mimes:jpg,jpeg,png', 'max:1024'],
        ])->validateWithBag('updateProfileInformation');

        if (isset($validated['photo']) && $validated['photo'] instanceof UploadedFile) {
            $user->updateProfilePhoto($validated['photo']);
        }

        $user->forceFill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'nip' => Arr::get($validated, 'nip', $user->nip) ?: $user->nip,
            'phone' => Arr::get($validated, 'phone', $user->phone) ?: $user->phone,
        ])->save();
    }
}