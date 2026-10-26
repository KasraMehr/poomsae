<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response|RedirectResponse
    {
        if (! $request->user()->is_admin) {
            $tournament = Tournament::query()->visibleTo($request->user())->orderByRaw("CASE WHEN status = 'running' THEN 0 WHEN status = 'draft' THEN 1 ELSE 2 END")->latest('id')->first();
            abort_unless($tournament !== null, 403);

            return redirect()->route(match ($request->user()->panelRole($tournament)) {
                'admin' => 'tournaments.setup',
                'operator' => 'tournaments.show',
                'judge' => 'judging.index',
                'display' => 'scoreboard.rtds',
                default => abort(403),
            }, $tournament);
        }
        $query = Tournament::query()->visibleTo($request->user())->where('status', '!=', 'archived');
        if ((clone $query)->count() === 1) {
            return redirect()->route('tournaments.setup', (clone $query)->firstOrFail());
        }

        return Inertia::render('Dashboard', [
            'stats' => [
                'total' => (clone $query)->count(),
                'running' => (clone $query)->where('status', 'running')->count(),
                'draft' => (clone $query)->where('status', 'draft')->count(),
            ],
            'tournaments' => $query->withCount('categories', 'courts')->latest()->limit(6)->get(),
        ]);
    }
}
