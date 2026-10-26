<?php

namespace App\Events;

class CompetitionDomainUpdated extends CompetitionUpdated
{
    public const DOMAINS = ['scoreboard', 'console', 'judging', 'athletes', 'draw', 'results', 'courts', 'members', 'appeals'];

    /** @param array{action:string, subject:array{type:string, id:int}, context:array<string, int|array<int, int>>, occurred_at:string} $change */
    public function __construct(int $tournamentId, int $auditId, public string $domain, array $change)
    {
        parent::__construct($tournamentId, $auditId, $change);
    }

    public function broadcastAs(): string
    {
        return $this->domain.'.updated';
    }
}
