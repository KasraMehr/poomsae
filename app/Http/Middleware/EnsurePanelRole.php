<?php

namespace App\Http\Middleware;

use App\Models\Performance;
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
        if (! $tournament instanceof Tournament) {
            $performance = $request->route('performance');
            if ($performance instanceof Performance) {
                $tournament = $performance->bout?->category?->tournament;
            } elseif (is_numeric($performance)) {
                $tournament = Performance::find($performance)?->bout?->category?->tournament;
            }
        }

        abort_unless($tournament instanceof Tournament && in_array($request->user()?->panelRole($tournament), $roles, true), 403);

        return $next($request);
    }
}
