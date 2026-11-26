<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForcePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->mustChangePassword()
            || $request->routeIs('profile.password', 'logout', 'lang.switch')
            || $request->is('livewire/*')) {
            return $next($request);
        }

        return redirect()->route('profile.password');
    }
}
