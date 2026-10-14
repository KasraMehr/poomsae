<?php

namespace App\Actions;

use App\Models\Category;
use App\Models\Performance;
use App\Models\Tournament;
use App\Models\User;
use Carbon\CarbonImmutable;

class CompetitionTiming
{
    public function __construct(private CompetitionSetup $setup, private CalculateScore $calculator) {}

    /** @param array{enabled:bool, minimum_duration_seconds?:int|null, maximum_duration_seconds?:int|null, minimum_rest_seconds:int, time_penalty_hundredths:int} $data */
    public function update(User $actor, Tournament $tournament, Category $category, array $data): void
    {
        $this->setup->locked($actor, $tournament, function (Tournament $tournament) use ($actor, $category, $data): void {
            $category = Category::lockForUpdate()->findOrFail($category->id);
            abort_unless($category->tournament_id === $tournament->id, 404);
            $this->setup->check(! Performance::whereHas('bout', fn ($query) => $query->where('category_id', $category->id))->where(fn ($query) => $query->whereNotNull('started_at')->orWhere('status', '!=', 'pending'))->exists(), 'تنظیمات زمان پس از شروع اولین اجرا قفل می‌شود.');
            $before = $category->only(['minimum_duration_seconds', 'maximum_duration_seconds', 'minimum_rest_seconds', 'time_penalty_hundredths']);
            $fields = [
                'minimum_duration_seconds' => $data['enabled'] ? ($data['minimum_duration_seconds'] ?? null) : null,
                'maximum_duration_seconds' => $data['enabled'] ? $data['maximum_duration_seconds'] : null,
                'minimum_rest_seconds' => $data['minimum_rest_seconds'],
                'time_penalty_hundredths' => $data['time_penalty_hundredths'],
            ];
            $category->update($fields);
            $this->setup->audit($actor, $tournament, 'category.timing_updated', 'category', $category->id, $fields, $before);
        });
    }

    /** @return array{minimum_duration_seconds:?int, maximum_duration_seconds:?int, minimum_rest_seconds:int, time_penalty_hundredths:int} */
    public function rules(Category $category): array
    {
        return $category->only(['minimum_duration_seconds', 'maximum_duration_seconds', 'minimum_rest_seconds', 'time_penalty_hundredths']);
    }

    public function readyAt(Performance $performance, Category $category): ?CarbonImmutable
    {
        $endedAt = Performance::where('entry_id', $performance->entry_id)->whereNotNull('ended_at')->get(['ended_at', 'timing_snapshot'])
            ->max(fn (Performance $previous): int => $previous->timing_snapshot['ended_at_milliseconds'] ?? $previous->ended_at->getTimestampMs());

        return $endedAt === null || $category->minimum_rest_seconds === 0 ? null : CarbonImmutable::createFromTimestampMs($endedAt)->addSeconds($category->minimum_rest_seconds);
    }

    public function checkRest(Performance $performance, Category $category): void
    {
        $readyAt = $this->readyAt($performance, $category);
        $remaining = $readyAt === null ? 0 : max(0, (int) ceil(now()->diffInSeconds($readyAt, false)));
        if ($remaining > 0) {
            $this->setup->check(false, 'استراحت '.$performance->entry()->value('display_name').' تمام نشده است؛ '.$remaining.' ثانیه باقی مانده.');
        }
    }

    /** @return array<string, int|string|null> */
    public function finish(Performance $performance, Category $category, CarbonImmutable $endedAt): array
    {
        $rules = $performance->timing_snapshot ?? $this->rules($category);
        $milliseconds = max(0, $endedAt->getTimestampMs() - ($rules['started_at_milliseconds'] ?? $performance->started_at->getTimestampMs()));
        $violation = $rules['minimum_duration_seconds'] !== null && $milliseconds < $rules['minimum_duration_seconds'] * 1000 ? 'too_short'
            : ($rules['maximum_duration_seconds'] !== null && $milliseconds > $rules['maximum_duration_seconds'] * 1000 ? 'too_long' : null);

        return [...$rules, 'ended_at_milliseconds' => $endedAt->getTimestampMs(), 'duration_milliseconds' => $milliseconds,
            'duration_seconds' => intdiv($milliseconds, 1000), 'violation' => $violation, 'penalty_hundredths' => $violation === null ? 0 : $rules['time_penalty_hundredths']];
    }

    /** @param array<string, mixed> $calculation
     * @return array<string, mixed>
     */
    public function applyPenalty(array $calculation, Performance $performance): array
    {
        $timing = $performance->timing_snapshot;
        $penalty = (int) ($timing['penalty_hundredths'] ?? 0) * 10000;

        return [...$calculation, 'gross_score' => $calculation['score'], 'timing' => $timing,
            'score' => $this->calculator->format(max(0, $this->calculator->micros($calculation['score']) - $penalty))];
    }
}
