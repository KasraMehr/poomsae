<?php

namespace App\Actions;

use App\Models\Category;
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
            $check($category->discipline === 'recognized' && $category->entry_type === 'individual' && in_array($category->execution_mode, ['alternating', 'simultaneous'], true), 'این گردش برای استاندارد انفرادی است.');
            $check($category->format !== 'round_robin' || $category->execution_mode === 'alternating', 'اجرای همزمان فقط برای تک‌حذفی دوبل است.');
            $check($category->scoringRuleSet?->approved_at !== null && ($category->scoringRuleSet->definition['algorithm'] ?? null) === 'component_trimmed_mean_v1', 'تنظیمات محاسبهٔ این رده تأیید نشده است.');
            $forms = $category->form_sequence ?? [];
            $check(count($forms) === 2 && $category->forms()->whereIn('poomsae_forms.id', $forms)->count() === 2, 'دو فرم معتبر برای رده تعیین کنید.');
            $check($tournament->courts()->whereKey($data['court_id'])->exists(), 'زمین باید متعلق به همین مسابقه باشد.');
            $judgeIds = array_map('intval', $data['judge_ids']);
            $check(count(array_unique($judgeIds)) === $category->judge_count, 'تعداد داورهای متفاوت باید دقیقاً برابر تنظیمات رده باشد.');
            $check($tournament->users()->wherePivot('role', 'judge')->where('is_active', true)->whereIn('users.id', $judgeIds)->count() === $category->judge_count, 'تمام داورها باید عضو فعال همین مسابقه باشند.');
            $previous = $category->rounds()->orderByDesc('sequence')->first();
            if ($previous) {
                $check($category->format === 'knockout', 'جدول دورهای فقط یک بار ساخته می‌شود.');
                $bouts = $previous->bouts()->orderBy('sequence')->get();
                $check($bouts->every(fn ($b) => $b->status === 'completed' && $b->winner_entry_id !== null), 'تمام رقابت‌های دور قبل باید برندهٔ قطعی داشته باشند.');
                $check($bouts->count() > 1, 'فینال پایان یافته است.');
                $ids = $bouts->pluck('winner_entry_id')->all();
                $seed = 'previous-round-'.$previous->id;
            } else {
                $check(! $category->entries()->where('status', 'registered')->exists(), 'حضور یا انصراف تمام ورزشکاران ثبت‌نام‌شده را مشخص کنید.');
                $ids = $category->entries()->where('status', 'checked_in')->orderBy('id')->pluck('id')->all();
                $check(count($ids) >= 2, 'حداقل دو ورزشکار حاضر لازم است.');
                $check(count($ids) <= ($category->format === 'round_robin' ? 16 : 64), 'حداکثر این نسخه: ۱۶ نفر دورهای یا ۶۴ نفر تک‌حذفی.');
                $seed = bin2hex(random_bytes(16));
                usort($ids, fn ($a, $b) => strcmp(hash('sha256', $seed.':'.$a), hash('sha256', $seed.':'.$b)));
            }
            $round = $category->rounds()->create(['name' => 'دور '.(($previous?->sequence ?? 0) + 1), 'sequence' => ($previous?->sequence ?? 0) + 1, 'status' => 'pending']);
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
            $draw = Draw::create(['category_id' => $category->id, 'competition_round_id' => $round->id, 'created_by' => $actor->id, 'kind' => 'bracket', 'algorithm_version' => $previous ? 'advance_in_order_v1' : 'sha256_seed_sort_v1', 'random_seed' => $seed, 'input_snapshot' => ['entries' => $ids, 'form_ids' => $forms, 'judge_ids' => $judgeIds], 'output_snapshot' => ['pairs' => $pairs]]);
            foreach ($pairs as $index => $pair) {
                $bye = $category->format === 'knockout' && count($pair) === 1;
                $bout = $round->bouts()->create(['category_id' => $category->id, 'court_id' => $data['court_id'], 'sequence' => $index + 1, 'status' => $bye ? 'completed' : 'pending', 'winner_entry_id' => $bye ? $pair[0] : null, 'resolved_by' => $bye ? $actor->id : null, 'resolution_reason' => $bye ? 'استراحت در قرعه (bye)' : null]);
                foreach ($pair as $side => $entryId) {
                    $bout->entries()->attach($entryId, ['category_id' => $category->id, 'side' => $side === 0 ? 'chung' : 'hong']);
                    if (! $bye) {
                        foreach ($forms as $formIndex => $formId) {
                            $bout->performances()->create(['public_id' => (string) Str::uuid(), 'entry_id' => $entryId, 'poomsae_form_id' => $formId, 'draw_id' => $draw->id, 'form_number' => $formIndex + 1, 'status' => 'pending']);
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
            $this->setup->audit($actor, $tournament, 'round.scheduled', 'competition_round', $round->id, ['draw_id' => $draw->id, 'bout_count' => count($pairs)]);
        });
    }
}
