<?php

namespace App\Http\Controllers;

use App\Actions\RealtimeHealth;
use App\Models\Tournament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CompetitionSystemController extends Controller
{
    public function __invoke(Tournament $tournament, RealtimeHealth $health): Response
    {
        Gate::authorize('update', $tournament);

        try {
            DB::select('SELECT 1');
            $database = 'online';
        } catch (\Throwable) {
            $database = 'offline';
        }

        return Inertia::render('Competition/System', [
            'tournament' => ['id' => $tournament->id, 'name' => $tournament->name],
            'system' => [
                'database' => $database,
                'realtime' => $health->mercureStatus(),
                'queue' => config('queue.default'),
                'queue_worker' => $health->queueWorkerStatus(),
                'cache' => config('cache.default'),
                'environment' => app()->environment(),
                'server_time' => now()->toISOString(),
                'courts' => $tournament->courts()->count(),
                'categories' => $tournament->categories()->count(),
            ],
            'backUrl' => route('tournaments.show', $tournament),
        ]);
    }
}
