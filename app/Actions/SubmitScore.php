<?php

namespace App\Actions;

use App\Models\Bout;
use App\Models\Category;
use App\Models\JudgeAssignment;
use App\Models\Performance;
use App\Models\ScoreSheet;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SubmitScore
{
    public function __construct(private CompetitionSetup $setup, private CalculateScore $calculator, private RunCompetition $run) {}

    public function handle(User $actor, Tournament $tournament, Performance $performance, array $data, bool $proxy = false): array
    {
        abort_unless($actor->hasTournamentRole($tournament, $proxy ? ['manager', 'operator'] : ['judge']), 403);

        return DB::transaction(function () use ($actor, $tournament, $performance, $data, $proxy): array {
            $tournament = Tournament::lockForUpdate()->findOrFail($tournament->id);
            User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $performance = Performance::lockForUpdate()->findOrFail($performance->id);
            $bout = Bout::findOrFail($performance->bout_id);
            $category = Category::with('scoringRuleSet')->findOrFail($bout->category_id);
            abort_unless($category->tournament_id === $tournament->id, 404);
            $judge = $proxy
                ? JudgeAssignment::where('bout_id', $bout->id)->whereKey($data['judge_assignment_id'])->first()
                : JudgeAssignment::where('bout_id', $bout->id)->where('user_id', $actor->id)->first();
            abort_unless($judge, 403);
            $rules = $category->scoringRuleSet->definition;
            $singleScore = ($rules['input_method'] ?? null) === 'single_score_v1';
            $payload = ['performance_id' => $performance->id, 'judge_assignment_id' => $judge->id, 'proxy' => $proxy, 'expected_version' => (int) $data['expected_version'], 'expected_revision' => (int) $data['expected_revision'], 'score' => $data['score'] ?? null, 'accuracy' => $data['accuracy'] ?? null, 'presentation' => $data['presentation'] ?? null, 'reason' => $data['reason'] ?? null];
            if (array_key_exists('accuracy_penalties', $data) || array_key_exists('presentation_components', $data)) {
                $payload['accuracy_penalties'] = $data['accuracy_penalties'] ?? null;
                $payload['presentation_components'] = $data['presentation_components'] ?? null;
            }
            if (array_key_exists('presentation_penalties', $data) || array_key_exists('accuracy_components', $data)) {
                $payload['presentation_penalties'] = $data['presentation_penalties'] ?? null;
                $payload['accuracy_components'] = $data['accuracy_components'] ?? null;
            }
            $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
            $existing = DB::table('idempotency_keys')->where('user_id', $actor->id)->where('request_id', $data['request_id'])->first();
            if ($existing) {
                abort_unless(hash_equals($existing->payload_hash, $hash), 409, 'این شناسهٔ درخواست برای اطلاعات دیگری استفاده شده است.');

                return json_decode($existing->response_body, true, 512, JSON_THROW_ON_ERROR);
            }
            abort_unless($performance->version === (int) $data['expected_version'], 409, 'وضعیت اجرا تغییر کرده؛ صفحه را تازه کنید.');
            $publishedCorrection = $performance->status === 'approved';
            $this->setup->check(in_array($tournament->status, ['running', 'completed'], true) && in_array($performance->status, ['scoring', 'approved'], true), 'ثبت یا اصلاح نمره فقط پس از پایان اجرا مجاز است.');
            $sheet = ScoreSheet::where('performance_id', $performance->id)->where('judge_assignment_id', $judge->id)->first();
            abort_unless(($sheet?->revision ?? 0) === (int) $data['expected_revision'], 409, 'نمره در دستگاه دیگری تغییر کرده است؛ نسخهٔ تازه را دریافت کنید.');
            $this->setup->check(! $publishedCorrection || $sheet !== null, 'برای اصلاح نتیجهٔ نهایی باید نمرهٔ قبلی همین صندلی موجود باشد.');
            $this->setup->check($publishedCorrection || $proxy || ! $sheet || $sheet->submission_mode !== 'operator_proxy' || $sheet->status === 'draft', 'نمرهٔ دستی تأیید شده است و داور نمی‌تواند آن را بازنویسی کند.');
            $this->setup->check(! $proxy || mb_strlen(trim($data['reason'] ?? '')) >= 10, 'دلیل ثبت نمرهٔ جایگزین باید حداقل ۱۰ کاراکتر باشد.');
            $this->setup->check(! $sheet || mb_strlen(trim($data['reason'] ?? '')) >= 5, 'برای اصلاح نمره، دلیل حداقل ۵ کاراکتری بنویسید.');
            $values = ['accuracy' => $this->calculator->hundredths($singleScore ? $data['score'] : $data['accuracy']), 'presentation' => $singleScore ? 0 : $this->calculator->hundredths($data['presentation'])];
            $breakdown = ['method' => $singleScore ? 'single_score_v1' : 'direct', 'accuracy_hundredths' => $values['accuracy'], 'presentation_hundredths' => $values['presentation']];
            $this->setup->check(($rules['input_method'] ?? null) !== 'deductions_and_components_v1'
                || (array_key_exists('accuracy_penalties', $data) && array_key_exists('presentation_components', $data)), 'برای این رده ثبت کسرهای دقت و سه مؤلفهٔ اجرا الزامی است.');
            $freestyleDetailed = ($rules['input_method'] ?? null) === 'components_and_deductions_v1';
            $this->setup->check(! $freestyleDetailed
                || (array_key_exists('presentation_penalties', $data) && array_key_exists('accuracy_components', $data)), 'برای ابداعی ثبت سه مؤلفهٔ دقت و کسرهای اجرا الزامی است.');
            $penaltyField = $freestyleDetailed ? 'presentation_penalties' : 'accuracy_penalties';
            $componentField = $freestyleDetailed ? 'accuracy_components' : 'presentation_components';
            if (array_key_exists($penaltyField, $data) || array_key_exists($componentField, $data)) {
                $this->setup->check(is_array($data[$penaltyField] ?? null) && is_array($data[$componentField] ?? null), 'کسرها و سه مؤلفهٔ نمره را با هم ثبت کنید.');
                try {
                    $detailed = $this->calculator->detailedInput($data[$penaltyField], $data[$componentField], $rules);
                } catch (InvalidArgumentException) {
                    $this->setup->check(false, 'کسرهای نمره یا سه مؤلفهٔ دقت و اجرا معتبر نیست.');
                }
                $this->setup->check($values['accuracy'] === $detailed['accuracy'] && $values['presentation'] === $detailed['presentation'], 'مجموع نمره با جزئیات دقت و اجرا برابر نیست.');
                $breakdown = $detailed['breakdown'];
            }
            foreach ($values as $criterion => $value) {
                $this->setup->check($value <= $rules[$criterion.'_max'], 'نمره از سقف مؤلفه بیشتر است.');
            }
            if (! $sheet) {
                $sheet = ScoreSheet::create(['performance_id' => $performance->id, 'judge_assignment_id' => $judge->id, 'submitted_by' => $actor->id, 'submission_mode' => $proxy ? 'operator_proxy' : 'judge', 'revision' => 1, 'breakdown' => $breakdown, 'status' => $proxy ? 'draft' : 'submitted', 'submitted_at' => now()]);
            } else {
                $sheet->update(['submitted_by' => $actor->id, 'submission_mode' => $proxy ? 'operator_proxy' : 'judge', 'revision' => $sheet->revision + 1, 'breakdown' => $breakdown, 'status' => 'submitted', 'submitted_at' => now(), 'confirmed_by' => $proxy ? $actor->id : null, 'confirmed_at' => $proxy ? now() : null]);
            }
            foreach ($values as $criterion => $value) {
                DB::table('score_components')->updateOrInsert(['score_sheet_id' => $sheet->id, 'criterion' => $criterion], ['value_hundredths' => $value]);
            }
            DB::table('score_revisions')->insert(['score_sheet_id' => $sheet->id, 'changed_by' => $actor->id, 'revision' => $sheet->revision, 'snapshot' => json_encode([...$values, 'breakdown' => $breakdown], JSON_THROW_ON_ERROR), 'reason' => $data['reason'] ?? null, 'created_at' => now()]);
            if ($publishedCorrection) {
                $this->run->recalculateApprovedResult($actor, $tournament, $performance, $data['reason']);
            }
            $response = ['score_sheet_id' => $sheet->id, 'revision' => $sheet->revision, 'requires_confirmation' => $sheet->status === 'draft'];
            DB::table('idempotency_keys')->insert(['user_id' => $actor->id, 'request_id' => $data['request_id'], 'payload_hash' => $hash, 'response_status' => 200, 'response_body' => json_encode($response, JSON_THROW_ON_ERROR), 'created_at' => now()]);
            $this->setup->audit($actor, $tournament, $proxy ? 'score.proxy_submitted' : 'score.submitted', 'score_sheet', $sheet->id, ['revision' => $sheet->revision, 'judge_assignment_id' => $judge->id, 'reason' => $data['reason'] ?? null]);

            return $response;
        }, 3);
    }
}
