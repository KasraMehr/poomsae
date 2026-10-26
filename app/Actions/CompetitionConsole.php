<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Support\Collection;

class CompetitionConsole
{
    public function __construct(private CompetitionView $view) {}

    /** @return array<string, mixed> */
    public function snapshot(Tournament $tournament, User $actor): array
    {
        $tournamentView = $this->view->snapshot($tournament, $actor);
        $performances = collect($tournamentView['categories'])->flatMap(
            fn (array $category) => collect($category['rounds'])->flatMap(
                fn (array $round) => collect($round['bouts'])->flatMap(function (array $bout) use ($category, $round) {
                    $entries = collect($bout['entries'])->keyBy('id');

                    return collect($bout['performances'])->map(function (array $performance) use ($category, $round, $bout, $entries) {
                        $scores = collect($performance['scores']);
                        $allSeats = collect($bout['judges'])->pluck('seat')->sort()->values();
                        $receivedSeats = $scores->pluck('seat')->filter()->sort()->values();
                        $submittedSeats = $scores->where('status', 'submitted')->pluck('seat')->filter()->sort()->values();
                        $pendingReviewSeats = $scores->where('status', 'draft')->pluck('seat')->filter()->sort()->values();

                        return [
                            ...$performance,
                            'category_id' => $category['id'],
                            'category_name' => $category['name'],
                            'forms_per_round' => $category['forms_per_round'],
                            'discipline' => $category['discipline'],
                            'format' => $category['format'],
                            'execution_mode' => $category['execution_mode'],
                            'performance_order' => $category['performance_order'],
                            'score_based' => $category['score_based'],
                            'round_id' => $round['id'],
                            'round_name' => $round['name'],
                            'forms_ready' => $round['forms_ready'],
                            'previous_completed' => $round['previous_completed'],
                            'bout_id' => $bout['id'],
                            'bout_sequence' => $bout['sequence'],
                            'bout_status' => $bout['status'],
                            'court_id' => $bout['court_id'],
                            'entry_name' => $entries->get($performance['entry_id'])['name'] ?? '—',
                            'paired_entry_name' => $category['format'] === 'knockout' && $category['execution_mode'] === 'simultaneous'
                                ? ($entries->first(fn (array $entry) => $entry['id'] !== $performance['entry_id'])['name'] ?? null)
                                : null,
                            'side' => $entries->get($performance['entry_id'])['side'] ?? null,
                            'judge_count' => $category['judge_count'],
                            'judges' => $bout['judges'],
                            'received_seats' => $receivedSeats->all(),
                            'submitted_seats' => $submittedSeats->all(),
                            'pending_review_seats' => $pendingReviewSeats->all(),
                            'missing_seats' => $allSeats->diff($receivedSeats)->values()->all(),
                        ];
                    });
                })
            )
        )->values();

        $performances = $performances->map(function (array $performance) use ($performances): array {
            if ($performance['status'] === 'pending' && $performance['format'] === 'knockout' && $performance['execution_mode'] === 'simultaneous') {
                $performance['rest_ready_at'] = $performances->where('bout_id', $performance['bout_id'])->where('form_number', $performance['form_number'])->max('rest_ready_at');
            }

            return $performance;
        });
        $courts = collect($tournamentView['courts'])->map(function (array $court) use ($performances, $tournamentView) {
            $courtPerformances = $performances->where('court_id', $court['id'])->values();
            $matches = collect($tournamentView['categories'])->flatMap(fn (array $category) => collect($category['rounds'])->flatMap(
                fn (array $round) => collect($round['bouts'])
                    ->filter(fn (array $bout) => $bout['court_id'] === $court['id'])
                    ->map(fn (array $bout) => [
                        'id' => $bout['id'],
                        'category_id' => $category['id'],
                        'category_name' => $category['name'],
                        'round_id' => $round['id'],
                        'round_name' => $round['name'],
                        'sequence' => $bout['sequence'],
                        'status' => $bout['status'],
                        'entries' => $bout['entries'],
                        'winner_entry_id' => $bout['winner_entry_id'],
                        'totals' => $bout['totals'],
                        'performances' => collect($bout['performances'])->map(fn (array $performance) => [
                            'id' => $performance['id'],
                            'entry_id' => $performance['entry_id'],
                            'form_number' => $performance['form_number'],
                            'form_name' => $performance['form_name'],
                            'status' => $performance['status'],
                            'result' => $performance['result'],
                        ])->values(),
                    ])
            ))->values();

            return [
                ...$court,
                'matches' => $matches,
                'active' => $courtPerformances->first(fn (array $performance) => in_array($performance['status'], ['running', 'scoring'], true)),
                'queue' => $courtPerformances
                    ->filter(fn (array $performance) => $performance['status'] === 'pending')
                    ->filter(fn (array $performance) => $performance['forms_ready'] && $performance['previous_completed'])
                    ->filter(fn (array $performance) => $this->isReadyForCourt($performance, $courtPerformances))
                    ->sortBy(fn (array $performance) => sprintf('%010d-%010d-%02d-%010d', $performance['category_id'], $performance['round_id'], $performance['form_number'], $performance['bout_sequence']))
                    ->take(5)
                    ->values(),
            ];
        })->values();

        return [
            'tournament' => $tournamentView,
            'courts' => $courts,
            'recent_events' => AuditLog::query()->with('user:id,name')
                ->where('tournament_id', $tournament->id)
                ->latest('id')->limit(20)->get()
                ->map(fn (AuditLog $log) => [
                    'id' => $log->id,
                    'action' => $log->action,
                    'label' => CompetitionReports::ACTION_LABELS[$log->action] ?? $log->action,
                    'subject_type' => $log->subject_type,
                    'subject_id' => $log->subject_id,
                    'actor_name' => $log->user?->name,
                    'occurred_at' => $log->created_at?->toISOString(),
                ])->values(),
            'attention' => [
                'running' => $performances->where('status', 'running')->count(),
                'waiting_for_scores' => $performances->where('status', 'scoring')->count(),
                'ready' => $courts->sum(fn (array $court) => count($court['queue'])),
            ],
        ];
    }

    /** @param Collection<int, array<string, mixed>> $courtPerformances */
    private function isReadyForCourt(array $performance, Collection $courtPerformances): bool
    {
        $runningKnockout = $courtPerformances->first(fn (array $candidate): bool => $candidate['format'] === 'knockout' && $candidate['bout_status'] === 'running');
        if ($runningKnockout !== null && $performance['bout_id'] !== $runningKnockout['bout_id']) {
            return false;
        }

        $scope = $courtPerformances->filter(fn (array $candidate) => $performance['score_based']
            ? $candidate['round_id'] === $performance['round_id']
            : $candidate['bout_id'] === $performance['bout_id']);
        $ordered = $scope->sortBy(function (array $candidate) use ($performance): string {
            if ($performance['score_based'] && $performance['performance_order'] === 'phased') {
                return sprintf('%02d-%06d-%010d', $candidate['form_number'], $candidate['bout_sequence'], $candidate['id']);
            }

            return $performance['score_based']
                ? sprintf('%06d-%02d-%010d', $candidate['bout_sequence'], $candidate['form_number'], $candidate['id'])
                : sprintf('%02d-%010d', $candidate['form_number'], $candidate['id']);
        });
        $next = $ordered->first(fn (array $candidate) => ! in_array($candidate['status'], ['approved', 'cancelled'], true));

        return $next && $next['id'] === $performance['id'];
    }
}
