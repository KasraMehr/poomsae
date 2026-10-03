<?php

namespace App\Actions;

use App\Models\Category;
use App\Models\CompetitionRound;
use App\Models\Draw;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Support\Str;

class ScheduleRound
{
    public function __construct(private CompetitionSetup $setup) {}

    public function handle(User $actor, Tournament $tournament, Category $category, array $data): void
    {
        $this->setup->locked($actor, $tournament, function (Tournament $tournament) use ($actor, $category, $data): void {
            $category = Category::with('scoringRuleSet')->findOrFail($category->id);
            abort_unless($category->tournament_id === $tournament->id, 404);
            $check = fn (bool $ok, string $message) => $this->setup->check($ok, $message);
            $check($category->management_source === null, 'برنامهٔ این رده از سیستم مدیریت مسابقات دریافت می‌شود.');
            $check(($category->discipline === 'recognized' && in_array($category->entry_type, ['individual', 'team'], true) || $category->discipline === 'freestyle' && in_array($category->entry_type, ['individual', 'pair'], true)) && in_array($category->execution_mode, ['alternating', 'simultaneous'], true), 'نوع برگزاری این رده معتبر نیست.');
            $check($category->format !== 'round_robin' || $category->execution_mode === 'alternating', 'اجرای همزمان فقط برای تک‌حذفی دوبل است.');
            $check($category->scoringRuleSet?->approved_at !== null && ($category->scoringRuleSet->definition['algorithm'] ?? null) === 'component_trimmed_mean_v1', 'تنظیمات محاسبهٔ این رده تأیید نشده است.');
            $forms = $category->form_sequence ?? [];
            $check($category->discipline === 'freestyle' || $category->planned_round_count !== null || count($forms) === 2 && $category->forms()->whereIn('poomsae_forms.id', $forms)->count() === 2, 'تعداد فرم‌های رده معتبر نیست.');
            $check($tournament->courts()->whereKey($data['court_id'])->exists(), 'زمین باید متعلق به همین مسابقه باشد.');
            $judgeIds = array_map('intval', $data['judge_ids']);
            $check(count(array_unique($judgeIds)) === $category->judge_count, 'تعداد داورهای متفاوت باید دقیقاً برابر تنظیمات رده باشد.');
            $check($tournament->users()->wherePivot('role', 'judge')->where('is_active', true)->whereIn('users.id', $judgeIds)->count() === $category->judge_count, 'تمام داورها باید عضو فعال همین مسابقه باشند.');
            $previous = $category->rounds()->whereHas('bouts')->orderByDesc('sequence')->first();
            if ($previous) {
                $check($category->format === 'knockout' || $category->planned_round_count !== null, 'جدول دورهای بدون برنامهٔ مرحله‌ای فقط یک بار ساخته می‌شود.');
                $bouts = $previous->bouts()->orderBy('sequence')->get();
                $check($previous->status === 'completed', 'مرحلهٔ قبل باید کامل شده باشد.');
                if ($category->format === 'knockout') {
                    $check($bouts->every(fn ($b) => $b->status === 'completed' && $b->winner_entry_id !== null), 'تمام رقابت‌های دور قبل باید برندهٔ قطعی داشته باشند.');
                    $check($bouts->count() > 1, 'فینال پایان یافته است.');
                    $ids = $bouts->pluck('winner_entry_id')->all();
                } else {
                    $ids = $bouts->flatMap(fn ($bout) => $bout->entries()->pluck('entries.id'))->all();
                }
                $seed = 'previous-round-'.$previous->id;
            } else {
                $check(! $category->entries()->where('status', 'registered')->exists(), 'حضور یا انصراف تمام ورزشکاران ثبت‌نام‌شده را مشخص کنید.');
                $ids = $category->entries()->where('status', 'checked_in')->orderBy('id')->pluck('id')->all();
                $check(count($ids) >= 2, 'حداقل دو ورودی حاضر لازم است.');
                $check(count($ids) <= ($category->format === 'round_robin' ? 16 : 64), 'حداکثر این نسخه: ۱۶ ورودی دورهای یا ۶۴ ورودی تک‌حذفی.');
                if ($category->discipline === 'freestyle') {
                    $check($category->entries()->whereIn('id', $ids)->whereNull('music_path')->doesntExist(), 'فایل موسیقی تمام ورودی‌های حاضر لازم است.');
                }
                $seed = bin2hex(random_bytes(16));
                usort($ids, fn ($a, $b) => strcmp(hash('sha256', $seed.':'.$a), hash('sha256', $seed.':'.$b)));
                if ($category->planned_round_count !== null && $category->format === 'knockout') {
                    $requiredRounds = (int) ceil(log(count($ids), 2));
                    $check($category->planned_round_count === $requiredRounds, 'تعداد مراحل تک‌حذفی باید با تعداد ورودی‌های حاضر سازگار باشد: '.$requiredRounds.' مرحله.');
                }
            }
            $sequence = ($previous?->sequence ?? 0) + 1;
            $check($category->planned_round_count === null || $sequence <= $category->planned_round_count, 'تمام مراحل برنامه‌ریزی‌شده ساخته شده‌اند.');
            $round = $category->planned_round_count === null
                ? $category->rounds()->create(['name' => 'دور '.$sequence, 'sequence' => $sequence, 'status' => 'pending', 'form_sequence' => $forms])
                : $category->rounds()->where('sequence', $sequence)->firstOrFail();
            $pairs = [];
            if ($category->format === 'round_robin') {
                foreach ($ids as $id) {
                    $pairs[] = [$id];
                }
            } else {
                $size = 2;
                while ($size < count($ids)) {
                    $size *= 2;
                }
                $byeCount = $size - count($ids);
                foreach (array_slice($ids, 0, $byeCount) as $id) {
                    $pairs[] = [$id];
                }
                foreach (array_chunk(array_slice($ids, $byeCount), 2) as $pair) {
                    $pairs[] = $pair;
                }
            }
            $this->createBouts($actor, $tournament, $category, $round, $pairs, (int) $data['court_id'], $judgeIds, $seed, $previous ? 'advance_in_order_v1' : 'sha256_seed_sort_v1');
        });
    }

