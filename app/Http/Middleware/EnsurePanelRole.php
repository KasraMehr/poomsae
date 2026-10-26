<?php

namespace App\Http\Middleware;

use App\Models\Tournament;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePanelRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $tournament = $request->route('tournament');
        abort_unless($tournament instanceof Tournament && in_array($request->user()?->panelRole($tournament), $roles, true), 403);

        return $next($request);
    }
}
