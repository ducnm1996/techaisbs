<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Throwable;

class InvitationMailer
{
    public function send(User $user): bool
    {
        try {
            return Password::sendResetLink(['email' => $user->email]) === Password::RESET_LINK_SENT;
        } catch (Throwable) {
            Log::warning('Password invitation delivery failed; check SMTP configuration.', ['user_id' => $user->id]);

            return false;
        }
    }
}
