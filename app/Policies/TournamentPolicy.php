<?php

namespace App\Policies;

use App\Models\Tournament;
use App\Models\User;

class TournamentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function create(User $user): bool
    {
        return $user->is_active && $user->is_admin;
    }

    public function view(User $user, Tournament $tournament): bool
    {
        return $user->hasTournamentRole($tournament, ['manager', 'operator', 'judge', 'display']);
    }

    public function update(User $user, Tournament $tournament): bool
    {
        return $user->hasTournamentRole($tournament, ['manager']);
    }

    public function operate(User $user, Tournament $tournament): bool
    {
        return $user->hasTournamentRole($tournament, ['manager', 'operator']);
    }
}
