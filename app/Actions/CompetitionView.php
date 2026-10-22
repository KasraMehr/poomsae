<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\CompetitionAppeal;
use App\Models\PoomsaeForm;
use App\Models\Tournament;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class CompetitionView
{
    public function __construct(private CalculateScore $calculator, private CompetitionTiming $timing) {}

    public function snapshot(Tournament $tournament, User $actor, bool $display = false): array
    {
        $manage = ! $display && $actor->can('update', $tournament);
        $operate = ! $display && $actor->can('operate', $tournament);
        $tournament->load(['courts', 'categories.scoringRuleSet', 'categories.forms', 'categories.entries.athletes', 'categories.rounds.bouts.entries.athletes', 'categories.rounds.bouts.judges.user', 'categories.rounds.bouts.performances.form', 'categories.rounds.bouts.performances.result', 'categories.rounds.bouts.performances.scoreSheets']);
        $sheetIds = $tournament->categories->flatMap(fn ($c) => $c->rounds)->flatMap(fn ($r) => $r->bouts)->flatMap(fn ($b) => $b->performances)->flatMap(fn ($p) => $p->scoreSheets)->pluck('id');
        $components = DB::table('score_components')->whereIn('score_sheet_id', $sheetIds)->get()->groupBy('score_sheet_id');
        $revisions = $operate ? DB::table('score_revisions')->join('users', 'users.id', '=', 'score_revisions.changed_by')
            ->whereIn('score_sheet_id', $sheetIds)
            ->select('score_revisions.*', 'users.name as changed_by_name')
            ->orderBy('score_revisions.revision')->get()->groupBy('score_sheet_id') : collect();
        $lastEnded = $tournament->categories->flatMap(fn ($c) => $c->rounds)->flatMap(fn ($r) => $r->bouts)->flatMap(fn ($b) => $b->performances)
            ->whereNotNull('ended_at')->groupBy('entry_id')->map(fn ($items) => CarbonImmutable::createFromTimestampMs($items->max(fn ($p) => $p->timing_snapshot['ended_at_milliseconds'] ?? $p->ended_at->getTimestampMs())));
        $appeals = CompetitionAppeal::where('tournament_id', $tournament->id)->get(['id', 'category_id', 'performance_id', 'status'])->keyBy('performance_id');
        $categories = $tournament->categories->map(function ($category) use ($actor, $display, $operate, $manage, $components, $revisions, $tournament, $lastEnded, $appeals) {
            $checkedInCount = $category->entries->where('status', 'checked_in')->count();
            $automaticStageCount = $category->format === 'knockout' && $category->management_source === null;
            $standingsRoundId = $category->rounds->filter(fn ($round) => $round->bouts->isNotEmpty())->sortBy('sequence')->last()?->id;
            $lastStartedSequence = $category->rounds->filter(fn ($round) => $round->started_at !== null || in_array($round->status, ['running', 'completed'], true))->max('sequence') ?? 0;
            $wins = [];
            $played = [];
            $scores = [];
            $presentationScores = [];
            $restoredScores = [];
            $withdrawn = [];
            $formScores = [];
            $scoreBased = $category->format === 'round_robin' && $category->rounds->flatMap(fn ($round) => $round->bouts)->every(fn ($bout) => $bout->entries->count() === 1);
            $rounds = $category->rounds->sortBy('sequence')->map(function ($round) use ($actor, $display, $operate, $components, $revisions, &$wins, &$played, &$scores, &$presentationScores, &$restoredScores, &$withdrawn, &$formScores, $scoreBased, $category, $standingsRoundId, $lastStartedSequence, $tournament, $lastEnded, $appeals) {
                $bouts = $round->bouts->sortBy('sequence')->map(function ($bout) use ($actor, $display, $operate, $components, $revisions, &$wins, &$played, &$scores, &$presentationScores, &$restoredScores, &$withdrawn, &$formScores, $scoreBased, $category, $round, $standingsRoundId, $lastEnded, $appeals) {
                    $ownJudge = $bout->judges->firstWhere('user_id', $actor->id);
                    $totals = [];
                    $presentationTotals = [];
                    $restoredTotals = [];
                    foreach ($bout->performances->groupBy('entry_id') as $entryId => $forms) {
                        if ($forms->count() === $category->forms_per_round && $forms->every(fn ($p) => $p->status === 'approved' && $p->result?->published_at)) {
                            $aggregation = $category->scoringRuleSet->definition['aggregation'] ?? 'mean_two_forms';
                            $sum = $forms->sum(fn ($p) => $this->calculator->micros($p->result->score));
                            $totals[$entryId] = $this->calculator->format($aggregation === 'mean_two_forms' ? intdiv($sum + 1, 2) : $sum);
                            $presentationSum = $forms->sum(fn ($p) => $this->calculator->presentationMicros($p->result->calculation_snapshot));
                            $presentationTotals[$entryId] = $aggregation === 'mean_two_forms' ? intdiv($presentationSum + 1, 2) : $presentationSum;
                            $restoredFormScores = $forms->map(fn ($p) => $this->calculator->restoredMeanMicros($p->result->calculation_snapshot));
                            if (! $restoredFormScores->containsStrict(null)) {
                                $restoredSum = $restoredFormScores->sum();
                                $restoredTotals[$entryId] = $aggregation === 'mean_two_forms' ? intdiv($restoredSum + 1, 2) : $restoredSum;
                            }
                        }
                    }
                    if ($round->id === $standingsRoundId) {
                        foreach ($bout->performances->groupBy('entry_id') as $entryId => $forms) {
                            $formScores[$entryId] = $forms->sortBy('form_number')->map(function ($p) {
                                return $p->result?->published_at ? $p->result->score : null;
                            })->values()->all();
                        }
                    }
                    if ($bout->status === 'completed') {
                        if ($bout->resolution_reason !== null && str_starts_with($bout->resolution_reason, 'انصراف')) {
                            foreach ($bout->entries as $entry) {
                                $withdrawn[$entry->id] = true;
                            }
                        }
                        if ($scoreBased && $round->id === $standingsRoundId && count($totals) === 1) {
                            $entryId = array_key_first($totals);
                            $scores[$entryId] = $this->calculator->micros($totals[$entryId]);
                            $presentationScores[$entryId] = $presentationTotals[$entryId] ?? null;
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
                        'entries' => $bout->entries->map(function ($entry) {
                            $firstAthlete = $entry->athletes->first();
                            $countryCode = $firstAthlete?->country_code ?? ($firstAthlete?->club && strlen(trim($firstAthlete->club)) <= 4 && preg_match('/^[A-Za-z]+$/', trim($firstAthlete->club)) ? strtoupper(trim($firstAthlete->club)) : null);
                            $shortName = $firstAthlete?->short_name ?? $entry->display_name;

                            return [
                                'id' => $entry->id,
                                'name' => $entry->display_name,
                                'short_name' => $shortName,
                                'country_code' => $countryCode,
                                'side' => $entry->pivot->side,
                            ];
                        })->values(),
                        'judges' => $operate ? $bout->judges->map(fn ($j) => ['id' => $j->id, 'user_id' => $j->user_id, 'name' => $j->user->name, 'seat' => $j->seat])->values() : [],
                        'totals' => $totals,
                        'performances' => $bout->performances->sortBy('id')->map(function ($p) use ($bout, $display, $operate, $ownJudge, $components, $revisions, $category, $lastEnded, $appeals) {
                            $own = ! $display && $ownJudge ? $p->scoreSheets->firstWhere('judge_assignment_id', $ownJudge->id) : null;
                            $ownValues = $own ? ($components->get($own->id, collect())->pluck('value_hundredths', 'criterion')->all()) : null;
                            $previewResult = null;
                            $previewCalc = null;
                            $submitted = $p->scoreSheets->where('status', 'submitted');
                            if ($p->status === 'scoring'
                                && $bout->judges->count() === $category->judge_count
                                && $submitted->pluck('judge_assignment_id')->sort()->values()->all() === $bout->judges->pluck('id')->sort()->values()->all()) {
                                $values = $submitted->map(fn ($sheet) => $components->get($sheet->id, collect())->pluck('value_hundredths', 'criterion')->map(fn ($value) => (int) $value)->all())->values()->all();
                                $previewCalc = $this->timing->applyPenalty($this->calculator->calculate($values, $category->scoringRuleSet->definition, $category->judge_count), $p);
                                if ($display) {
                                    $previewResult = $previewCalc['score'];
                                }
                            }

                            $calculationSnapshot = $p->result?->published_at ? $p->result->calculation_snapshot : $previewCalc;
                            $detailedScores = null;
                            if ($calculationSnapshot !== null) {
                                $judges = $bout->judges->sortBy('seat')->map(function ($judge) use ($submitted, $components) {
                                    $sheet = $submitted->firstWhere('judge_assignment_id', $judge->id);
                                    if (! $sheet) {
                                        return null;
                                    }
                                    $comp = $components->get($sheet->id, collect())->pluck('value_hundredths', 'criterion')->all();
                                    $breakdown = $sheet->breakdown ?? [];

                                    $accuracyComponents = isset($breakdown['accuracy_components_hundredths'])
                                        ? array_map(fn ($v) => number_format($v / 100, 2, '.', ''), $breakdown['accuracy_components_hundredths'])
                                        : [];
                                    $presentationComponents = isset($breakdown['presentation_components_hundredths'])
                                        ? array_map(fn ($v) => number_format($v / 100, 2, '.', ''), $breakdown['presentation_components_hundredths'])
                                        : [];

                                    $accuracyPenalties = isset($breakdown['accuracy_penalties_hundredths'])
                                        ? array_map(fn ($v) => number_format($v / 100, 2, '.', ''), $breakdown['accuracy_penalties_hundredths'])
                                        : [];
                                    $presentationPenalties = isset($breakdown['presentation_penalties_hundredths'])
                                        ? array_map(fn ($v) => number_format($v / 100, 2, '.', ''), $breakdown['presentation_penalties_hundredths'])
                                        : [];

                                    $accuracyHundredths = (int) ($comp['accuracy'] ?? ($breakdown['accuracy_hundredths'] ?? 0));
                                    $presentationHundredths = (int) ($comp['presentation'] ?? ($breakdown['presentation_hundredths'] ?? 0));

                                    return [
                                        'judge_id' => $judge->id,
                                        'seat' => $judge->seat,
                                        'label' => 'J'.$judge->seat,
                                        'name' => $judge->user?->name,
                                        'accuracy' => number_format($accuracyHundredths / 100, 2, '.', ''),
                                        'presentation' => number_format($presentationHundredths / 100, 2, '.', ''),
                                        'total' => number_format(($accuracyHundredths + $presentationHundredths) / 100, 2, '.', ''),
                                        'accuracy_components' => $accuracyComponents,
                                        'presentation_components' => $presentationComponents,
                                        'accuracy_penalties' => $accuracyPenalties,
                                        'presentation_penalties' => $presentationPenalties,
                                    ];
                                })->filter()->values()->all();

                                $accuracyMean = $calculationSnapshot['components']['accuracy']['mean'] ?? null;
                                $presentationMean = $calculationSnapshot['components']['presentation']['mean'] ?? null;
                                $totalScore = $calculationSnapshot['score'] ?? null;

                                $ruleDef = $category->scoringRuleSet->definition ?? [];
                                $accuracyMax = ($ruleDef['accuracy_max'] ?? 400) / 100;
                                $presentationMax = ($ruleDef['presentation_max'] ?? 600) / 100;

                                $detailedScores = [
                                    'discipline' => $category->discipline,
                                    'method' => $ruleDef['input_method'] ?? null,
                                    'judges' => $judges,
                                    'accuracy' => $accuracyMean !== null ? number_format((float) $accuracyMean, 2, '.', '') : null,
                                    'accuracy_max' => number_format($accuracyMax, 1, '.', ''),
                                    'presentation' => $presentationMean !== null ? number_format((float) $presentationMean, 2, '.', '') : null,
                                    'presentation_max' => number_format($presentationMax, 1, '.', ''),
                                    'total' => $totalScore !== null ? number_format((float) $totalScore, 2, '.', '') : null,
                                    'total_max' => number_format($accuracyMax + $presentationMax, 1, '.', ''),
                                ];
                            }

                            $formName = $p->form?->name ?? ($category->discipline === 'freestyle' ? 'اجرای ابداعی' : 'در انتظار قرعهٔ پومسه');
                            $formBadge = PoomsaeForm::numberFromName($p->form?->name) ?? $p->form_number;

                            return [
                                'id' => $p->id, 'entry_id' => $p->entry_id, 'form_number' => $p->form_number, 'form_name' => $formName, 'form_badge' => $formBadge, 'music_url' => $p->music_path && $operate ? route('operations.performance.music', [$category->tournament_id, $p->id]) : null, 'status' => $p->status, 'version' => $p->version,
                                'started_at' => isset($p->timing_snapshot['started_at_milliseconds']) ? CarbonImmutable::createFromTimestampMs($p->timing_snapshot['started_at_milliseconds'])->toISOString() : $p->started_at?->toISOString(),
                                'ended_at' => isset($p->timing_snapshot['ended_at_milliseconds']) ? CarbonImmutable::createFromTimestampMs($p->timing_snapshot['ended_at_milliseconds'])->toISOString() : $p->ended_at?->toISOString(),
                                'timing' => $p->timing_snapshot ?? $this->timing->rules($category),
                                'live_timing' => $this->timing->liveTimer($p, $category),
                                'server_now' => now()->toISOString(),
                                'rest_ready_at' => $p->status === 'pending' && $category->minimum_rest_seconds > 0 ? $lastEnded->get($p->entry_id)?->copy()->addSeconds($category->minimum_rest_seconds)->toISOString() : null,
                                'result' => $p->result?->published_at ? $p->result->score : null,
                                'appeal_status' => $appeals->get($p->id)?->status,
                                'appeal_pending' => in_array($appeals->get($p->id)?->status, CompetitionAppeal::OPEN_STATUSES, true),
                                'preview_result' => $previewResult,
                                'detailed_scores' => $detailedScores,
                                'submitted_count' => $p->scoreSheets->where('status', 'submitted')->count(),
                                'is_assigned' => ! $display && $ownJudge !== null,
                                'own_score' => $own ? ['revision' => $own->revision, 'values' => $ownValues, 'breakdown' => $own->breakdown] : null,
                                'scores' => $operate ? $p->scoreSheets->map(fn ($s) => [
                                    'id' => $s->id, 'judge_assignment_id' => $s->judge_assignment_id, 'seat' => $bout->judges->firstWhere('id', $s->judge_assignment_id)?->seat, 'revision' => $s->revision, 'status' => $s->status,
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
                $previous = $category->rounds->firstWhere('sequence', $round->sequence - 1);
                $advancementReview = $category->format === 'knockout' && $round->started_at !== null && $previous
                    && $previous->bouts->pluck('winner_entry_id')->sort()->values()->all() !== $round->bouts->flatMap(fn ($bout) => $bout->entries->pluck('id'))->sort()->values()->all();

                $totalStages = $category->planned_round_count ?? $category->rounds->count();
                $stageLabel = match (true) {
                    str_contains(mb_strtolower($round->name), 'نیمه') || str_contains(strtolower($round->name), 'semi') => 'SEMI-FINAL',
                    str_contains(mb_strtolower($round->name), 'مقدماتی') || str_contains(strtolower($round->name), 'prelim') => 'PRELIMINARY',
                    str_contains(mb_strtolower($round->name), 'نهایی') || str_contains(strtolower($round->name), 'final') => 'FINAL',
                    $totalStages === 3 && $round->sequence === 1 => 'PRELIMINARY',
                    $totalStages === 3 && $round->sequence === 2 => 'SEMI-FINAL',
                    $totalStages === 3 && $round->sequence === 3 => 'FINAL',
                    $totalStages === 2 && $round->sequence === 1 => 'SEMI-FINAL',
                    $totalStages === 2 && $round->sequence === 2 => 'FINAL',
                    $totalStages <= 1 => 'FINAL',
                    default => strtoupper($round->name),
                };

                return ['id' => $round->id, 'name' => $round->name, 'stage_label' => $stageLabel, 'sequence' => $round->sequence, 'status' => $round->status, 'bouts' => $bouts,
                    'score_correction_open' => in_array($tournament->status, ['running', 'completed'], true) && $round->sequence >= $lastStartedSequence,
                    'court_ids' => $bouts->pluck('court_id')->unique()->values(),
                    'scheduled' => $round->scheduled_at !== null || $bouts->isNotEmpty(), 'forms_ready' => $formsReady,
                    'form_ids' => $round->form_sequence ?? [], 'form_names' => collect($round->form_sequence ?? [])->map(fn ($id) => $category->forms->firstWhere('id', $id)?->name)->values(),
                    'forms_drawn_at' => $round->forms_drawn_at?->toISOString(), 'started_at' => $round->started_at?->toISOString(),
                    'previous_completed' => $round->sequence === 1 || $round->started_at !== null || ($previous?->status === 'completed' && $appeals->where('category_id', $category->id)->whereIn('status', CompetitionAppeal::OPEN_STATUSES)->isEmpty()),
                    'advancement_review_required' => (bool) $advancementReview,
                    'schedule_version' => $round->schedule_version, 'source_version' => $round->source_version,
                ];
            })->values();
            $last = $rounds->last();
            $completed = $last && $last['status'] === 'completed' && ($category->format === 'round_robin' || count($last['bouts']) === 1)
                && ($category->planned_round_count === null || $rounds->every(fn ($round) => $round['status'] === 'completed'));
            $standings = $category->entries->where('status', 'checked_in')->map(function ($e) use ($wins, $played, $scores, $presentationScores, $restoredScores, $withdrawn, $formScores) {
                $entryForms = $formScores[$e->id] ?? [];
                $firstAthlete = $e->athletes->first();
                $countryCode = $firstAthlete?->country_code ?? ($firstAthlete?->club && strlen(trim($firstAthlete->club)) <= 4 && preg_match('/^[A-Za-z]+$/', trim($firstAthlete->club)) ? strtoupper(trim($firstAthlete->club)) : null);
                $shortName = $firstAthlete?->short_name ?? $e->display_name;

                return [
                    'id' => $e->id,
                    'name' => $e->display_name,
                    'short_name' => $shortName,
                    'country_code' => $countryCode,
                    'wins' => $wins[$e->id] ?? 0,
                    'played' => $played[$e->id] ?? 0,
                    'score' => isset($scores[$e->id]) ? $this->calculator->format($scores[$e->id]) : null,
                    'score_micros' => $scores[$e->id] ?? null,
                    'presentation_score_micros' => $presentationScores[$e->id] ?? null,
                    'restored_score_micros' => $restoredScores[$e->id] ?? null,
                    'withdrawn' => ! empty($withdrawn[$e->id]),
                    'form_scores' => $entryForms,
                    'r1' => $entryForms[0] ?? null,
                    'r2' => $entryForms[1] ?? null,
                ];
            });
            $tiedScoreGroups = $scoreBased
                ? $standings->whereNotNull('score_micros')->groupBy('score_micros')->filter(fn ($rows) => $rows->count() > 1)->keys()->map(fn ($score) => (int) $score)->all()
                : [];
            $standings = $standings->sort(function ($left, $right) use ($scoreBased, $tiedScoreGroups): int {
                if (! $scoreBased) {
                    return $right['wins'] <=> $left['wins'];
                }
                if (($left['withdrawn'] ?? false) !== ($right['withdrawn'] ?? false)) {
                    return ($left['withdrawn'] ?? false) ? 1 : -1;
                }
                $primaryComparison = ($right['score_micros'] ?? PHP_INT_MIN) <=> ($left['score_micros'] ?? PHP_INT_MIN);
                if ($primaryComparison !== 0 || ! in_array($left['score_micros'], $tiedScoreGroups, true)) {
                    return $primaryComparison;
                }

                $presentationComparison = ($right['presentation_score_micros'] ?? PHP_INT_MIN) <=> ($left['presentation_score_micros'] ?? PHP_INT_MIN);
                if ($presentationComparison !== 0) {
                    return $presentationComparison;
                }

                return ($right['restored_score_micros'] ?? PHP_INT_MIN) <=> ($left['restored_score_micros'] ?? PHP_INT_MIN);
            })->values();
            $previousValue = null;
            $rank = 0;
            $standings = $standings->map(function ($row, $index) use (&$previousValue, &$rank, $scoreBased, $tiedScoreGroups) {
                $isTied = $scoreBased && in_array($row['score_micros'], $tiedScoreGroups, true);
                $value = $scoreBased ? [$row['score_micros'], $isTied ? $row['presentation_score_micros'] : null, $isTied ? $row['restored_score_micros'] : null] : $row['wins'];
                if ($previousValue !== $value || $index === 0) {
                    $rank = $index + 1;
                    $previousValue = $value;
                }

                $row['presentation_score'] = $isTied && $row['presentation_score_micros'] !== null ? $this->calculator->format($row['presentation_score_micros']) : null;
                $row['tie_break_score'] = $isTied && $row['restored_score_micros'] !== null ? $this->calculator->format($row['restored_score_micros']) : null;
                unset($row['score_micros']);
                unset($row['presentation_score_micros']);
                unset($row['restored_score_micros']);

                return [...$row, 'rank' => ($scoreBased && ($value[0] === null || ! empty($row['withdrawn']))) ? null : $rank];
            });

            return [
                'id' => $category->id, 'name' => $category->name,
                'display_label' => strtoupper(($category->gender === 'male' ? 'MALE' : ($category->gender === 'female' ? 'FEMALE' : $category->gender)).($category->maximum_age ? '-UNDER '.$category->maximum_age : ($category->minimum_age ? '-OVER '.$category->minimum_age : ''))),
                'gender' => $category->gender, 'minimum_age' => $category->minimum_age, 'maximum_age' => $category->maximum_age,
                'format' => $category->format, 'discipline' => $category->discipline, 'entry_type' => $category->entry_type, 'forms_per_round' => $category->forms_per_round, 'execution_mode' => $category->execution_mode, 'performance_order' => $category->performance_order, 'score_based' => $scoreBased, 'judge_count' => $category->judge_count, 'rules' => $category->scoringRuleSet?->definition, 'form_names' => $category->form_sequence ? collect($category->form_sequence)->map(fn ($id) => $category->forms->firstWhere('id', $id)?->name)->all() : [],
                'entries' => $category->entries->map(fn ($e) => [
                    'id' => $e->id,
                    'name' => $e->display_name,
                    'short_name' => $e->athletes->first()?->short_name ?? $e->display_name,
                    'country_code' => $e->athletes->first()?->country_code ?? ($e->athletes->first()?->club && strlen(trim($e->athletes->first()->club)) <= 4 && preg_match('/^[A-Za-z]+$/', trim($e->athletes->first()->club)) ? strtoupper(trim($e->athletes->first()->club)) : null),
                    'status' => $e->status,
                    'club' => $e->athletes->first()?->club,
                    'members' => $e->athletes->sortBy('pivot.position')->map(fn ($athlete) => [
                        'name' => $athlete->first_name.' '.$athlete->last_name,
                        'short_name' => $athlete->short_name,
                        'gender' => $athlete->gender,
                        'country_code' => $athlete->country_code,
                        ...($manage ? [
                            'id' => $athlete->id,
                            'first_name' => $athlete->first_name,
                            'last_name' => $athlete->last_name,
                            'birth_date' => $athlete->birth_date?->format('Y-m-d'),
                            'club' => $athlete->club,
                            'country_code' => $athlete->country_code,
                        ] : []),
                    ])->values(),
                ])->values(),
                'rounds' => $rounds, 'completed' => $completed, 'champion_id' => $completed && $category->format === 'knockout' ? $last['bouts'][0]['winner_entry_id'] : null,
                'planned_round_count' => $category->planned_round_count, 'allow_form_repetition' => $category->allow_form_repetition, 'form_draw_timing' => $category->form_draw_timing, 'form_draw_time' => $category->form_draw_time,
                'round_count_automatic' => $automaticStageCount,
                'open_appeal_count' => $appeals->where('category_id', $category->id)->whereIn('status', CompetitionAppeal::OPEN_STATUSES)->count(),
                'timing' => $this->timing->rules($category),
                'timing_editable' => $manage && ! in_array($tournament->status, ['completed', 'archived'], true) && ! $category->rounds->flatMap(fn ($r) => $r->bouts)->flatMap(fn ($b) => $b->performances)->contains(fn ($p) => $p->started_at !== null || $p->status !== 'pending'),
                'required_round_count' => $automaticStageCount && $checkedInCount >= 2 ? (int) ceil(log($checkedInCount, 2)) : null,
                'form_pool' => $category->forms->map(fn ($form) => ['id' => $form->id, 'name' => $form->name])->values(),
                'draw_available_at' => app(DrawRoundForms::class)->availableAt($tournament, $category)->toISOString(),
                'registration_locked' => $category->rounds->contains(fn ($round) => $round->bouts->isNotEmpty()),
                'settings_editable' => $manage && ! in_array($tournament->status, ['completed', 'archived'], true) && $category->management_source === null && ! $category->rounds->contains(fn ($round) => $round->bouts->isNotEmpty() || $round->forms_drawn_at !== null),
                'rule_version' => $category->scoringRuleSet?->version,
                'management_source' => $category->management_source, 'management_version' => $category->management_version, 'synced_at' => $category->synced_at?->toISOString(), 'standings_round_id' => $standingsRoundId,
                'standings' => $category->format === 'round_robin' ? $standings : [],
            ];
        })->values();

        return [
            'revision' => (int) AuditLog::where('tournament_id', $tournament->id)->max('id'),
            'id' => $tournament->id, 'name' => $tournament->name, 'venue' => $tournament->venue, 'starts_on' => $tournament->starts_on->format('Y-m-d'), 'ends_on' => $tournament->ends_on?->format('Y-m-d'), 'timezone' => $tournament->timezone, 'status' => $tournament->status,
            'dates_editable' => ! $tournament->categories->contains(fn ($category) => $category->rounds->contains(fn ($round) => $round->bouts->isNotEmpty() || $round->forms_drawn_at !== null)),
            'courts' => $tournament->courts->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->values(),
            'members' => $manage ? $tournament->users()->get(['users.id', 'users.name', 'users.email', 'users.is_active'])->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'role' => $u->pivot->role, 'is_active' => $u->is_active])->values() : [],
            'categories' => $categories,
        ];
    }
}
