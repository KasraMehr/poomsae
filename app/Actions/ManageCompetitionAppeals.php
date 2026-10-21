<?php

namespace App\Actions;

use App\Models\CompetitionAppeal;
use App\Models\CompetitionRound;
use App\Models\Performance;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class ManageCompetitionAppeals
{
    public function __construct(private CompetitionSetup $setup, private SubmitScore $submit, private CalculateScore $calculator, private CompetitionTiming $timing) {}

    /** @param array<string,mixed> $data */
    public function file(User $actor, Tournament $tournament, array $data): CompetitionAppeal
    {
        Gate::forUser($actor)->authorize('operate', $tournament);

        return DB::transaction(function () use ($actor, $tournament, $data): CompetitionAppeal {
            $tournament = Tournament::lockForUpdate()->findOrFail($tournament->id);
            $performance = Performance::lockForUpdate()->findOrFail($data['performance_id']);
            $this->checkWindow($tournament, $performance);
            abort_unless($performance->version === (int) $data['expected_version'], 409, 'نتیجه تغییر کرده است؛ صفحه را تازه کنید.');
            $this->setup->check(! CompetitionAppeal::where('performance_id', $performance->id)->exists(), 'برای این اجرا قبلاً اعتراض ثبت شده است؛ سابقه و رأی همان پرونده را بررسی کنید.');
            $appeal = CompetitionAppeal::create([
                'public_id' => (string) Str::uuid(), 'tournament_id' => $tournament->id,
                'category_id' => $performance->bout->category_id, 'performance_id' => $performance->id,
                'filed_by' => $actor->id, 'representative_name' => trim($data['representative_name']),
                'reason' => trim($data['reason']), 'evidence' => $data['evidence'] ?? null,
                'status' => 'submitted', 'version' => 1, 'performance_version' => $performance->version,
                'original_snapshot' => $this->facts($performance),
            ]);
            $this->setup->audit($actor, $tournament, 'appeal.filed', 'competition_appeal', $appeal->id, $appeal->toArray());

            return $appeal;
        }, 3);
    }

    public function beginReview(User $actor, Tournament $tournament, CompetitionAppeal $appeal, int $expectedVersion): void
    {
        Gate::forUser($actor)->authorize('reviewAppeals', $tournament);
        $this->locked($tournament, $appeal, $expectedVersion, function (Tournament $tournament, CompetitionAppeal $appeal, Performance $performance) use ($actor): void {
            $this->checkReviewer($actor, $appeal, $performance);
            $this->setup->check($appeal->status === 'submitted', 'فقط اعتراض ثبت‌شده قابل شروع بررسی است.');
            $before = $appeal->toArray();
            $appeal->update(['status' => 'reviewing', 'reviewed_by' => $actor->id, 'review_started_at' => now(), 'version' => $appeal->version + 1]);
            $this->setup->audit($actor, $tournament, 'appeal.review_started', 'competition_appeal', $appeal->id, $appeal->fresh()->toArray(), $before);
        });
    }

    /** @param array<string,mixed> $data */
    public function decide(User $actor, Tournament $tournament, CompetitionAppeal $appeal, array $data): void
    {
        Gate::forUser($actor)->authorize('reviewAppeals', $tournament);
        $this->locked($tournament, $appeal, (int) $data['expected_version'], function (Tournament $tournament, CompetitionAppeal $appeal, Performance $performance) use ($actor, $data): void {
            $this->checkReviewer($actor, $appeal, $performance);
            $this->setup->check($appeal->status === 'reviewing' && $appeal->reviewed_by === $actor->id, 'رأی باید توسط کاربری که بررسی این پرونده را آغاز کرده ثبت شود.');
            $this->setup->check(in_array($data['decision'], ['accepted', 'rejected'], true), 'رأی معتبر نیست.');
            $this->checkWindow($tournament, $performance);
            abort_unless($performance->version === $appeal->performance_version, 409, 'نتیجهٔ مورد بررسی تغییر کرده است.');
            $before = $appeal->toArray();
            $corrections = null;
            $decisionSnapshot = $this->facts($performance);
            if ($data['decision'] === 'accepted') {
                $keys = array_flip(['judge_assignment_id', 'expected_revision', 'accuracy', 'presentation', 'score', 'accuracy_penalties', 'presentation_components', 'presentation_penalties', 'accuracy_components']);
                $corrections = array_map(fn (array $input): array => array_intersect_key($input, $keys), $data['corrections']);
                $decisionSnapshot['proposed_result'] = $this->preview($performance, $corrections);
            }
            $appeal->update([
                'status' => $data['decision'], 'decision_reason' => trim($data['decision_reason']),
                'corrections' => $corrections, 'decision_snapshot' => $decisionSnapshot,
                'decided_at' => now(), 'version' => $appeal->version + 1,
            ]);
            $this->setup->audit($actor, $tournament, 'appeal.'.$data['decision'], 'competition_appeal', $appeal->id, $appeal->fresh()->toArray(), $before);
        });
    }

    public function apply(User $actor, Tournament $tournament, CompetitionAppeal $appeal, int $expectedVersion): void
    {
        Gate::forUser($actor)->authorize('operate', $tournament);
        DB::transaction(function () use ($actor, $tournament, $appeal, $expectedVersion): void {
            $tournament = Tournament::lockForUpdate()->findOrFail($tournament->id);
            $appeal = CompetitionAppeal::lockForUpdate()->findOrFail($appeal->id);
            abort_unless($appeal->tournament_id === $tournament->id, 404);
            if ($appeal->status === 'applied' && $expectedVersion === $appeal->version - 1) {
                return;
            }
            abort_unless($appeal->version === $expectedVersion, 409, 'پرونده تغییر کرده است؛ صفحه را تازه کنید.');
            $this->setup->check($appeal->status === 'accepted', 'فقط رأی پذیرفته‌شده قابل اعمال است.');
            $performance = Performance::lockForUpdate()->findOrFail($appeal->performance_id);
            $this->checkWindow($tournament, $performance);
            abort_unless($performance->version === $appeal->performance_version, 409, 'نتیجه پس از بررسی تغییر کرده است.');
            $this->preview($performance, $appeal->corrections);
            $before = $appeal->toArray();
            $reason = 'اعمال رأی اعتراض '.$appeal->id.'؛ '.mb_substr($appeal->decision_reason, 0, 440);
            foreach ($appeal->corrections as $input) {
                $this->submit->handle($actor, $tournament, $performance->fresh(), [
                    ...$input, 'expected_version' => $performance->fresh()->version,
                    'request_id' => (string) Str::uuid(), 'reason' => $reason,
                ], true, $appeal);
            }
            $after = $this->facts($performance->fresh());
            $this->setup->check($after['result'] === $appeal->decision_snapshot['proposed_result']['score'], 'نتیجهٔ محاسبه‌شده با رأی تأییدشده سازگار نیست.');
            $appeal->update(['status' => 'applied', 'applied_by' => $actor->id, 'applied_at' => now(), 'applied_snapshot' => $after, 'version' => $appeal->version + 1]);
            $this->setup->audit($actor, $tournament, 'appeal.applied', 'competition_appeal', $appeal->id, $appeal->fresh()->toArray(), $before);
        }, 3);
    }

    private function locked(Tournament $tournament, CompetitionAppeal $appeal, int $expectedVersion, callable $callback): void
    {
        DB::transaction(function () use ($tournament, $appeal, $expectedVersion, $callback): void {
            $tournament = Tournament::lockForUpdate()->findOrFail($tournament->id);
            $appeal = CompetitionAppeal::lockForUpdate()->findOrFail($appeal->id);
            abort_unless($appeal->tournament_id === $tournament->id, 404);
            $this->setup->check($tournament->status !== 'archived', 'مسابقه آرشیو شده است.');
            abort_unless($appeal->version === $expectedVersion, 409, 'پرونده تغییر کرده است؛ صفحه را تازه کنید.');
            $performance = Performance::lockForUpdate()->findOrFail($appeal->performance_id);
            $callback($tournament, $appeal, $performance);
        }, 3);
    }

    private function checkWindow(Tournament $tournament, Performance $performance): void
    {
        $performance->loadMissing('bout.category', 'bout.round', 'result');
        abort_unless($performance->bout->category->tournament_id === $tournament->id, 404);
        $this->setup->check(in_array($tournament->status, ['running', 'completed'], true) && $performance->status === 'approved' && $performance->result?->published_at !== null, 'اعتراض فقط به نتیجهٔ تأیید و منتشرشده در مسابقهٔ غیرآرشیوی مجاز است.');
        $this->setup->check(! $performance->bout->category->rounds()->where('sequence', '>', $performance->bout->round->sequence)
            ->where(fn ($query) => $query->whereNotNull('started_at')->orWhereIn('status', ['running', 'completed']))->exists(), 'پس از شروع مرحلهٔ بعدِ همین رده، ثبت یا اعمال اعتراض به مرحلهٔ قبل مجاز نیست.');
    }

    private function checkReviewer(User $actor, CompetitionAppeal $appeal, Performance $performance): void
    {
        abort_if($performance->bout()->firstOrFail()->judges()->where('user_id', $actor->id)->exists(), 403, 'داور همان رقابت نمی‌تواند بررسی‌کنندهٔ اعتراض آن باشد.');
    }

    /** @param array<int,array<string,mixed>> $corrections
     * @return array<string,mixed>
     */
    private function preview(Performance $performance, array $corrections): array
    {
        $facts = $this->facts($performance);
        $this->setup->check(count($corrections) >= 1 && count($corrections) <= 7, 'حداقل یک اصلاح مشخص برای پذیرش اعتراض لازم است.');
        $sheets = collect($facts['scores'])->keyBy('judge_assignment_id');
        $changedIds = [];
        foreach ($corrections as $input) {
            $id = (int) $input['judge_assignment_id'];
            $this->setup->check($sheets->has($id) && ! in_array($id, $changedIds, true), 'صندلی داوری باید متعلق به همین اجرا باشد و تکرار نشود.');
            abort_unless($sheets[$id]['revision'] === (int) $input['expected_revision'], 409, 'نمرهٔ صندلی تغییر کرده است.');
            $validated = $this->submit->validateInput($input, $facts['rules']);
            $sheets[$id] = [...$sheets[$id], 'values' => $validated['values']];
            $changedIds[] = $id;
        }
        $calculation = $this->calculator->calculate($sheets->pluck('values')->values()->all(), $facts['rules'], $performance->bout->category->judge_count);

        return $this->timing->applyPenalty($calculation, $performance);
    }

    /** @return array<string,mixed> */
    public function facts(Performance $performance): array
    {
        $performance->load(['bout.category.scoringRuleSet', 'bout.round', 'bout.judges.user', 'entry', 'result', 'scoreSheets']);
        $components = DB::table('score_components')->whereIn('score_sheet_id', $performance->scoreSheets->pluck('id'))->get()->groupBy('score_sheet_id');

        return [
            'id' => $performance->id, 'version' => $performance->version, 'status' => $performance->status,
            'entry_name' => $performance->entry->display_name, 'entry_id' => $performance->entry_id,
            'category_name' => $performance->bout->category->name, 'round_name' => $performance->bout->round->name,
            'form_number' => $performance->form_number, 'result' => $performance->result?->score,
            'calculation' => $performance->result?->calculation_snapshot, 'rules' => $performance->bout->category->scoringRuleSet->definition,
            'scores' => $performance->scoreSheets->map(fn ($sheet): array => [
                'id' => $sheet->id, 'judge_assignment_id' => $sheet->judge_assignment_id, 'revision' => $sheet->revision,
                'seat' => $performance->bout->judges->firstWhere('id', $sheet->judge_assignment_id)?->seat,
                'judge_name' => $performance->bout->judges->firstWhere('id', $sheet->judge_assignment_id)?->user?->name,
                'values' => $components->get($sheet->id, collect())->pluck('value_hundredths', 'criterion')->map(fn ($value) => (int) $value)->all(),
                'breakdown' => $sheet->breakdown,
            ])->values()->all(),
        ];
    }

    /** @return array<string,mixed> */
    public function listing(User $actor, Tournament $tournament, ?string $status = null): array
    {
        Gate::forUser($actor)->authorize('viewAppeals', $tournament);
        $appeals = CompetitionAppeal::where('tournament_id', $tournament->id)->when($status, fn ($query) => $query->where('status', $status))
            ->with(['filedBy', 'reviewedBy', 'appliedBy', 'performance.bout.judges'])->latest()->paginate(12)->withQueryString()->through(function (CompetitionAppeal $appeal) use ($actor, $tournament): array {
                return [...$appeal->attributesToArray(),
                    'filed_by_name' => $appeal->filedBy->name, 'reviewed_by_name' => $appeal->reviewedBy?->name, 'applied_by_name' => $appeal->appliedBy?->name,
                    'current' => $this->facts($appeal->performance),
                    'can_review' => $tournament->status !== 'archived' && $actor->can('reviewAppeals', $tournament) && ! $appeal->performance->bout->judges->contains('user_id', $actor->id),
                ];
            });
        $startedSequences = CompetitionRound::whereHas('category', fn ($query) => $query->where('tournament_id', $tournament->id))
            ->where(fn ($query) => $query->whereNotNull('started_at')->orWhereIn('status', ['running', 'completed']))
            ->select('category_id')->selectRaw('MAX(sequence) AS sequence')->groupBy('category_id')->pluck('sequence', 'category_id');
        $candidates = $actor->can('operate', $tournament) && in_array($tournament->status, ['running', 'completed'], true)
            ? Performance::where('status', 'approved')->whereHas('result', fn ($query) => $query->whereNotNull('published_at'))
                ->whereHas('bout.category', fn ($query) => $query->where('tournament_id', $tournament->id))
                ->whereNotIn('id', CompetitionAppeal::where('tournament_id', $tournament->id)->select('performance_id'))
                ->with(['entry', 'bout.category', 'bout.round'])->latest('id')->get()->filter(fn ($p) => (int) ($startedSequences[$p->bout->category_id] ?? 0) <= $p->bout->round->sequence)
                ->map(fn ($p): array => ['id' => $p->id, 'version' => $p->version, 'label' => $p->bout->category->name.' · '.$p->bout->round->name.' · '.$p->entry->display_name.' · فرم '.$p->form_number])->values()->all()
            : [];

        return ['appeals' => $appeals, 'candidates' => $candidates];
    }
}
