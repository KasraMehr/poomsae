<?php

namespace App\Actions;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class RealtimeHealth
{
    public function mercureStatus(): string
    {
        if (config('broadcasting.default') !== 'mercure') {
            return 'polling';
        }

        $url = config('broadcasting.connections.mercure.url');
        if (! is_string($url) || ! in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
            return 'misconfigured';
        }

        try {
            $response = Http::connectTimeout(1)->timeout(2)->head($url);
            $server = strtolower($response->header('Server', ''));

            return str_contains($server, 'mercure.rocks') || $response->header('Accept-Query') !== ''
                ? 'online' : 'offline';
        } catch (Throwable) {
            return 'offline';
        }
    }

    public function queueWorkerStatus(): string
    {
        $connection = config('queue.default');
        if ($connection === 'sync') {
            return 'inline';
        }
        if (! is_string($connection) || $connection === 'null' || config('cache.default') === 'array') {
            return 'unknown';
        }

        try {
            return Cache::has('poomsae:queue-worker:'.$connection) ? 'online' : 'offline';
        } catch (Throwable) {
            return 'unknown';
        }
    }
}
