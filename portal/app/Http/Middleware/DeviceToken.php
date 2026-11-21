<?php

namespace App\Http\Middleware;

use App\Models\Device;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class DeviceToken
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(strlen($request->getContent()) > 65536, 413);
        $device = $request->user();
        abort_unless($device instanceof Device && $device->currentAccessToken() instanceof PersonalAccessToken, 401);
        abort_unless($device->currentAccessToken()->expires_at && $device->currentAccessToken()->expires_at->isFuture(), 401);
        abort_unless(! $device->revoked_at && $device->user?->is_active && ! $device->user?->setup_pending, 401);
        abort_unless($device->tokenCan('events:write'), 403);

        return $next($request);
    }
}
