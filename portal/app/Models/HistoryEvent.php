<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HistoryEvent extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['sender_phone' => 'encrypted', 'sender_name' => 'encrypted', 'server_domain' => 'encrypted', 'device_ip' => 'encrypted', 'latitude' => 'encrypted', 'longitude' => 'encrypted', 'accuracy_m' => 'encrypted', 'occurred_at' => 'immutable_datetime'];
    }
}
