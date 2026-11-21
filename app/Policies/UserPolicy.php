<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function create(User $user): bool
    {
        return $user->is_active && $user->role === 'admin';
    }

    public function manage(User $user, User $target): bool
    {
        return $user->is_active && $user->role === 'admin' && $user->id !== $target->id;
    }
}
