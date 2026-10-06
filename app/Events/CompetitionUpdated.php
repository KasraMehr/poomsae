<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class CompetitionUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [1, 5, 15];

    /** @var array{action:string, subject:array{type:string, id:int}, context:array<string, int|array<int, int>>, occurred_at:string}|array{} */
    public array $change = [];

    /** @param array{action:string, subject:array{type:string, id:int}, context:array<string, int|array<int, int>>, occurred_at:string}|array{} $change */
    public function __construct(public int $tournamentId, public int $auditId, array $change = [])
    {
        $this->change = $change;
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('tournaments.'.$this->tournamentId)];
    }

    public function broadcastAs(): string
    {
        return 'competition.updated';
    }

    public function broadcastWith(): array
    {
        return ['schema_version' => 1, 'tournament_id' => $this->tournamentId, 'revision' => $this->auditId, ...$this->change];
    }
}
