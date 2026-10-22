<?php

namespace App\Actions;

use App\Models\Bout;
use App\Models\Category;
use App\Models\Performance;
use App\Models\Tournament;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

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

    /**
     * @return array{
     *     status: string,
     *     started_at: ?string,
     *     duration_seconds: int,
     *     minimum_duration_seconds: ?int,
     *     maximum_duration_seconds: ?int,
     *     target_end_at: ?string,
     *     elapsed_seconds: int,
     *     elapsed_formatted: string,
     *     remaining_seconds: int,
     *     remaining_formatted: string,
     *     is_overtime: bool
     * }
     */
    public function liveTimer(Performance $performance, Category $category, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $rules = $performance->timing_snapshot ?? $this->rules($category);
        $maxDuration = $rules['maximum_duration_seconds'] ?? ($category->discipline === 'freestyle' ? 100 : 90);
        $minDuration = $rules['minimum_duration_seconds'] ?? ($category->discipline === 'freestyle' ? 90 : null);
        $startedAtMs = $rules['started_at_milliseconds'] ?? $performance->started_at?->getTimestampMs();

        if ($startedAtMs === null) {
            return [
                'status' => $performance->status,
                'started_at' => null,
                'duration_seconds' => $maxDuration,
                'minimum_duration_seconds' => $minDuration,
                'maximum_duration_seconds' => $rules['maximum_duration_seconds'] ?? null,
                'target_end_at' => null,
                'elapsed_seconds' => 0,
                'elapsed_formatted' => '00:00',
                'remaining_seconds' => $maxDuration,
                'remaining_formatted' => sprintf('%02d:%02d', intdiv($maxDuration, 60), $maxDuration % 60),
                'is_overtime' => false,
            ];
        }

        $endMs = $performance->status === 'running'
            ? $now->getTimestampMs()
            : ($rules['ended_at_milliseconds'] ?? $performance->ended_at?->getTimestampMs() ?? $now->getTimestampMs());

        $elapsedMs = max(0, $endMs - $startedAtMs);
        $elapsedSeconds = intdiv($elapsedMs, 1000);
        $remainingSeconds = max(0, $maxDuration - $elapsedSeconds);
        $isOvertime = $rules['maximum_duration_seconds'] !== null ? $elapsedSeconds > $rules['maximum_duration_seconds'] : false;

        $targetEndAt = CarbonImmutable::createFromTimestampMs($startedAtMs)->addSeconds($maxDuration)->toISOString();

        return [
            'status' => $performance->status,
            'started_at' => CarbonImmutable::createFromTimestampMs($startedAtMs)->toISOString(),
            'duration_seconds' => $maxDuration,
            'minimum_duration_seconds' => $minDuration,
            'maximum_duration_seconds' => $rules['maximum_duration_seconds'] ?? null,
            'target_end_at' => $targetEndAt,
            'elapsed_seconds' => $elapsedSeconds,
            'elapsed_formatted' => sprintf('%02d:%02d', intdiv($elapsedSeconds, 60), $elapsedSeconds % 60),
            'remaining_seconds' => $remainingSeconds,
            'remaining_formatted' => sprintf('%02d:%02d', intdiv($remainingSeconds, 60), $remainingSeconds % 60),
            'is_overtime' => $isOvertime,
        ];
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

    /** @param array{penalty_hundredths:int, violation?:?string, reason:string, expected_version:int} $data */
    public function adjustPerformancePenalty(User $actor, Tournament $tournament, Performance $performance, array $data, RunCompetition $run): void
    {
        Gate::forUser($actor)->authorize('operate', $tournament);
        DB::transaction(function () use ($actor, $tournament, $performance, $data, $run): void {
            $tournament = Tournament::lockForUpdate()->findOrFail($tournament->id);
            $performance = Performance::lockForUpdate()->findOrFail($performance->id);
            $bout = Bout::lockForUpdate()->findOrFail($performance->bout_id);
            $category = Category::lockForUpdate()->findOrFail($bout->category_id);
            abort_unless($category->tournament_id === $tournament->id, 404);
            abort_unless($performance->version === (int) $data['expected_version'], 409, 'وضعیت اجرا تغییر کرده؛ صفحه را تازه کنید.');
            $this->setup->check(in_array($performance->status, ['scoring', 'approved'], true), 'جریمهٔ زمان فقط پس از ثبت زمان پایان اجرا قابل ویرایش است.');

            $penaltyHundredths = (int) $data['penalty_hundredths'];
            $timing = $performance->timing_snapshot ?? $this->rules($category);
            $violation = $data['violation'] ?? ($penaltyHundredths === 0 ? ($timing['violation'] ? 'waived' : null) : ($timing['violation'] ?? 'too_long'));

            $newTiming = [
                ...$timing,
                'penalty_hundredths' => $penaltyHundredths,
                'violation' => $violation,
                'adjusted_by' => $actor->id,
                'adjusted_at' => now()->toIso8601String(),
                'adjustment_reason' => trim($data['reason']),
                'original_penalty_hundredths' => $timing['original_penalty_hundredths'] ?? ($timing['penalty_hundredths'] ?? 0),
            ];

            $before = $performance->toArray();
            $performance->update([
                'timing_snapshot' => $newTiming,
                'version' => $performance->version + 1,
            ]);

            $this->setup->audit($actor, $tournament, 'performance.timing_penalty_adjusted', 'performance', $performance->id, [
                'timing_snapshot' => $newTiming,
                'reason' => trim($data['reason']),
            ], $before);

            if ($performance->status === 'approved' && $performance->result !== null) {
                $run->recalculateApprovedResult($actor, $tournament, $performance, trim($data['reason']));
            }
        }, 3);
    }
}
