<?php

namespace App\Actions;

use App\Models\Category;
use App\Models\CompetitionAppeal;
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
            $category = Category::with('scoringRuleSet')->lockForUpdate()->findOrFail($category->id);
            abort_unless($category->tournament_id === $tournament->id, 404);
            $check = fn (bool $ok, string $message) => $this->setup->check($ok, $message);
            $check(in_array($category->management_source, [null, 'tkdhub'], true), 'برنامهٔ این رده از سیستم مدیریت مسابقات دریافت می‌شود.');
            $externalDraw = $category->management_source === 'tkdhub';
            $check(($category->discipline === 'recognized' && in_array($category->entry_type, ['individual', 'team'], true) || $category->discipline === 'freestyle' && in_array($category->entry_type, ['individual', 'pair'], true)) && in_array($category->execution_mode, ['alternating', 'simultaneous'], true), 'نوع برگزاری این رده معتبر نیست.');
            $check($category->format !== 'round_robin' || $category->execution_mode === 'alternating', 'اجرای همزمان فقط برای تک‌حذفی دوبل است.');
            $check($category->scoringRuleSet?->approved_at !== null && ($category->scoringRuleSet->definition['algorithm'] ?? null) === 'component_trimmed_mean_v1', 'تنظیمات محاسبهٔ این رده تأیید نشده است.');
            $forms = $category->form_sequence ?? [];
            $check($category->discipline === 'freestyle' || $category->planned_round_count !== null || count($forms) === 2 && $category->forms()->whereIn('poomsae_forms.id', $forms)->count() === 2, 'تعداد فرم‌های رده معتبر نیست.');
            $courtPanels = $this->courtPanels($tournament, $category, $data);
            $this->setup->prepareKnockoutStages($actor, $tournament, $category);
            $previous = $category->rounds()->whereHas('bouts')->orderByDesc('sequence')->first();
            if ($previous) {
                $check(! CompetitionAppeal::open()->where('category_id', $category->id)->exists(), 'پیش از ساخت مرحلهٔ بعد، اعتراض‌های باز این رده را تعیین تکلیف کنید.');
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
                $ids = $category->entries()->where('status', 'checked_in')->orderBy($externalDraw ? 'seed' : 'id')->pluck('id')->all();
                $check(count($ids) >= ($externalDraw ? 1 : 2), $externalDraw ? 'حداقل یک ورزشکار در قرعهٔ tkdhub لازم است.' : 'حداقل دو ورودی حاضر لازم است.');
                $seed = $externalDraw ? $category->management_hash : bin2hex(random_bytes(16));
                if (! $externalDraw) {
                    usort($ids, fn ($a, $b) => strcmp(hash('sha256', $seed.':'.$a), hash('sha256', $seed.':'.$b)));
                }
            }
            $sequence = ($previous?->sequence ?? 0) + 1;
            $check($category->planned_round_count === null || $sequence <= $category->planned_round_count, 'تمام مراحل برنامه‌ریزی‌شده ساخته شده‌اند.');
            $round = $category->planned_round_count === null
                ? $category->rounds()->create(['name' => 'مرحله '.$sequence, 'sequence' => $sequence, 'status' => 'pending', 'form_sequence' => $forms])
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
            $this->createBouts($actor, $tournament, $category, $round, $pairs, $courtPanels, $seed, $externalDraw ? 'tkdhub_draw_positions_v1' : ($previous ? 'advance_in_order_v1' : 'sha256_seed_sort_v1'));
        });
    }

    /**
     * @param  array{courts?: array<int, array{court_id: int, judge_ids: array<int, int>}>, court_id?: int, judge_ids?: array<int, int>}  $data
     * @return array<int, array{court_id: int, judge_ids: array<int, int>}>
     */
    public function courtPanels(Tournament $tournament, Category $category, array $data): array
    {
        $this->setup->check(! array_key_exists('courts', $data) || (! array_key_exists('court_id', $data) && ! array_key_exists('judge_ids', $data)), 'زمین‌ها را به یک روش مشخص کنید.');
        $courtPanels = $data['courts'] ?? [['court_id' => $data['court_id'] ?? 0, 'judge_ids' => $data['judge_ids'] ?? []]];
        $this->setup->check(count($courtPanels) >= 1 && count($courtPanels) <= 4, 'برای هر مرحله بین یک تا چهار زمین انتخاب کنید.');
        $courtPanels = array_values(array_map(fn (array $panel): array => ['court_id' => (int) $panel['court_id'], 'judge_ids' => array_map('intval', $panel['judge_ids'])], $courtPanels));
        $courtIds = array_column($courtPanels, 'court_id');
        $this->setup->check(count(array_unique($courtIds)) === count($courtIds), 'هر زمین فقط یک بار در برنامهٔ مرحله انتخاب شود.');
        $this->setup->check($tournament->courts()->whereIn('id', $courtIds)->count() === count($courtIds), 'زمین باید متعلق به همین مسابقه باشد.');
        $allJudgeIds = [];
        foreach ($courtPanels as $panel) {
            $judgeIds = $panel['judge_ids'];
            $this->setup->check(count($judgeIds) === $category->judge_count && count(array_unique($judgeIds)) === $category->judge_count, 'تعداد داورهای متفاوت هر زمین باید دقیقاً برابر تنظیمات رده باشد.');
            $this->setup->check($tournament->users()->wherePivot('role', 'judge')->where('is_active', true)->whereIn('users.id', $judgeIds)->count() === $category->judge_count, 'تمام داورها باید عضو فعال همین مسابقه باشند.');
            $this->setup->check(array_intersect($allJudgeIds, $judgeIds) === [], 'زمین‌های همزمان باید پنل داوری مستقل داشته باشند.');
            $allJudgeIds = [...$allJudgeIds, ...$judgeIds];
        }

        return $courtPanels;
    }

    /**
     * @param  array<int, array<int, int>>  $pairs
     * @param  array<int, array{court_id: int, judge_ids: array<int, int>}>  $courtPanels
     */
    public function createBouts(User $actor, Tournament $tournament, Category $category, CompetitionRound $round, array $pairs, array $courtPanels, string $seed, string $algorithm): void
    {
        $courtPanels = $this->courtPanels($tournament, $category, ['courts' => $courtPanels]);
        $this->setup->check($pairs !== [], 'حداقل یک رقابت برای برنامهٔ مرحله لازم است.');
        $activeCourtCount = min(count($courtPanels), count($pairs));
        $forms = $category->discipline === 'freestyle' ? [null] : (($round->form_sequence ?: $category->form_sequence) ?: [null, null]);
        $ids = array_merge(...$pairs);
        $this->setup->check(count($ids) === count(array_unique($ids)) && $category->entries()->whereIn('id', $ids)->where('status', 'checked_in')->count() === count($ids), 'هر ورودی حاضر فقط یک بار در مرحله قرار می‌گیرد.');
        $courtAssignments = array_map(fn (int $index): int => $courtPanels[intdiv($index * $activeCourtCount, count($pairs))]['court_id'], array_keys($pairs));
        $draw = Draw::create(['category_id' => $category->id, 'competition_round_id' => $round->id, 'created_by' => $actor->id, 'kind' => 'bracket', 'algorithm_version' => $algorithm, 'random_seed' => $seed, 'input_snapshot' => ['entries' => $ids, 'form_ids' => $round->form_sequence ?? [], 'judge_ids' => $courtPanels[0]['judge_ids'], 'courts' => $courtPanels], 'output_snapshot' => ['pairs' => $pairs, 'court_ids' => $courtAssignments]]);
        $musicPaths = $category->discipline === 'freestyle' ? $category->entries()->whereIn('id', $ids)->pluck('music_path', 'id') : collect();
        foreach ($pairs as $index => $pair) {
            $panel = $courtPanels[intdiv($index * $activeCourtCount, count($pairs))];
            $bye = $category->format === 'knockout' && count($pair) === 1;
            $bout = $round->bouts()->create(['category_id' => $category->id, 'court_id' => $panel['court_id'], 'sequence' => $index + 1, 'status' => $bye ? 'completed' : 'pending', 'winner_entry_id' => $bye ? $pair[0] : null, 'resolved_by' => $bye ? $actor->id : null, 'resolution_reason' => $bye ? 'استراحت در قرعه (bye)' : null]);
            foreach ($pair as $side => $entryId) {
                $bout->entries()->attach($entryId, ['category_id' => $category->id, 'side' => $side === 0 ? 'chung' : 'hong']);
                if (! $bye) {
                    foreach ($forms as $formIndex => $formId) {
                        $bout->performances()->create(['public_id' => (string) Str::uuid(), 'entry_id' => $entryId, 'poomsae_form_id' => $formId, 'draw_id' => $round->form_draw_id ?? $draw->id, 'form_number' => $formIndex + 1, 'music_path' => $musicPaths->get($entryId), 'status' => 'pending']);
                    }
                }
            }
            foreach ($panel['judge_ids'] as $seat => $judgeId) {
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
