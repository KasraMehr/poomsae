<?php

use App\Models\Tournament;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('tournaments.{tournament}', function (User $user, Tournament $tournament): bool {
    return $user->hasTournamentRole($tournament, ['manager', 'operator', 'judge', 'display']);
}, ['guards' => ['web', 'sanctum']]);

Broadcast::channel('tournaments.{tournament}.judges', function (User $user, Tournament $tournament): array|false {
    if (! $user->hasTournamentRole($tournament, ['manager', 'operator', 'judge'])) {
        return false;
    }

    return ['id' => $user->id, 'name' => $user->name];
}, ['guards' => ['web', 'sanctum']]);
