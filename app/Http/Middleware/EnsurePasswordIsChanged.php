<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A new account (or one whose password the Owner reset) may only visit the
 * "My Profile" page and log out until it has chosen its own password.
 */
class EnsurePasswordIsChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->must_change_password && ! $request->routeIs('profile.*', 'logout')) {
            return redirect()->route('profile.edit')
                ->with('warning', 'Please choose a new password before you continue.');
        }

        return $next($request);
    }
}
