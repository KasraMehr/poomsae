<?php

namespace App\Actions;

use App\Models\Bout;
use App\Models\Category;
use App\Models\CompetitionRound;
use App\Models\Performance;
use App\Models\Result;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class RunCompetition
{
    public function __construct(private CompetitionSetup $setup, private CalculateScore $calculator) {}

    public function command(User $actor, Tournament $tournament, Performance $performance, array $data): void
    {
        Gate::forUser($actor)->authorize('operate', $tournament);
        DB::transaction(function () use ($actor, $tournament, $performance, $data): void {
            $tournament = Tournament::lockForUpdate()->findOrFail($tournament->id);
            $performance = Performance::lockForUpdate()->findOrFail($performance->id);
            $bout = Bout::lockForUpdate()->findOrFail($performance->bout_id);
            $category = Category::lockForUpdate()->findOrFail($bout->category_id);
            $round = CompetitionRound::lockForUpdate()->findOrFail($bout->competition_round_id);
            abort_unless($category->tournament_id === $tournament->id, 404);
            abort_unless($performance->version === (int) $data['expected_version'], 409, 'وضعیت اجرا تغییر کرده؛ صفحه را تازه کنید.');
            $this->setup->check(in_array($tournament->status, ['ready', 'running']), 'مسابقه آماده یا در حال برگزاری نیست.');
            $this->setup->check(in_array($data['command'], ['start', 'finish', 'approve'], true), 'فرمان اجرا معتبر نیست.');
            $before = $performance->toArray();
            if ($data['command'] === 'start') {
                $this->setup->check($performance->status === 'pending', 'فقط اجرای در انتظار را می‌توان شروع کرد.');
                $this->setup->check($category->discipline === 'freestyle' || $performance->poomsae_form_id !== null && ($category->planned_round_count === null || $round->forms_drawn_at !== null && count($round->form_sequence ?? []) === 2), 'در انتظار قرعهٔ پومسه؛ قبل از تعیین فرم‌های مرحله، اجرا شروع نمی‌شود.');
                $this->setup->check($category->discipline !== 'freestyle' || $performance->music_path !== null, 'فایل موسیقی اجرای ابداعی لازم است.');
                if ($round->sequence > 1) {
                    $previousRound = $category->rounds()->where('sequence', $round->sequence - 1)->first();
                    $this->setup->check($previousRound && ($previousRound->status === 'completed' || $round->started_at !== null), 'مرحلهٔ قبل باید قبل از شروع این مرحله کامل شود.');
                    if ($category->format === 'knockout') {
                        $expectedEntries = $previousRound->bouts()->pluck('winner_entry_id')->sort()->values()->all();
                        $actualEntries = $round->bouts()->with('entries')->get()->flatMap(fn ($item) => $item->entries->pluck('id'))->sort()->values()->all();
                        $this->setup->check($expectedEntries === $actualEntries || $round->started_at !== null, 'جدول مرحلهٔ جدید باید دقیقاً شامل برندگان مرحلهٔ قبل باشد.');
                    }
                }
                $judgeIds = $bout->judges()->orderBy('user_id')->lockForUpdate()->pluck('user_id');
                User::whereIn('id', $judgeIds)->orderBy('id')->lockForUpdate()->get();
                $this->setup->check($bout->court_id !== null && $tournament->courts()->whereKey($bout->court_id)->exists(), 'زمین معتبر لازم است.');
                $this->setup->check($bout->judges()->count() === $category->judge_count && $bout->judges()->whereHas('user', fn ($q) => $q->where('is_active', true))->count() === $category->judge_count, 'تمام داورهای پنل باید فعال باشند.');
                $blocked = Bout::where('court_id', $bout->court_id)->where(function ($q) use ($bout) {
                    $q->whereHas('performances', fn ($p) => $p->whereIn('status', ['running', 'scoring']))
                        ->orWhere(fn ($other) => $other->where('id', '!=', $bout->id)->where('status', 'running')->whereHas('category', fn ($query) => $query->where('format', 'knockout')));
                })->exists();
                $this->setup->check(! $blocked, 'زمین در اختیار اجرای دیگر یا رقابت تعیین‌تکلیف‌نشده است.');
                $busyJudge = Bout::where('id', '!=', $bout->id)->whereHas('performances', fn ($p) => $p->whereIn('status', ['running', 'scoring']))->whereHas('judges', fn ($j) => $j->whereIn('user_id', $judgeIds))->exists();
                $this->setup->check(! $busyJudge, 'یکی از داورها روی زمین دیگری مشغول داوری است.');
                $this->checkPerformanceOrder($category, $bout, $performance);
                $simultaneous = $category->format === 'knockout' && $category->execution_mode === 'simultaneous';
                $group = $simultaneous
                    ? $bout->performances()->where('form_number', $performance->form_number)->lockForUpdate()->get()
                    : collect([$performance]);
                $this->setup->check($group->count() === ($simultaneous ? 2 : 1) && $group->every(fn ($member) => $member->status === 'pending'), 'هر دو ورزشکار باید برای اجرای همزمان آماده باشند.');
                foreach ($group as $member) {
                    $memberBefore = $member->toArray();
                    $member->update(['status' => 'running', 'started_at' => now(), 'version' => $member->version + 1]);
                    if ($member->id !== $performance->id) {
                        $this->setup->audit($actor, $tournament, 'performance.start', 'performance', $member->id, $member->fresh()->toArray(), $memberBefore);
                    }
                }
                $bout->update(['status' => 'running']);
                $round->update(['status' => 'running', 'started_at' => $round->started_at ?? now()]);
                $tournament->update(['status' => 'running']);
            } elseif ($data['command'] === 'finish') {
                $this->setup->check($performance->status === 'running', 'فقط اجرای در حال اجرا پایان می‌یابد.');
                $simultaneous = $category->format === 'knockout' && $category->execution_mode === 'simultaneous';
                $group = $simultaneous
                    ? $bout->performances()->where('form_number', $performance->form_number)->lockForUpdate()->get()
                    : collect([$performance]);
                $this->setup->check($group->count() === ($simultaneous ? 2 : 1) && $group->every(fn ($member) => $member->status === 'running'), 'اجرای همزمان دو ورزشکار باید با هم پایان یابد.');
                foreach ($group as $member) {
                    $memberBefore = $member->toArray();
                    $member->update(['status' => 'scoring', 'ended_at' => now(), 'version' => $member->version + 1]);
                    if ($member->id !== $performance->id) {
                        $this->setup->audit($actor, $tournament, 'performance.finish', 'performance', $member->id, $member->fresh()->toArray(), $memberBefore);
                    }
                }
            } else {
                $this->setup->check($performance->status === 'scoring', 'فقط اجرای منتظر نمره قابل تأیید است.');
                $this->publishResult($performance, $bout, $category);
                $performance->update(['status' => 'approved', 'approved_at' => now(), 'approved_by' => $actor->id, 'version' => $performance->version + 1]);
                $this->finalizeBout($actor, $tournament, $bout);
            }
            $this->setup->audit($actor, $tournament, 'performance.'.$data['command'], 'performance', $performance->id, $performance->fresh()->toArray(), $before);
        }, 3);
    }

    private function publishResult(Performance $performance, Bout $bout, Category $category): Result
    {
        $sheets = $performance->scoreSheets()->where('status', 'submitted')->get();
        $assigned = $bout->judges()->pluck('id')->sort()->values()->all();
        $this->setup->check($sheets->pluck('judge_assignment_id')->sort()->values()->all() === $assigned && count($assigned) === $category->judge_count, 'نمرهٔ تمام داوران تخصیص‌داده‌شده لازم است.');
        $scores = $sheets->map(fn ($sheet) => DB::table('score_components')->where('score_sheet_id', $sheet->id)->pluck('value_hundredths', 'criterion')->map(fn ($value) => (int) $value)->all())->all();
        $calculation = $this->calculator->calculate($scores, $category->scoringRuleSet->definition, $category->judge_count);
        $calculation['sheet_revisions'] = $sheets->pluck('revision', 'id')->all();

        return Result::updateOrCreate(['performance_id' => $performance->id], ['scoring_rule_set_id' => $category->scoring_rule_set_id, 'score' => $calculation['score'], 'calculation_snapshot' => $calculation, 'published_at' => now()]);
    }

    public function recalculateApprovedResult(User $actor, Tournament $tournament, Performance $performance, string $reason): void
    {
        $bout = Bout::with('category.scoringRuleSet', 'round')->lockForUpdate()->findOrFail($performance->bout_id);
        $this->setup->check($performance->status === 'approved' && $performance->result !== null, 'فقط نتیجهٔ تأییدشده قابل اصلاح است.');
        $before = $performance->result->toArray();
        $boutBefore = $bout->toArray();
        $result = $this->publishResult($performance, $bout, $bout->category);
        $performance->update(['version' => $performance->version + 1, 'approved_by' => $actor->id, 'approved_at' => now()]);
        if (! $bout->performances()->where('status', '!=', 'approved')->exists()) {
            $bout->update(['status' => 'running', 'winner_entry_id' => null, 'resolved_by' => null, 'resolution_reason' => null]);
            $bout->round->update(['status' => 'running']);
            $this->finalizeBout($actor, $tournament, $bout);
            $this->synchronizeAdvancement($actor, $tournament, $bout->fresh(), $reason, $boutBefore['winner_entry_id']);
            if ($bout->fresh()->status !== 'completed' && $tournament->status === 'completed') {
                $tournament->update(['status' => 'running']);
            }
        }
        $this->setup->audit($actor, $tournament, 'result.corrected', 'result', $result->id, [...$result->toArray(), 'reason' => $reason], $before);
        $this->setup->audit($actor, $tournament, 'bout.result_corrected', 'bout', $bout->id, [...$bout->fresh()->toArray(), 'reason' => $reason], $boutBefore);
    }

    private function synchronizeAdvancement(User $actor, Tournament $tournament, Bout $bout, string $reason, ?int $previousWinner = null): void
    {
        if ($bout->category->format !== 'knockout' || $bout->winner_entry_id === null || $previousWinner === $bout->winner_entry_id) {
            return;
        }
        $nextRound = $bout->category->rounds()->where('sequence', $bout->round->sequence + 1)->first();
        if (! $nextRound || $nextRound->started_at !== null) {
            return;
        }
        $losers = $previousWinner !== null ? [$previousWinner] : $bout->entries()->where('entries.id', '!=', $bout->winner_entry_id)->pluck('entries.id')->all();
        $nextBouts = $nextRound->bouts()->whereHas('entries', fn ($query) => $query->whereIn('entries.id', $losers))->get();
        foreach ($nextBouts as $nextBout) {
            $this->setup->check(! $nextBout->performances()->where(fn ($query) => $query->where('status', '!=', 'pending')->orWhereHas('scoreSheets'))->exists(), 'جدول مرحلهٔ شروع‌شده قابل جایگزینی خودکار نیست.');
            $before = $nextBout->entries()->pluck('entries.id')->all();
            $oldEntry = $nextBout->entries()->whereIn('entries.id', $losers)->firstOrFail();
            $pending = $nextBout->performances()->where('entry_id', $oldEntry->id)->get();
            foreach ($pending as $nextPerformance) {
                $nextPerformance->delete();
            }
            $nextBout->entries()->detach($oldEntry->id);
            $nextBout->entries()->attach($bout->winner_entry_id, ['category_id' => $bout->category_id, 'side' => $oldEntry->pivot->side]);
            $musicPath = $bout->category->entries()->whereKey($bout->winner_entry_id)->value('music_path');
            foreach ($pending as $nextPerformance) {
                $nextBout->performances()->create([
                    'public_id' => (string) Str::uuid(), 'entry_id' => $bout->winner_entry_id,
                    'poomsae_form_id' => $nextPerformance->poomsae_form_id, 'draw_id' => $nextPerformance->draw_id,
                    'form_number' => $nextPerformance->form_number, 'music_path' => $musicPath,
                    'status' => 'pending', 'version' => $nextPerformance->version + 1,
                ]);
            }
            if ($nextBout->winner_entry_id === $oldEntry->id) {
                $nextBout->update(['winner_entry_id' => $bout->winner_entry_id]);
                $this->synchronizeAdvancement($actor, $tournament, $nextBout, $reason, $oldEntry->id);
            }
            $nextRound->update(['schedule_version' => $nextRound->schedule_version + 1]);
            $this->setup->audit($actor, $tournament, 'bout.advancement_corrected', 'bout', $nextBout->id, ['entry_ids' => $nextBout->entries()->pluck('entries.id')->all(), 'reason' => $reason], ['entry_ids' => $before]);
        }
    }

    public function totals(Bout $bout): array
    {
        $performances = $bout->performances()->with('result')->get();
        $totals = [];
        foreach ($performances->groupBy('entry_id') as $entryId => $forms) {
            if ($forms->count() !== $bout->category->forms_per_round || ! $forms->every(fn ($p) => $p->status === 'approved' && $p->result !== null)) {
                continue;
            }
            $sum = $forms->sum(fn ($p) => $this->calculator->micros($p->result->score));
            $totals[$entryId] = ($bout->category->scoringRuleSet->definition['aggregation'] ?? 'mean_two_forms') === 'mean_two_forms'
                ? intdiv($sum + 1, 2)
                : $sum;
        }

        return $totals;
    }

    private function checkPerformanceOrder(Category $category, Bout $bout, Performance $performance): void
    {
        $soloRound = $category->format === 'round_robin' && $bout->entries()->count() === 1;
        $ordered = $soloRound
            ? $bout->round->bouts()->with('performances')->orderBy('sequence')->get()->flatMap(fn ($roundBout) => $roundBout->performances->map(fn ($item) => ['performance' => $item, 'bout_sequence' => $roundBout->sequence]))
            : $bout->performances()->get()->map(fn ($item) => ['performance' => $item, 'bout_sequence' => $bout->sequence]);
        $ordered = $ordered->sortBy(function (array $item) use ($soloRound, $category): string {
            $form = $item['performance']->form_number;
            $boutSequence = $item['bout_sequence'];

            return $soloRound && $category->performance_order === 'phased'
                ? sprintf('%02d-%06d-%010d', $form, $boutSequence, $item['performance']->id)
                : ($soloRound
                    ? sprintf('%06d-%02d-%010d', $boutSequence, $form, $item['performance']->id)
                    : sprintf('%02d-%010d', $form, $item['performance']->id));
        });
        $next = $ordered->first(fn (array $item) => $item['performance']->status !== 'approved');
        $simultaneous = $category->format === 'knockout' && $category->execution_mode === 'simultaneous';
        $inNextGroup = $simultaneous && $next && $next['performance']->form_number === $performance->form_number;
        $this->setup->check($next && ($next['performance']->id === $performance->id || $inNextGroup), 'ترتیب اجرای فرم‌ها رعایت نشده است؛ ابتدا نتیجهٔ اجرای قبلی را تأیید کنید.');
    }

    private function restoredMeanTotals(Bout $bout): array
    {
        $totals = [];
        foreach ($bout->performances()->with('result')->get()->groupBy('entry_id') as $entryId => $forms) {
            if ($forms->count() !== $bout->category->forms_per_round || ! $forms->every(fn ($performance) => $performance->status === 'approved' && $performance->result !== null)) {
                continue;
            }

            $restoredMeans = $forms->map(fn ($performance) => $this->calculator->restoredMeanMicros($performance->result->calculation_snapshot));
            if ($restoredMeans->containsStrict(null)) {
                return [];
            }

            $sum = $restoredMeans->sum();
            $totals[$entryId] = ($bout->category->scoringRuleSet->definition['aggregation'] ?? 'mean_two_forms') === 'mean_two_forms'
                ? intdiv($sum + 1, 2)
                : $sum;
        }

        return $totals;
    }

    private function finalizeBout(User $actor, Tournament $tournament, Bout $bout): void
    {
        if ($bout->performances()->where('status', '!=', 'approved')->exists()) {
            return;
        }
        $totals = $this->totals($bout);
        if ($bout->category->format === 'round_robin' && $bout->entries()->count() === 1 && count($totals) === 1) {
            $bout->update(['status' => 'completed']);
            $this->completeRound($bout);

            return;
        }
        if (count($totals) !== 2) {
            return;
        }

        $isPrimaryTie = count(array_unique($totals)) === 1;
        $comparison = $isPrimaryTie ? $this->restoredMeanTotals($bout) : $totals;
        if (count($comparison) !== 2 || count(array_unique($comparison)) === 1) {
            return;
        }

        arsort($comparison, SORT_NUMERIC);
        $winnerEntryId = array_key_first($comparison);
        $bout->update(['status' => 'completed', 'winner_entry_id' => $winnerEntryId, 'resolved_by' => $actor->id, 'resolution_reason' => $isPrimaryTie ? 'میانگین همهٔ نمره‌های داوران با بازگرداندن کمینه و بیشینه' : 'بالاترین نتیجه']);
        $this->completeRound($bout);
        if ($isPrimaryTie) {
            $this->setup->audit($actor, $tournament, 'bout.tie_break_resolved', 'bout', $bout->id, [
                'winner_entry_id' => $winnerEntryId,
                'primary_totals_micros' => $totals,
                'restored_mean_totals_micros' => $comparison,
            ]);
        }
    }

    private function completeRound(Bout $bout): void
    {
        $round = $bout->round()->firstOrFail();
        if (! $round->bouts()->where('status', '!=', 'completed')->exists()) {
            $round->update(['status' => 'completed']);
        }
    }

    public function resolve(User $actor, Tournament $tournament, Bout $bout, array $data): void
    {
        Gate::forUser($actor)->authorize('operate', $tournament);
        DB::transaction(function () use ($actor, $tournament, $bout, $data): void {
            $tournament = Tournament::lockForUpdate()->findOrFail($tournament->id);
            $bout = Bout::with('category')->findOrFail($bout->id);
            abort_unless($bout->category->tournament_id === $tournament->id, 404);
            $decisionType = $data['decision_type'] ?? 'tie';
            $this->setup->check(in_array($decisionType, ['tie', 'walkover'], true), 'نوع تصمیم رقابت معتبر نیست.');
            $walkover = $decisionType === 'walkover';
            $this->setup->check($bout->entries()->count() === 2, 'تعیین برنده فقط برای رقابت دو نفره است.');
            $this->setup->check(in_array($tournament->status, ['ready', 'running']) && in_array($bout->status, ['pending', 'running']), 'رقابت برای تعیین برنده باز نیست.');
            $this->setup->check($bout->entries()->where('entries.id', $data['winner_entry_id'])->exists(), 'برنده باید یکی از طرفین همین رقابت باشد.');
            if ($walkover) {
                $this->setup->check(filter_var($data['confirmed'] ?? false, FILTER_VALIDATE_BOOLEAN), 'لغو اجراهای باقی‌مانده را تأیید کنید.');
                $this->setup->check($bout->performances()->where('status', '!=', 'approved')->exists(), 'برای رقابت با تمام نتایج تأییدشده از تعیین تساوی استفاده کنید.');
                foreach ($bout->performances()->where('status', '!=', 'approved')->get() as $performance) {
                    $performance->update(['status' => 'cancelled', 'version' => $performance->version + 1, 'ended_at' => $performance->ended_at ?? now()]);
                }
                $data['reason'] = 'انصراف / عدم حضور: '.$data['reason'];
                $tournament->update(['status' => 'running']);
            } else {
                $totals = $this->totals($bout);
                $this->setup->check(count($totals) === 2 && count(array_unique($totals)) === 1, 'فقط تساوی دو نتیجهٔ نهایی با دلیل قابل تعیین برنده است.');
                $this->setup->check(array_key_exists((int) $data['winner_entry_id'], $totals), 'برنده باید یکی از طرفین همین رقابت باشد.');
                $restoredMeanTotals = $this->restoredMeanTotals($bout);
                $this->setup->check(count($restoredMeanTotals) !== 2 || count(array_unique($restoredMeanTotals)) === 1, 'میانگین همهٔ نمره‌های داوران برنده را تعیین می‌کند؛ تصمیم دستی مجاز نیست.');
            }
            $bout->update(['status' => 'completed', 'winner_entry_id' => $data['winner_entry_id'], 'resolved_by' => $actor->id, 'resolution_reason' => $data['reason']]);
            $this->completeRound($bout);
            if (! $walkover) {
                $this->synchronizeAdvancement($actor, $tournament, $bout, $data['reason']);
            }
            $this->setup->audit($actor, $tournament, $walkover ? 'bout.walkover' : 'bout.tie_resolved', 'bout', $bout->id, $data);
        }, 3);
    }

    public function complete(User $actor, Tournament $tournament): void
    {
        Gate::forUser($actor)->authorize('update', $tournament);
        DB::transaction(function () use ($actor, $tournament): void {
            $tournament = Tournament::lockForUpdate()->findOrFail($tournament->id);
            $this->setup->check($tournament->status === 'running', 'مسابقه در حال برگزاری نیست.');
            $categories = $tournament->categories()->get();
            $this->setup->check($categories->isNotEmpty(), 'مسابقه رده ندارد.');
            foreach ($categories as $category) {
                $round = $category->rounds()->orderByDesc('sequence')->first();
                $this->setup->check($round && $round->status === 'completed', 'همهٔ رده‌ها باید دور کامل‌شده داشته باشند.');
                $this->setup->check($category->planned_round_count === null || $round->sequence === $category->planned_round_count && ! $category->rounds()->where('status', '!=', 'completed')->exists(), 'تمام مراحل از پیش تعیین‌شده باید کامل شوند.');
                $this->setup->check($category->format !== 'knockout' || $round->bouts()->count() === 1, 'فینال تمام رده‌های تک‌حذفی باید برگزار شود.');
            }
            $tournament->update(['status' => 'completed']);
            $this->setup->audit($actor, $tournament, 'tournament.completed', 'tournament', $tournament->id);
        }, 3);
    }
}
