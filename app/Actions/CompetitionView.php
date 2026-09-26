<?php

namespace App\Actions;

use App\Models\Tournament;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CompetitionView
{
    public function __construct(private CalculateScore $calculator) {}

    public function snapshot(Tournament $tournament, User $actor, bool $display = false): array
    {
        $manage = ! $display && $actor->can('update', $tournament);
        $operate = ! $display && $actor->can('operate', $tournament);
        $tournament->load(['courts', 'categories.scoringRuleSet', 'categories.forms', 'categories.entries.athletes', 'categories.rounds.bouts.entries', 'categories.rounds.bouts.judges.user', 'categories.rounds.bouts.performances.form', 'categories.rounds.bouts.performances.result', 'categories.rounds.bouts.performances.scoreSheets']);
        $sheetIds = $tournament->categories->flatMap(fn ($c) => $c->rounds)->flatMap(fn ($r) => $r->bouts)->flatMap(fn ($b) => $b->performances)->flatMap(fn ($p) => $p->scoreSheets)->pluck('id');
        $components = $display ? collect() : DB::table('score_components')->whereIn('score_sheet_id', $sheetIds)->get()->groupBy('score_sheet_id');
        $categories = $tournament->categories->map(function ($category) use ($actor, $display, $operate, $components) {
            $wins = [];
            $played = [];
            $rounds = $category->rounds->sortBy('sequence')->map(function ($round) use ($actor, $display, $operate, $components, &$wins, &$played) {
                $bouts = $round->bouts->sortBy('sequence')->map(function ($bout) use ($actor, $display, $operate, $components, &$wins, &$played) {
                    $ownJudge = $bout->judges->firstWhere('user_id', $actor->id);
                    $totals = [];
                    foreach ($bout->performances->groupBy('entry_id') as $entryId => $forms) {
                        if ($forms->count() === 2 && $forms->every(fn ($p) => $p->status === 'approved' && $p->result?->published_at)) {
                            $totals[$entryId] = $this->calculator->format(intdiv($forms->sum(fn ($p) => $this->calculator->micros($p->result->score)) + 1, 2));
                        }
                    }
                    if ($bout->status === 'completed') {
                        if ($bout->winner_entry_id) {
                            $wins[$bout->winner_entry_id] = ($wins[$bout->winner_entry_id] ?? 0) + 1;
                        }
                        foreach ($bout->entries as $entry) {
                            $played[$entry->id] = ($played[$entry->id] ?? 0) + 1;
                        }
                    }

                    return [
                        'id' => $bout->id, 'sequence' => $bout->sequence, 'court_id' => $bout->court_id, 'status' => $bout->status, 'winner_entry_id' => $bout->winner_entry_id, 'resolution_reason' => $bout->resolution_reason,
                        'entries' => $bout->entries->map(fn ($entry) => ['id' => $entry->id, 'name' => $entry->display_name, 'side' => $entry->pivot->side])->values(),
                        'judges' => $operate ? $bout->judges->map(fn ($j) => ['id' => $j->id, 'user_id' => $j->user_id, 'name' => $j->user->name, 'seat' => $j->seat])->values() : [],
                        'totals' => $totals,
                        'performances' => $bout->performances->sortBy('id')->map(function ($p) use ($bout, $display, $operate, $ownJudge, $components) {
                            $own = ! $display && $ownJudge ? $p->scoreSheets->firstWhere('judge_assignment_id', $ownJudge->id) : null;
                            $ownValues = $own ? ($components->get($own->id, collect())->pluck('value_hundredths', 'criterion')->all()) : null;

                            return [
                                'id' => $p->id, 'entry_id' => $p->entry_id, 'form_number' => $p->form_number, 'form_name' => $p->form?->name, 'status' => $p->status, 'version' => $p->version,
                                'started_at' => $p->started_at?->toISOString(), 'ended_at' => $p->ended_at?->toISOString(),
                                'result' => $p->result?->published_at ? $p->result->score : null,
                                'submitted_count' => $p->scoreSheets->where('status', 'submitted')->count(),
                                'is_assigned' => ! $display && $ownJudge !== null,
                                'own_score' => $own ? ['revision' => $own->revision, 'values' => $ownValues] : null,
                                'scores' => $operate ? $p->scoreSheets->map(fn ($s) => ['seat' => $bout->judges->firstWhere('id', $s->judge_assignment_id)?->seat, 'revision' => $s->revision, 'submission_mode' => $s->submission_mode, 'submitted_by' => $s->submitted_by, 'values' => $components->get($s->id, collect())->pluck('value_hundredths', 'criterion')->all()])->values() : [],
                            ];
                        })->values(),
                    ];
                })->values();

                return ['id' => $round->id, 'name' => $round->name, 'sequence' => $round->sequence, 'status' => $round->status, 'bouts' => $bouts];
            })->values();
            $last = $rounds->last();
            $completed = $last && $last['status'] === 'completed' && ($category->format === 'round_robin' || count($last['bouts']) === 1);
            $standings = $category->entries->where('status', 'checked_in')->map(fn ($e) => ['id' => $e->id, 'name' => $e->display_name, 'wins' => $wins[$e->id] ?? 0, 'played' => $played[$e->id] ?? 0])->sortByDesc('wins')->values();
            $previousWins = null;
            $rank = 0;
            $standings = $standings->map(function ($row, $index) use (&$previousWins, &$rank) {
                if ($previousWins !== $row['wins']) {
                    $rank = $index + 1;
                    $previousWins = $row['wins'];
                }

                return [...$row, 'rank' => $rank];
            });

            return [
                'id' => $category->id, 'name' => $category->name, 'gender' => $category->gender, 'minimum_age' => $category->minimum_age, 'maximum_age' => $category->maximum_age,
                'format' => $category->format, 'judge_count' => $category->judge_count, 'rules' => $category->scoringRuleSet?->definition, 'form_names' => $category->form_sequence ? collect($category->form_sequence)->map(fn ($id) => $category->forms->firstWhere('id', $id)?->name)->all() : [],
                'entries' => $category->entries->map(fn ($e) => ['id' => $e->id, 'name' => $e->display_name, 'status' => $e->status, 'club' => $e->athletes->first()?->club])->values(),
                'rounds' => $rounds, 'completed' => $completed, 'champion_id' => $completed && $category->format === 'knockout' ? $last['bouts'][0]['winner_entry_id'] : null,
                'standings' => $category->format === 'round_robin' ? $standings : [],
            ];
        })->values();

        return [
            'id' => $tournament->id, 'name' => $tournament->name, 'venue' => $tournament->venue, 'starts_on' => $tournament->starts_on->format('Y-m-d'), 'status' => $tournament->status,
            'courts' => $tournament->courts->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->values(),
            'members' => $manage ? $tournament->users()->get(['users.id', 'users.name', 'users.email', 'users.is_active'])->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'role' => $u->pivot->role, 'is_active' => $u->is_active])->values() : [],
            'categories' => $categories,
        ];
    }
}
