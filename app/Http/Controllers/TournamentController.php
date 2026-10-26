<?php

namespace App\Http\Controllers;

use App\Actions\CompetitionConsole;
use App\Actions\CompetitionView;
use App\Actions\CreateTournament;
use App\Actions\UpdateCompetitionSettings;
use App\Http\Requests\StoreTournamentRequest;
use App\Http\Requests\UpdateCompetitionSettingsRequest;
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
        abort_unless($request->user()->is_admin || $request->user()->tournaments()->wherePivot('role', 'manager')->exists(), 403);
        $filters = $request->validate(['status' => ['nullable', 'in:active,archived,all']]);
        $status = $filters['status'] ?? 'active';
        $query = Tournament::query()->visibleTo($request->user())->withCount('categories', 'courts')->latest();
        if ($status === 'archived') {
            $query->where('status', 'archived');
        } elseif ($status === 'active') {
            $query->where('status', '!=', 'archived');
        }

        return Inertia::render('Tournaments/Index', [
            'tournaments' => $query->paginate(12)->withQueryString(),
            'statusFilter' => $status,
            'listUrls' => [
                'active' => route('tournaments.index'), 'archived' => route('tournaments.index', ['status' => 'archived']),
                'all' => route('tournaments.index', ['status' => 'all']),
            ],
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

        $role = $request->user()->panelRole($tournament);
        if ($role === 'admin') {
            return redirect()->route('tournaments.setup', $tournament);
        }
        if ($role === 'judge') {
            return redirect()->route('judging.index', $tournament);
        }
        if ($role === 'display') {
            return redirect()->route('scoreboard.rtds', $tournament);
        }
        abort_unless($role === 'operator', 403);

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
            'ageCategories' => collect(config('poomsae.age_categories'))->map(fn (array $category, string $id): array => ['id' => $id, ...$category])->values(),
            'ageTableYear' => config('poomsae.age_table_year'),
            'tournament' => $view->snapshot($tournament, $request->user()),
            'can' => $this->permissions($request, $tournament),
            'competitionUrls' => $this->urls($tournament),
        ]);
    }

    public function update(UpdateCompetitionSettingsRequest $request, Tournament $tournament, UpdateCompetitionSettings $settings): RedirectResponse
    {
        $settings->tournament($request->user(), $tournament, $request->validated());

        return back()->with('success', 'اطلاعات مسابقه ویرایش شد.');
    }

    /** @return array<string, bool> */
    private function permissions(Request $request, Tournament $tournament): array
    {
        return [
            'manage' => $request->user()->can('update', $tournament),
            'operate' => $tournament->status !== 'archived' && $request->user()->panelRole($tournament) === 'operator',
            'judge' => $request->user()->panelRole($tournament) === 'judge',
        ];
    }

    /** @return array<string, mixed> */
    private function urls(Tournament $tournament): array
    {
        return [
            'base' => route('tournaments.show', $tournament),
            'setup' => route('tournaments.setup', $tournament),
            'judge' => route('judging.index', $tournament),
            'scoreboard' => route('scoreboard.show', $tournament),
            'scoreboard_pages' => collect(['Standby' => 'standby', 'Live board' => 'live', 'Scoring' => 'scoring', 'Result' => 'result', 'Ranking' => 'ranking', 'Top four' => 'top-four', 'Draw' => 'draw', 'Winner' => 'winner'])
                ->mapWithKeys(fn (string $view, string $label): array => [$label => route('scoreboard.page', [$tournament, $view])])->all(),
            'rtds' => route('scoreboard.rtds', $tournament),
            'export' => route('scoreboard.export', $tournament),
            'results' => route('scoreboard.results', $tournament),
            'excel' => route('scoreboard.excel', $tournament),
            'reports' => route('reports.judges', $tournament),
            'system' => route('competition.system', $tournament),
            'appeals' => route('appeals.index', $tournament),
        ];
    }
}
