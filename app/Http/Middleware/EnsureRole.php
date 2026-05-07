<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        $user = $request->user();
        $allowedRoles = collect($roles)
            ->flatMap(fn (string $role) => explode(',', $role))
            ->map(fn (string $role) => strtolower(trim($role)))
            ->filter()
            ->values()
            ->all();

        if (! $user || ! $user->hasRole($allowedRoles)) {
            if ($request->wantsJson() || $request->routeIs('dashboard')) {
                abort(Response::HTTP_FORBIDDEN, 'This action is unauthorized.');
            }

            return redirect()
                ->route('dashboard')
                ->with('error', 'You do not have access to that page.');
        }

        return $next($request);
    }
}
