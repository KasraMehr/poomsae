<?php

namespace App\Actions;

use App\Models\Tournament;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CompetitionDisplay
{
    public function __construct(private CompetitionView $view, private CalculateScore $calculator) {}

    public function revision(Tournament $tournament): int
    {
        return (int) DB::table('audit_logs')->where('tournament_id', $tournament->id)->max('id');
    }

    /** @return array<string, mixed> */
    public function snapshot(Tournament $tournament, User $actor): array
    {
        return DB::transaction(function () use ($tournament, $actor): array {
            $tournament = Tournament::findOrFail($tournament->id);
            $revision = $this->revision($tournament);
            $snapshot = $this->view->snapshot($tournament, $actor, true);
            $snapshot['categories'] = collect($snapshot['categories'])->map(fn (array $category): array => [
                ...$category,
                'stage' => collect($category['rounds'])->last()['stage_label'] ?? null,
                'placements' => $this->placements($category),
                'rounds' => collect($category['rounds'])->map(fn (array $round): array => [
                    ...$round,
                    'bouts' => collect($round['bouts'])->map(fn (array $bout): array => [
                        ...$bout,
                        'component_totals' => $this->componentTotals($category, $bout),
                    ])->values(),
                ])->values(),
            ])->values();
            $snapshot['current_result'] = $this->currentResult($snapshot['categories']);
            $items = [];
            $decisions = [];
            foreach ($snapshot['categories'] as $category) {
                foreach ($category['rounds'] as $round) {
                    foreach ($round['bouts'] as $bout) {
                        $groups = collect($bout['performances'])->groupBy(fn (array $performance) => $category['format'] === 'knockout' && $category['execution_mode'] === 'simultaneous' ? $performance['form_number'] : $performance['id']);
                        foreach ($groups as $performances) {
                            $performance = $performances->first();
                            $entries = collect($bout['entries'])->whereIn('id', $performances->pluck('entry_id'))->values()->all();
                            $active = $performances->contains(fn (array $item) => in_array($item['status'], ['running', 'scoring'], true));
                            if (! $active && ! $performances->every(fn (array $item) => $item['status'] === 'pending')) {
                                continue;
                            }
                            $items[] = [
                                'id' => $performance['id'], 'court_id' => $bout['court_id'],
                                'category_id' => $category['id'], 'category_name' => $category['name'], 'format' => $category['format'],
                                'round_id' => $round['id'], 'round_name' => $round['name'], 'round_sequence' => $round['sequence'], 'round_status' => $round['status'],
                                'bout_id' => $bout['id'], 'bout_sequence' => $bout['sequence'], 'bout_status' => $bout['status'],
                                'form_number' => $performance['form_number'], 'form_name' => $performance['form_name'],
                                'entries' => $entries, 'performances' => $performances->values()->all(),
                                'status' => $active ? ($performances->contains('status', 'running') ? 'running' : 'scoring') : 'pending',
                                'ready' => $round['forms_ready'] && $round['previous_completed'],
                                'order' => $category['format'] === 'round_robin' && $category['performance_order'] === 'phased'
                                    ? sprintf('%02d-%06d-%010d', $performance['form_number'], $bout['sequence'], $performance['id'])
                                    : sprintf('%06d-%02d-%010d', $bout['sequence'], $performance['form_number'], $performance['id']),
                            ];
                        }
                        if ($bout['status'] === 'running' && collect($bout['performances'])->isNotEmpty() && collect($bout['performances'])->every(fn (array $performance) => $performance['status'] === 'approved')) {
                            $decisions[] = ['id' => 'decision-'.$bout['id'], 'court_id' => $bout['court_id'], 'category_id' => $category['id'],
                                'category_name' => $category['name'], 'round_name' => $round['name'], 'round_sequence' => $round['sequence'], 'round_status' => $round['status'],
                                'bout_id' => $bout['id'], 'bout_sequence' => $bout['sequence'], 'format' => $category['format'], 'entries' => collect($bout['entries'])->all(),
                                'status' => 'decision', 'form_name' => 'تعیین نتیجهٔ رقابت', 'performances' => [], 'ready' => false];
                        }
                    }
                }
            }
            $courts = collect($snapshot['courts'])->map(function (array $court) use ($items, $decisions, $snapshot): array {
                $courtItems = collect($items)->where('court_id', $court['id']);
                $previousBout = collect($snapshot['categories'])->flatMap(fn (array $category) => collect($category['rounds'])->flatMap(fn (array $round) => $round['bouts']))
                    ->filter(fn (array $bout): bool => $bout['court_id'] === $court['id'] && $bout['status'] === 'completed')
                    ->sortBy(fn (array $bout): string => collect($bout['performances'])->max('result_published_at').sprintf('-%010d', $bout['id']))->last();
                $current = $courtItems->first(fn (array $item) => in_array($item['status'], ['running', 'scoring'], true))
                    ?? collect($decisions)->firstWhere('court_id', $court['id']);
                $upcoming = $courtItems->where('status', 'pending')->sortBy(fn (array $item) => sprintf('%d-%010d-%02d-%s', $item['format'] === 'knockout' && $item['bout_status'] === 'running' ? 0 : 1, $item['category_id'], $item['round_sequence'], $item['order']))->take(5)->values()->map(function (array $item) use ($current): array {
                    unset($item['order']);

                    return [...$item, 'ready' => $item['ready'] && $current === null];
                })->all();
                if ($current) {
                    unset($current['order']);
                    $runningPerf = collect($current['performances'] ?? [])->firstWhere('status', 'running');
                    if ($runningPerf) {
                        $current['live_timing'] = $runningPerf['live_timing'] ?? null;
                        $current['form_badge'] = $runningPerf['form_badge'] ?? $current['form_number'];
                    }
                }

                return [...$court, 'current' => $current, 'upcoming' => $upcoming, 'previous_bout_id' => $previousBout['id'] ?? null];
            })->all();

            return ['tournament' => $snapshot, 'courts' => $courts, 'revision' => $revision, 'generated_at' => now()->toISOString()];
        });
    }

    /** @param array<string, mixed> $category
     * @return array<int, array<string, mixed>>
     */
    private function placements(array $category): array
    {
        if ($category['format'] === 'round_robin') {
            return collect($category['standings'])->filter(fn (array $row): bool => $row['rank'] !== null)
                ->map(fn (array $row): array => [
                    'rank' => $row['rank'], 'entry_id' => $row['id'], 'name' => $row['name'],
                    'score' => $row['score'], 'round_scores' => [$row['r1'] ?? null, $row['r2'] ?? null],
                    'country_code' => $row['country_code'] ?? null,
                ])->values()->all();
        }

        if (! $category['completed']) {
            return [];
        }

        $rounds = collect($category['rounds'])->sortBy('sequence')->values();
        $finalRound = $rounds->last();
        $finalBout = collect($finalRound['bouts'] ?? [])->first();
        if (! $finalBout || $finalBout['status'] !== 'completed' || ! $finalBout['winner_entry_id']) {
            return [];
        }

        $entries = collect($category['entries'])->keyBy('id');
        $placements = [];
        $placements[] = $this->placement($entries->get($finalBout['winner_entry_id']), $finalBout, 1);
        foreach ($rounds as $round) {
            $rank = 2 ** ($finalRound['sequence'] - $round['sequence']) + 1;
            foreach ($round['bouts'] as $bout) {
                if ($bout['status'] !== 'completed' || ! $bout['winner_entry_id']) {
                    continue;
                }
                foreach ($bout['entries'] as $entry) {
                    if ($entry['id'] !== $bout['winner_entry_id']) {
                        $placements[] = $this->placement($entries->get($entry['id']), $bout, $rank);
                    }
                }
            }
        }

        return collect($placements)->filter()->sortBy('rank')->values()->all();
    }

    /** @param array<string, mixed>|null $entry
     * @param  array<string, mixed>  $bout
     * @return array<string, mixed>|null
     */
    private function placement(?array $entry, array $bout, int $rank): ?array
    {
        if ($entry === null) {
            return null;
        }

        return [
            'rank' => $rank, 'entry_id' => $entry['id'], 'name' => $entry['name'],
            'score' => $bout['totals'][$entry['id']] ?? null,
            'round_scores' => collect($bout['performances'])->where('entry_id', $entry['id'])->sortBy('form_number')->pluck('result')->values()->all(),
            'country_code' => $entry['country_code'] ?? null,
        ];
    }

    /** @param array<string, mixed> $category
     * @param  array<string, mixed>  $bout
     * @return array<int, array<string, string>>
     */
    private function componentTotals(array $category, array $bout): array
    {
        $components = [];
        foreach ($bout['entries'] as $entry) {
            $performances = collect($bout['performances'])->where('entry_id', $entry['id']);
            if ($performances->count() !== $category['forms_per_round'] || $performances->contains(fn (array $performance): bool => $performance['result'] === null || $performance['detailed_scores'] === null || $performance['detailed_scores']['accuracy_exact'] === null || $performance['detailed_scores']['presentation_exact'] === null)) {
                continue;
            }

            $accuracy = $performances->sum(fn (array $performance): int => $this->calculator->micros($performance['detailed_scores']['accuracy_exact']));
            $presentation = $performances->sum(fn (array $performance): int => $this->calculator->micros($performance['detailed_scores']['presentation_exact']));
            $mean = ($category['rules']['aggregation'] ?? null) === 'mean_two_forms';
            $accuracy = $mean ? intdiv($accuracy + 1, $performances->count()) : $accuracy;
            $presentation = $mean ? intdiv($presentation + 1, $performances->count()) : $presentation;
            $multiplier = $mean ? 1 : $performances->count();

            $components[$entry['id']] = [
                'accuracy' => $this->calculator->format($accuracy),
                'presentation' => $this->calculator->format($presentation),
                'accuracy_max' => $this->calculator->format(($category['rules']['accuracy_max'] ?? 400) * $multiplier * 10000),
                'presentation_max' => $this->calculator->format(($category['rules']['presentation_max'] ?? 600) * $multiplier * 10000),
                'total_max' => $this->calculator->format(10 * $multiplier * 1000000),
            ];
        }

        return $components;
    }

    /** @param Collection<int, array<string, mixed>> $categories
     * @return array<string, mixed>|null
     */
    private function currentResult(Collection $categories): ?array
    {
        $results = $categories->flatMap(fn (array $category) => collect($category['rounds'])->flatMap(
            fn (array $round) => collect($round['bouts'])->filter(fn (array $bout): bool => $bout['status'] === 'completed' && $bout['winner_entry_id'] !== null && count($bout['entries']) === 2)
                ->map(fn (array $bout): array => ['category' => $category, 'bout' => $bout])
        ));
        $latest = $results->sortBy(fn (array $item): string => collect($item['bout']['performances'])->max('result_published_at').sprintf('-%010d', $item['bout']['id']))->last();
        if ($latest === null) {
            return null;
        }

        $category = $latest['category'];
        $bout = $latest['bout'];

        return [
            'status' => 'completed', 'category_id' => $category['id'], 'round_count' => 1,
            'bout_ids' => [$bout['id']], 'winner_entry_id' => $bout['winner_entry_id'],
            'winner_total' => $bout['totals'][$bout['winner_entry_id']] ?? null,
            'total_max' => ($category['rules']['aggregation'] ?? null) === 'sum_two_forms' ? 10 * $category['forms_per_round'] : 10,
        ];
    }
}
