<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;

class Audit
{
    public static function record(?User $actor, string $action, string $type, int $id, array $changes = []): void
    {
        AuditLog::create(['actor_id' => $actor?->id, 'action' => $action, 'target_type' => $type, 'target_id' => $id, 'changes' => $changes]);
    }
}
