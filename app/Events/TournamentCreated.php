<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class TournamentCreated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public int $tournamentId) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('tournaments.'.$this->tournamentId)];
    }

    public function broadcastAs(): string
    {
        return 'tournament.created';
    }

    public function broadcastWith(): array
    {
        return ['schema_version' => 1, 'tournament_id' => $this->tournamentId];
    }
}
