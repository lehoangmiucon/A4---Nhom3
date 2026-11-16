<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaffSessionCurrent
{
    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('web');
        // Login replaces the credential marker after checking the new password.
        if (! $request->hasSession() || ($request->isMethod('post') && $request->is('login'))
            || ! $request->session()->has($guard->getName()) || $guard->user()?->role !== 'staff') {
            return $next($request);
        }

        $stored = $request->session()->get('staff_password_hash');
        $current = $guard->hashPasswordForCookie($guard->user()->getAuthPassword());
        // Legacy sessions must reauthenticate, rather than adopt the new password hash.
        if (! is_string($stored) || ! hash_equals($current, $stored)) {
            $guard->logoutCurrentDevice();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            throw new AuthenticationException('Unauthenticated.', ['web']);
        }

        $response = $next($request);
        if ($guard->check()) {
            // A staff member changing their own password may keep this session.
            $request->session()->put('staff_password_hash', $guard->hashPasswordForCookie($guard->user()->getAuthPassword()));
        }

        return $response;
    }
}
