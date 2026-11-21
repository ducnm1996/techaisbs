<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Device extends Authenticatable
{
    use HasApiTokens;

    protected $guarded = ['id'];

    protected $hidden = ['remember_token'];

    protected function casts(): array
    {
        return ['sources' => 'array', 'allow_ip' => 'boolean', 'allow_location' => 'boolean', 'revoked_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
