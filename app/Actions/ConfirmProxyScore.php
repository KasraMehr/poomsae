<?php

namespace App\Actions;

use App\Models\Bout;
use App\Models\Category;
use App\Models\Performance;
use App\Models\ScoreSheet;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ConfirmProxyScore
{
    public function __construct(private CompetitionSetup $setup) {}

    public function handle(User $actor, Tournament $tournament, Performance $performance, ScoreSheet $scoreSheet, int $expectedRevision): void
    {
        Gate::forUser($actor)->authorize('operate', $tournament);

        DB::transaction(function () use ($actor, $tournament, $performance, $scoreSheet, $expectedRevision): void {
            $tournament = Tournament::lockForUpdate()->findOrFail($tournament->id);
            $performance = Performance::lockForUpdate()->findOrFail($performance->id);
            $scoreSheet = ScoreSheet::lockForUpdate()->findOrFail($scoreSheet->id);
            $bout = Bout::findOrFail($performance->bout_id);
            $category = Category::findOrFail($bout->category_id);

            abort_unless($category->tournament_id === $tournament->id && $scoreSheet->performance_id === $performance->id, 404);
            abort_unless($scoreSheet->revision === $expectedRevision, 409, 'نمره در دستگاه دیگری تغییر کرده است؛ نسخهٔ تازه را دریافت کنید.');
            $this->setup->check($tournament->status === 'running' && $performance->status === 'scoring', 'تأیید نمرهٔ دستی فقط پیش از تأیید نهایی اجرا مجاز است.');
            $this->setup->check($scoreSheet->submission_mode === 'operator_proxy' && $scoreSheet->status === 'draft', 'این نمرهٔ دستی در انتظار تأیید نیست.');
            $this->setup->check($scoreSheet->submitted_by !== $actor->id, 'ثبت‌کنندهٔ نمرهٔ دستی نمی‌تواند تأییدکنندهٔ همان نمره باشد.');

            $scoreSheet->update(['status' => 'submitted', 'confirmed_by' => $actor->id, 'confirmed_at' => now()]);
            $this->setup->audit($actor, $tournament, 'score.proxy_confirmed', 'score_sheet', $scoreSheet->id, [
                'revision' => $scoreSheet->revision,
                'judge_assignment_id' => $scoreSheet->judge_assignment_id,
                'submitted_by' => $scoreSheet->submitted_by,
                'confirmed_by' => $actor->id,
            ]);
        }, 3);
    }
}
