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
        $revisions = $operate ? DB::table('score_revisions')->join('users', 'users.id', '=', 'score_revisions.changed_by')
            ->whereIn('score_sheet_id', $sheetIds)
            ->select('score_revisions.*', 'users.name as changed_by_name')
            ->orderBy('score_revisions.revision')->get()->groupBy('score_sheet_id') : collect();
        $categories = $tournament->categories->map(function ($category) use ($actor, $display, $operate, $components, $revisions, $tournament) {
            $standingsRoundId = $category->rounds->filter(fn ($round) => $round->bouts->isNotEmpty())->sortBy('sequence')->last()?->id;
            $wins = [];
            $played = [];
            $scores = [];
            $restoredScores = [];
            $scoreBased = $category->format === 'round_robin' && $category->rounds->flatMap(fn ($round) => $round->bouts)->every(fn ($bout) => $bout->entries->count() === 1);
            $rounds = $category->rounds->sortBy('sequence')->map(function ($round) use ($actor, $display, $operate, $components, $revisions, &$wins, &$played, &$scores, &$restoredScores, $scoreBased, $category, $standingsRoundId) {
                $bouts = $round->bouts->sortBy('sequence')->map(function ($bout) use ($actor, $display, $operate, $components, $revisions, &$wins, &$played, &$scores, &$restoredScores, $scoreBased, $category, $round, $standingsRoundId) {
                    $ownJudge = $bout->judges->firstWhere('user_id', $actor->id);
                    $totals = [];
                    $restoredTotals = [];
                    foreach ($bout->performances->groupBy('entry_id') as $entryId => $forms) {
                        if ($forms->count() === $category->forms_per_round && $forms->every(fn ($p) => $p->status === 'approved' && $p->result?->published_at)) {
                            $aggregation = $category->scoringRuleSet->definition['aggregation'] ?? 'mean_two_forms';
                            $sum = $forms->sum(fn ($p) => $this->calculator->micros($p->result->score));
                            $totals[$entryId] = $this->calculator->format($aggregation === 'mean_two_forms' ? intdiv($sum + 1, 2) : $sum);
                            $restoredFormScores = $forms->map(fn ($p) => $this->calculator->restoredMeanMicros($p->result->calculation_snapshot));
                            if (! $restoredFormScores->containsStrict(null)) {
                                $restoredSum = $restoredFormScores->sum();
                                $restoredTotals[$entryId] = $aggregation === 'mean_two_forms' ? intdiv($restoredSum + 1, 2) : $restoredSum;
                            }
                        }
                    }
                    if ($bout->status === 'completed') {
                        if ($scoreBased && $round->id === $standingsRoundId && count($totals) === 1) {
                            $entryId = array_key_first($totals);
                            $scores[$entryId] = $this->calculator->micros($totals[$entryId]);
                            $restoredScores[$entryId] = $restoredTotals[$entryId] ?? null;
                        }
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
                        'performances' => $bout->performances->sortBy('id')->map(function ($p) use ($bout, $display, $operate, $ownJudge, $components, $revisions, $category) {
                            $own = ! $display && $ownJudge ? $p->scoreSheets->firstWhere('judge_assignment_id', $ownJudge->id) : null;
                            $ownValues = $own ? ($components->get($own->id, collect())->pluck('value_hundredths', 'criterion')->all()) : null;

                            return [
                                'id' => $p->id, 'entry_id' => $p->entry_id, 'form_number' => $p->form_number, 'form_name' => $p->form?->name ?? ($category->discipline === 'freestyle' ? 'اجرای ابداعی' : 'در انتظار قرعهٔ پومسه'), 'music_url' => $p->music_path && $operate ? route('operations.performance.music', [$category->tournament_id, $p->id]) : null, 'status' => $p->status, 'version' => $p->version,
                                'started_at' => $p->started_at?->toISOString(), 'ended_at' => $p->ended_at?->toISOString(),
                                'result' => $p->result?->published_at ? $p->result->score : null,
                                'submitted_count' => $p->scoreSheets->where('status', 'submitted')->count(),
                                'is_assigned' => ! $display && $ownJudge !== null,
                                'own_score' => $own ? ['revision' => $own->revision, 'values' => $ownValues, 'breakdown' => $own->breakdown] : null,
                                'scores' => $operate ? $p->scoreSheets->map(fn ($s) => [
                                    'id' => $s->id, 'seat' => $bout->judges->firstWhere('id', $s->judge_assignment_id)?->seat, 'revision' => $s->revision, 'status' => $s->status,
                                    'submission_mode' => $s->submission_mode, 'submitted_by' => $s->submitted_by, 'submitted_at' => $s->submitted_at?->toISOString(),
                                    'confirmed_by' => $s->confirmed_by, 'confirmed_at' => $s->confirmed_at?->toISOString(),
                                    'values' => $components->get($s->id, collect())->pluck('value_hundredths', 'criterion')->all(), 'breakdown' => $s->breakdown,
                                    'history' => $revisions->get($s->id, collect())->map(fn ($revision) => [
                                        'revision' => $revision->revision, 'changed_by' => $revision->changed_by, 'changed_by_name' => $revision->changed_by_name,
                                        'created_at' => $revision->created_at, 'reason' => $revision->reason,
                                        'snapshot' => json_decode($revision->snapshot, true, 512, JSON_THROW_ON_ERROR),
                                    ])->values(),
                                ])->values() : [],
                            ];
                        })->values(),
                    ];
                })->values();

                $formsReady = $category->discipline === 'freestyle' || ($category->planned_round_count === null ? count($category->form_sequence ?? []) === 2 : $round->forms_drawn_at !== null && count($round->form_sequence ?? []) === 2);

                return ['id' => $round->id, 'name' => $round->name, 'sequence' => $round->sequence, 'status' => $round->status, 'bouts' => $bouts,
                    'scheduled' => $round->scheduled_at !== null || $bouts->isNotEmpty(), 'forms_ready' => $formsReady,
                    'form_ids' => $round->form_sequence ?? [], 'form_names' => collect($round->form_sequence ?? [])->map(fn ($id) => $category->forms->firstWhere('id', $id)?->name)->values(),
                    'forms_drawn_at' => $round->forms_drawn_at?->toISOString(), 'started_at' => $round->started_at?->toISOString(),
                    'previous_completed' => $round->sequence === 1 || $category->rounds->firstWhere('sequence', $round->sequence - 1)?->status === 'completed',
                    'schedule_version' => $round->schedule_version, 'source_version' => $round->source_version,
                ];
            })->values();
            $last = $rounds->last();
            $completed = $last && $last['status'] === 'completed' && ($category->format === 'round_robin' || count($last['bouts']) === 1)
                && ($category->planned_round_count === null || $rounds->every(fn ($round) => $round['status'] === 'completed'));
            $standings = $category->entries->where('status', 'checked_in')->map(fn ($e) => [
                'id' => $e->id, 'name' => $e->display_name, 'wins' => $wins[$e->id] ?? 0, 'played' => $played[$e->id] ?? 0,
                'score' => isset($scores[$e->id]) ? $this->calculator->format($scores[$e->id]) : null,
                'score_micros' => $scores[$e->id] ?? null,
                'restored_score_micros' => $restoredScores[$e->id] ?? null,
            ]);
            $restoredTieBreakScores = $scoreBased
                ? $standings->whereNotNull('score_micros')->groupBy('score_micros')->filter(
                    fn ($rows) => $rows->count() > 1 && $rows->every(fn ($row) => $row['restored_score_micros'] !== null)
                )->keys()->map(fn ($score) => (int) $score)->all()
                : [];
            $standings = $standings->sort(function ($left, $right) use ($scoreBased, $restoredTieBreakScores): int {
                if (! $scoreBased) {
                    return $right['wins'] <=> $left['wins'];
                }
                $primaryComparison = ($right['score_micros'] ?? PHP_INT_MIN) <=> ($left['score_micros'] ?? PHP_INT_MIN);
                if ($primaryComparison !== 0 || ! in_array($left['score_micros'], $restoredTieBreakScores, true)) {
                    return $primaryComparison;
                }

                return $right['restored_score_micros'] <=> $left['restored_score_micros'];
            })->values();
            $previousValue = null;
            $rank = 0;
            $standings = $standings->map(function ($row, $index) use (&$previousValue, &$rank, $scoreBased, $restoredTieBreakScores) {
                $usesRestoredTieBreak = $scoreBased && in_array($row['score_micros'], $restoredTieBreakScores, true);
                $value = $scoreBased ? [$row['score_micros'], $usesRestoredTieBreak ? $row['restored_score_micros'] : null] : $row['wins'];
                if ($previousValue !== $value || $index === 0) {
                    $rank = $index + 1;
                    $previousValue = $value;
                }

                $row['tie_break_score'] = $usesRestoredTieBreak ? $this->calculator->format($row['restored_score_micros']) : null;
                unset($row['score_micros']);
                unset($row['restored_score_micros']);

                return [...$row, 'rank' => $scoreBased && $value[0] === null ? null : $rank];
            });

            return [
                'id' => $category->id, 'name' => $category->name, 'gender' => $category->gender, 'minimum_age' => $category->minimum_age, 'maximum_age' => $category->maximum_age,
                'format' => $category->format, 'discipline' => $category->discipline, 'entry_type' => $category->entry_type, 'forms_per_round' => $category->forms_per_round, 'execution_mode' => $category->execution_mode, 'performance_order' => $category->performance_order, 'score_based' => $scoreBased, 'judge_count' => $category->judge_count, 'rules' => $category->scoringRuleSet?->definition, 'form_names' => $category->form_sequence ? collect($category->form_sequence)->map(fn ($id) => $category->forms->firstWhere('id', $id)?->name)->all() : [],
                'entries' => $category->entries->map(fn ($e) => ['id' => $e->id, 'name' => $e->display_name, 'status' => $e->status, 'club' => $e->athletes->first()?->club, 'members' => $e->athletes->sortBy('pivot.position')->map(fn ($athlete) => ['name' => $athlete->first_name.' '.$athlete->last_name, 'gender' => $athlete->gender])->values()])->values(),
                'rounds' => $rounds, 'completed' => $completed, 'champion_id' => $completed && $category->format === 'knockout' ? $last['bouts'][0]['winner_entry_id'] : null,
                'planned_round_count' => $category->planned_round_count, 'allow_form_repetition' => $category->allow_form_repetition, 'form_draw_timing' => $category->form_draw_timing, 'form_draw_time' => $category->form_draw_time,
                'form_pool' => $category->forms->map(fn ($form) => ['id' => $form->id, 'name' => $form->name])->values(),
                'draw_available_at' => app(DrawRoundForms::class)->availableAt($tournament, $category)->toISOString(),
                'registration_locked' => $category->rounds->contains(fn ($round) => $round->bouts->isNotEmpty()),
                'management_source' => $category->management_source, 'management_version' => $category->management_version, 'synced_at' => $category->synced_at?->toISOString(), 'standings_round_id' => $standingsRoundId,
                'standings' => $category->format === 'round_robin' ? $standings : [],
            ];
        })->values();

        return [
            'id' => $tournament->id, 'name' => $tournament->name, 'venue' => $tournament->venue, 'starts_on' => $tournament->starts_on->format('Y-m-d'), 'timezone' => $tournament->timezone, 'status' => $tournament->status,
            'courts' => $tournament->courts->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->values(),
            'members' => $manage ? $tournament->users()->get(['users.id', 'users.name', 'users.email', 'users.is_active'])->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'role' => $u->pivot->role, 'is_active' => $u->is_active])->values() : [],
            'categories' => $categories,
        ];
    }
}
