<?php

namespace App\Console\Commands;

use App\Actions\PruneTemporaryData as PruneTemporaryDataAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('poomsae:prune-temporary-data {--hours=24 : The number of hours to retain idempotency keys} {--idempotency-hours= : The number of hours to retain idempotency keys (overrides --hours)} {--token-hours=0 : The number of hours past expiration to retain Sanctum tokens} {--only-idempotency : Prune only idempotency keys} {--only-tokens : Prune only expired Sanctum tokens}')]
#[Description('Prune old idempotency keys and expired Sanctum personal access tokens')]
class PruneTemporaryData extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(PruneTemporaryDataAction $action): int
    {
        $idempotencyOption = $this->option('idempotency-hours');
        $idempotencyHours = (int) ($idempotencyOption !== null ? $idempotencyOption : ($this->option('hours') ?? 24));
        $tokenHours = (int) ($this->option('token-hours') ?? 0);

        $onlyTokens = (bool) $this->option('only-tokens');
        $onlyIdempotency = (bool) $this->option('only-idempotency');

        $pruneIdempotency = ! $onlyTokens;
        $pruneTokens = ! $onlyIdempotency;

        if ($pruneIdempotency) {
            $deletedKeys = $action->pruneIdempotencyKeys($idempotencyHours);
            $this->info(sprintf('Pruned %d idempotency key(s) older than %d hour(s).', $deletedKeys, $idempotencyHours));
        }

        if ($pruneTokens) {
            $deletedTokens = $action->pruneExpiredTokens($tokenHours);
            $this->info(sprintf('Pruned %d expired Sanctum token(s).', $deletedTokens));
        }

        return self::SUCCESS;
    }
}
