<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class AnalystRoleRedirect
{
    public function handle($request, Closure $next)
    {
        $user = Auth::user();
        if ($user && $user->role === 'analyst') {

            $allowedPrefixes = ['analysis-tools','logout','login','home','profile'];

            $path = trim($request->path(), '/');

            foreach ($allowedPrefixes as $prefix) {
                if (str_starts_with($path, $prefix)) {
                    return $next($request);
                }
            }

            return redirect('/home');
        }

        return $next($request);
    }
}
