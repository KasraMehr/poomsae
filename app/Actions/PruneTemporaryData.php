<?php

namespace App\Actions;

use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

class PruneTemporaryData
{
    /**
     * Prune old idempotency keys and expired Sanctum personal access tokens.
     *
     * @return array{idempotency_keys: int, personal_access_tokens: int}
     */
    public function handle(int $idempotencyHours = 24, int $tokenHours = 0): array
    {
        return [
            'idempotency_keys' => $this->pruneIdempotencyKeys($idempotencyHours),
            'personal_access_tokens' => $this->pruneExpiredTokens($tokenHours),
        ];
    }

    /**
     * Prune idempotency keys older than the given number of hours.
     */
    public function pruneIdempotencyKeys(int $hours = 24): int
    {
        return DB::table('idempotency_keys')
            ->where('created_at', '<', now()->subHours($hours))
            ->delete();
    }

    /**
     * Prune expired Sanctum personal access tokens.
     */
    public function pruneExpiredTokens(int $tokenHours = 0): int
    {
        $model = Sanctum::$personalAccessTokenModel;

        $deleted = $model::where('expires_at', '<', now()->subHours($tokenHours))->delete();

        if ($expiration = config('sanctum.expiration')) {
            $deleted += $model::where('created_at', '<', now()->subMinutes($expiration + ($tokenHours * 60)))->delete();
        }

        return $deleted;
    }
}