    /** @param array<int, array<int, int>> $pairs
     * @param  array<int, int>  $judgeIds
     */
    public function createBouts(User $actor, Tournament $tournament, Category $category, CompetitionRound $round, array $pairs, int $courtId, array $judgeIds, string $seed, string $algorithm): void
    {
        $this->setup->check($tournament->courts()->whereKey($courtId)->exists(), 'زمین باید متعلق به همین مسابقه باشد.');
        $this->setup->check(count($judgeIds) === $category->judge_count && count(array_unique($judgeIds)) === $category->judge_count && $tournament->users()->wherePivot('role', 'judge')->where('is_active', true)->whereIn('users.id', $judgeIds)->count() === $category->judge_count, 'پنل داوری این مرحله معتبر نیست.');
        $forms = $category->discipline === 'freestyle' ? [null] : (($round->form_sequence ?: $category->form_sequence) ?: [null, null]);
        $ids = array_merge(...$pairs);
        $this->setup->check(count($ids) === count(array_unique($ids)) && $category->entries()->whereIn('id', $ids)->where('status', 'checked_in')->count() === count($ids), 'هر ورودی حاضر فقط یک بار در مرحله قرار می‌گیرد.');
        $draw = Draw::create(['category_id' => $category->id, 'competition_round_id' => $round->id, 'created_by' => $actor->id, 'kind' => 'bracket', 'algorithm_version' => $algorithm, 'random_seed' => $seed, 'input_snapshot' => ['entries' => $ids, 'form_ids' => $round->form_sequence ?? [], 'judge_ids' => $judgeIds], 'output_snapshot' => ['pairs' => $pairs]]);
        $musicPaths = $category->discipline === 'freestyle' ? $category->entries()->whereIn('id', $ids)->pluck('music_path', 'id') : collect();
        foreach ($pairs as $index => $pair) {
            $bye = $category->format === 'knockout' && count($pair) === 1;
            $bout = $round->bouts()->create(['category_id' => $category->id, 'court_id' => $courtId, 'sequence' => $index + 1, 'status' => $bye ? 'completed' : 'pending', 'winner_entry_id' => $bye ? $pair[0] : null, 'resolved_by' => $bye ? $actor->id : null, 'resolution_reason' => $bye ? 'استراحت در قرعه (bye)' : null]);
            foreach ($pair as $side => $entryId) {
                $bout->entries()->attach($entryId, ['category_id' => $category->id, 'side' => $side === 0 ? 'chung' : 'hong']);
                if (! $bye) {
                    foreach ($forms as $formIndex => $formId) {
                        $bout->performances()->create(['public_id' => (string) Str::uuid(), 'entry_id' => $entryId, 'poomsae_form_id' => $formId, 'draw_id' => $round->form_draw_id ?? $draw->id, 'form_number' => $formIndex + 1, 'music_path' => $musicPaths->get($entryId), 'status' => 'pending']);
                    }
                }
            }
            foreach ($judgeIds as $seat => $judgeId) {
                $bout->judges()->create(['user_id' => $judgeId, 'seat' => $seat + 1]);
            }
        }
        if ($tournament->status === 'draft') {
            $tournament->update(['status' => 'ready']);
        }
        $round->update(['scheduled_at' => now(), 'schedule_version' => $round->schedule_version + 1]);
        $this->setup->audit($actor, $tournament, 'round.scheduled', 'competition_round', $round->id, ['draw_id' => $draw->id, 'bout_count' => count($pairs), 'schedule_version' => $round->schedule_version]);
    }
}
