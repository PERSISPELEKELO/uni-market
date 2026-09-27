<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signs out a user the moment a suspension takes effect, even if they were
 * already logged in when it happened - suspension isn't just a login gate.
 */
class EnsureUserIsNotSuspended
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user !== null && $user->isSuspended()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('warning', 'This account has been suspended. Contact support if you believe this is a mistake.');
        }

        return $next($request);
    }
}
