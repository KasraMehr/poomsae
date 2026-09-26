<?php

namespace App\Actions;

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
                        $scoredSeats = $scores->pluck('seat')->filter()->sort()->values();

                        return [
                            ...$performance,
                            'category_id' => $category['id'],
                            'category_name' => $category['name'],
                            'round_id' => $round['id'],
                            'round_name' => $round['name'],
                            'bout_id' => $bout['id'],
                            'bout_sequence' => $bout['sequence'],
                            'bout_status' => $bout['status'],
                            'court_id' => $bout['court_id'],
                            'entry_name' => $entries->get($performance['entry_id'])['name'] ?? '—',
                            'side' => $entries->get($performance['entry_id'])['side'] ?? null,
                            'judge_count' => $category['judge_count'],
                            'judges' => $bout['judges'],
                            'submitted_seats' => $scoredSeats->all(),
                            'missing_seats' => $allSeats->diff($scoredSeats)->values()->all(),
                        ];
                    });
                })
            )
        )->values();

        $courts = collect($tournamentView['courts'])->map(function (array $court) use ($performances) {
            $courtPerformances = $performances->where('court_id', $court['id'])->values();

            return [
                ...$court,
                'active' => $courtPerformances->first(fn (array $performance) => in_array($performance['status'], ['running', 'scoring'], true)),
                'queue' => $courtPerformances
                    ->filter(fn (array $performance) => $performance['status'] === 'pending')
                    ->filter(fn (array $performance) => $this->isReadyForCourt($performance, $courtPerformances))
                    ->take(5)
                    ->values(),
            ];
        })->values();

        return [
            'tournament' => $tournamentView,
            'courts' => $courts,
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
        if ($performance['form_number'] === 1) {
            return true;
        }

        return $courtPerformances->contains(fn (array $candidate) => $candidate['bout_id'] === $performance['bout_id']
            && $candidate['entry_id'] === $performance['entry_id']
            && $candidate['form_number'] === $performance['form_number'] - 1
            && $candidate['status'] === 'approved'
        );
    }
}
