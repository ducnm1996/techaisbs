<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistoryEvent extends Model
{
    protected $guarded = ['id'];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    protected function casts(): array
    {
        return ['sender_phone' => 'encrypted', 'sender_name' => 'encrypted', 'server_domain' => 'encrypted', 'device_ip' => 'encrypted', 'latitude' => 'encrypted', 'longitude' => 'encrypted', 'accuracy_m' => 'encrypted', 'app_package' => 'encrypted', 'call_direction' => 'encrypted', 'duration_seconds' => 'encrypted', 'device_metadata' => 'encrypted:array', 'network_metadata' => 'encrypted:array', 'occurred_at' => 'immutable_datetime'];
    }
}
