<?php

namespace App\Http\Controllers;

use App\Actions\CompetitionConsole;
use App\Actions\CompetitionView;
use App\Actions\CreateTournament;
use App\Http\Requests\StoreTournamentRequest;
use App\Models\Tournament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TournamentController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Tournaments/Index', [
            'tournaments' => Tournament::query()->visibleTo($request->user())->withCount('categories', 'courts')->latest()->paginate(12),
        ]);
    }

    public function store(StoreTournamentRequest $request, CreateTournament $action): RedirectResponse
    {
        $tournament = $action->handle($request->user(), $request->validated());

        return redirect()->route('tournaments.show', $tournament)->with('success', 'مسابقه ساخته شد.');
    }

    public function show(Request $request, Tournament $tournament, CompetitionConsole $console): Response|RedirectResponse
    {
        Gate::authorize('view', $tournament);

        if (! $request->user()->can('operate', $tournament)) {
            if ($request->user()->hasTournamentRole($tournament, ['judge'])) {
                return redirect()->route('judging.index', $tournament);
            }

            return redirect()->route('scoreboard.show', $tournament);
        }

        return Inertia::render('Competition/Console', [
            'console' => $console->snapshot($tournament, $request->user()),
            'can' => $this->permissions($request, $tournament),
            'competitionUrls' => $this->urls($tournament),
        ]);
    }

    public function setup(Request $request, Tournament $tournament, CompetitionView $view): Response
    {
        Gate::authorize('update', $tournament);

        return Inertia::render('Tournaments/Show', [
            'tournament' => $view->snapshot($tournament, $request->user()),
            'can' => $this->permissions($request, $tournament),
            'competitionUrls' => $this->urls($tournament),
        ]);
    }

    /** @return array<string, bool> */
    private function permissions(Request $request, Tournament $tournament): array
    {
        return [
            'manage' => $request->user()->can('update', $tournament),
            'operate' => $request->user()->can('operate', $tournament),
            'judge' => $request->user()->hasTournamentRole($tournament, ['judge']),
        ];
    }

    /** @return array<string, string> */
    private function urls(Tournament $tournament): array
    {
        return [
            'base' => route('tournaments.show', $tournament),
            'setup' => route('tournaments.setup', $tournament),
            'judge' => route('judging.index', $tournament),
            'scoreboard' => route('scoreboard.show', $tournament),
            'export' => route('scoreboard.export', $tournament),
        ];
    }
}
