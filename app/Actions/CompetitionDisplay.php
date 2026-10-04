<?php

namespace App\Actions;

use App\Models\Tournament;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CompetitionDisplay
{
    public function __construct(private CompetitionView $view) {}

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
            $courts = collect($snapshot['courts'])->map(function (array $court) use ($items, $decisions): array {
                $courtItems = collect($items)->where('court_id', $court['id']);
                $current = $courtItems->first(fn (array $item) => in_array($item['status'], ['running', 'scoring'], true))
                    ?? collect($decisions)->firstWhere('court_id', $court['id']);
                $upcoming = $courtItems->where('status', 'pending')->sortBy(fn (array $item) => sprintf('%d-%010d-%02d-%s', $item['bout_status'] === 'running' ? 0 : 1, $item['category_id'], $item['round_sequence'], $item['order']))->take(5)->values()->map(function (array $item) use ($current): array {
                    unset($item['order']);

                    return [...$item, 'ready' => $item['ready'] && $current === null];
                })->all();
                if ($current) {
                    unset($current['order']);
                }

                return [...$court, 'current' => $current, 'upcoming' => $upcoming];
            })->all();

            return ['tournament' => $snapshot, 'courts' => $courts, 'revision' => $revision, 'generated_at' => now()->toISOString()];
        });
    }
}
