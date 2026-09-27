<?php

namespace App\Actions;

use App\Models\Bout;
use App\Models\Category;
use App\Models\Performance;
use App\Models\Result;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

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
            abort_unless($category->tournament_id === $tournament->id, 404);
            abort_unless($performance->version === (int) $data['expected_version'], 409, 'وضعیت اجرا تغییر کرده؛ صفحه را تازه کنید.');
            $this->setup->check(in_array($tournament->status, ['ready', 'running']), 'مسابقه آماده یا در حال برگزاری نیست.');
            $this->setup->check(in_array($data['command'], ['start', 'finish', 'approve'], true), 'فرمان اجرا معتبر نیست.');
            $before = $performance->toArray();
            if ($data['command'] === 'start') {
                $this->setup->check($performance->status === 'pending', 'فقط اجرای در انتظار را می‌توان شروع کرد.');
                $judgeIds = $bout->judges()->orderBy('user_id')->lockForUpdate()->pluck('user_id');
                User::whereIn('id', $judgeIds)->orderBy('id')->lockForUpdate()->get();
                $this->setup->check($bout->court_id !== null && $tournament->courts()->whereKey($bout->court_id)->exists(), 'زمین معتبر لازم است.');
                $this->setup->check($bout->judges()->count() === $category->judge_count && $bout->judges()->whereHas('user', fn ($q) => $q->where('is_active', true))->count() === $category->judge_count, 'تمام داورهای پنل باید فعال باشند.');
                $blocked = Bout::where('court_id', $bout->court_id)->where(function ($q) use ($bout) {
                    $q->whereHas('performances', fn ($p) => $p->whereIn('status', ['running', 'scoring']))
                        ->orWhere(fn ($other) => $other->where('id', '!=', $bout->id)->where('status', 'running'));
                })->exists();
                $this->setup->check(! $blocked, 'زمین در اختیار اجرای دیگر یا رقابت تعیین‌تکلیف‌نشده است.');
                $busyJudge = Bout::where('id', '!=', $bout->id)->whereHas('performances', fn ($p) => $p->whereIn('status', ['running', 'scoring']))->whereHas('judges', fn ($j) => $j->whereIn('user_id', $judgeIds))->exists();
                $this->setup->check(! $busyJudge, 'یکی از داورها روی زمین دیگری مشغول داوری است.');
                $prior = $bout->performances()->where('entry_id', $performance->entry_id)->where('form_number', '<', $performance->form_number)->where('status', '!=', 'approved')->exists();
                $this->setup->check(! $prior, 'ابتدا فرم قبلی همین ورزشکار باید تأیید شود.');
                $performance->update(['status' => 'running', 'started_at' => now(), 'version' => $performance->version + 1]);
                $bout->update(['status' => 'running']);
                $bout->round->update(['status' => 'running']);
                $tournament->update(['status' => 'running']);
            } elseif ($data['command'] === 'finish') {
                $this->setup->check($performance->status === 'running', 'فقط اجرای در حال اجرا پایان می‌یابد.');
                $performance->update(['status' => 'scoring', 'ended_at' => now(), 'version' => $performance->version + 1]);
            } else {
                $this->setup->check($performance->status === 'scoring', 'فقط اجرای منتظر نمره قابل تأیید است.');
                $sheets = $performance->scoreSheets()->where('status', 'submitted')->get();
                $assigned = $bout->judges()->pluck('id')->sort()->values()->all();
                $this->setup->check($sheets->pluck('judge_assignment_id')->sort()->values()->all() === $assigned && count($assigned) === $category->judge_count, 'نمرهٔ تمام داوران تخصیص‌داده‌شده لازم است.');
                $scores = $sheets->map(fn ($sheet) => DB::table('score_components')->where('score_sheet_id', $sheet->id)->pluck('value_hundredths', 'criterion')->map(fn ($value) => (int) $value)->all())->all();
                $calculation = $this->calculator->calculate($scores, $category->scoringRuleSet->definition, $category->judge_count);
                $calculation['sheet_revisions'] = $sheets->pluck('revision', 'id')->all();
                Result::create(['performance_id' => $performance->id, 'scoring_rule_set_id' => $category->scoring_rule_set_id, 'score' => $calculation['score'], 'calculation_snapshot' => $calculation, 'published_at' => now()]);
                $performance->update(['status' => 'approved', 'approved_at' => now(), 'approved_by' => $actor->id, 'version' => $performance->version + 1]);
                $this->finalizeBout($actor, $tournament, $bout);
            }
            $this->setup->audit($actor, $tournament, 'performance.'.$data['command'], 'performance', $performance->id, $performance->fresh()->toArray(), $before);
        }, 3);
    }

    public function totals(Bout $bout): array
    {
        $performances = $bout->performances()->with('result')->get();
        $totals = [];
        foreach ($performances->groupBy('entry_id') as $entryId => $forms) {
            if ($forms->count() !== 2 || ! $forms->every(fn ($p) => $p->status === 'approved' && $p->result !== null)) {
                continue;
            }
            $sum = $forms->sum(fn ($p) => $this->calculator->micros($p->result->score));
            $totals[$entryId] = intdiv($sum + 1, 2);
        }

        return $totals;
    }

    private function restoredMeanTotals(Bout $bout): array
    {
        $totals = [];
        foreach ($bout->performances()->with('result')->get()->groupBy('entry_id') as $entryId => $forms) {
            if ($forms->count() !== 2 || ! $forms->every(fn ($performance) => $performance->status === 'approved' && $performance->result !== null)) {
                continue;
            }

            $restoredMeans = $forms->map(fn ($performance) => $this->calculator->restoredMeanMicros($performance->result->calculation_snapshot));
            if ($restoredMeans->containsStrict(null)) {
                return [];
            }

            $totals[$entryId] = intdiv($restoredMeans->sum() + 1, 2);
        }

        return $totals;
    }

    private function finalizeBout(User $actor, Tournament $tournament, Bout $bout): void
    {
        if ($bout->performances()->where('status', '!=', 'approved')->exists()) {
            return;
        }
        $totals = $this->totals($bout);
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
        $bout->update(['status' => 'completed', 'winner_entry_id' => $winnerEntryId, 'resolved_by' => $actor->id, 'resolution_reason' => $isPrimaryTie ? 'میانگین همهٔ نمره‌های داوران با بازگرداندن کمینه و بیشینه در دو فرم' : 'بالاترین میانگین دو فرم']);
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
                $this->setup->check($category->format !== 'knockout' || $round->bouts()->count() === 1, 'فینال تمام رده‌های تک‌حذفی باید برگزار شود.');
            }
            $tournament->update(['status' => 'completed']);
            $this->setup->audit($actor, $tournament, 'tournament.completed', 'tournament', $tournament->id);
        }, 3);
    }
}
