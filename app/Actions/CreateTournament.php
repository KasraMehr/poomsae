<?php

namespace App\Actions;

use App\Events\TournamentCreated;
use App\Models\AuditLog;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CreateTournament
{
    public function handle(User $actor, array $data): Tournament
    {
        Gate::forUser($actor)->authorize('create', Tournament::class);

        return DB::transaction(function () use ($actor, $data): Tournament {
            $tournament = Tournament::create([...$data, 'created_by' => $actor->id, 'status' => 'draft']);
            $tournament->users()->attach($actor, ['role' => 'manager']);
            AuditLog::create([
                'tournament_id' => $tournament->id, 'user_id' => $actor->id,
                'action' => 'tournament.created', 'subject_type' => 'tournament',
                'subject_id' => $tournament->id, 'after' => $tournament->toArray(),
            ]);
            TournamentCreated::dispatch($tournament->id);

            return $tournament;
        });
    }
}
