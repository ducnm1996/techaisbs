<?php

namespace App\Policies;

use App\Models\HistoryEvent;
use App\Models\User;

class HistoryEventPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, HistoryEvent $event): bool
    {
        return $user->is_active && $user->id === $event->user_id;
    }

    public function delete(User $user, HistoryEvent $event): bool
    {
        return $this->view($user, $event);
    }
}
