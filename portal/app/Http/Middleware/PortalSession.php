<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class PortalSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('web')->user();
        if (! $user || ! $user->is_active || $user->setup_pending) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login');
        }
        if ($user->role === 'admin' && ! $request->routeIs('mfa.*', 'logout') && (! $user->mfa_confirmed_at || $request->session()->get('mfa_user') !== $user->id)) {
            return redirect()->route('mfa.show');
        }

        return $next($request);
    }
}
