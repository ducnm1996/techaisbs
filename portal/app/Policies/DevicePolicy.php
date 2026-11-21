<?php

namespace App\Policies;

use App\Models\Device;
use App\Models\User;

class DevicePolicy
{
    public function create(User $user): bool
    {
        return $user->is_active && ! $user->setup_pending;
    }

    public function revoke(User $user, Device $device): bool
    {
        return $user->is_active && ($user->id === $device->user_id || $user->role === 'admin');
    }
}
