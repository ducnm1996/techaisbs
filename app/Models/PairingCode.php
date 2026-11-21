<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PairingCode extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['code_hash'];

    protected function casts(): array
    {
        return ['sources' => 'array', 'allow_ip' => 'boolean', 'allow_location' => 'boolean', 'expires_at' => 'datetime', 'used_at' => 'datetime'];
    }
}
