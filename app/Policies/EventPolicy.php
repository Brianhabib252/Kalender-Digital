<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

final class EventPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Event $event): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isEditor() || $user->isAdmin();
    }

    public function update(User $user, Event $event): bool
    {
        if ($user->isEditor()) {
            return (int) $event->created_by === (int) $user->id;
        }

        return false;
    }

    public function delete(User $user, Event $event): bool
    {
        if ($user->isEditor()) {
            return (int) $event->created_by === (int) $user->id;
        }

        return false;
    }
}
