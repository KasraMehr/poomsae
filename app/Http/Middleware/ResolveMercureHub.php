<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Symfony\Component\HttpFoundation\Response;

class ResolveMercureHub
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $connection = config('broadcasting.connections.mercure');
        if (config('broadcasting.default') === 'mercure'
            && $request->is('broadcasting/auth', 'api/v1/broadcasting/auth')
            && str_starts_with($connection['public_url'] ?? '', '/')) {
            $connection['public_url'] = $request->getSchemeAndHttpHost().$connection['public_url'];
            Broadcast::connection('mercure')->setHub(Broadcast::mercure($connection));
        }

        return $next($request);
    }
}
