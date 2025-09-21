<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateManagedUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User && $this->user()->isAdmin();
    }

    public function rules(): array
    {
        $userId = (int) $this->route('user')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'nip' => ['nullable', 'string', 'max:30', Rule::unique('users', 'nip')->ignore($userId)],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', Rule::in([User::ROLE_VIEWER, User::ROLE_EDITOR, User::ROLE_ADMIN])],
            'password' => ['nullable', 'string', 'confirmed', 'min:8'],
            'password_confirmation' => ['nullable', 'string'],
        ];
    }
}
