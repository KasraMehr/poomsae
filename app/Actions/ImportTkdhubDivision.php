<?php

namespace App\Actions;

use App\Http\Requests\TkdhubDivisionRequest;
use App\Models\Athlete;
use App\Models\Category;
use App\Models\Draw;
use App\Models\PoomsaeForm;
use App\Models\ScoringRuleSet;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ImportTkdhubDivision
{
    public function __construct(private CompetitionSetup $setup) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{category:Category,replayed:bool}
     */
    public function handle(User $actor, Tournament $tournament, array $payload): array
    {
        Gate::forUser($actor)->authorize('update', $tournament);
        $payload = Validator::make($payload, TkdhubDivisionRequest::payloadRules($payload['allowedFormCodes'] ?? []))->validate();
        $payload = $this->normalize($payload);
        $this->validateRoster($payload, $tournament);
        $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

        return DB::transaction(function () use ($actor, $tournament, $payload, $hash): array {
            $tournament = Tournament::lockForUpdate()->findOrFail($tournament->id);
            abort_if($tournament->tkdhub_event_id !== null && $tournament->tkdhub_event_id !== $payload['eventId'], 409, 'این مسابقه به رویداد دیگری متصل است.');
            abort_if(Tournament::where('tkdhub_event_id', $payload['eventId'])->whereKeyNot($tournament->id)->exists(), 409, 'رویداد قبلاً به مسابقهٔ دیگری متصل شده است.');
            $category = $tournament->categories()->where('tkdhub_division_id', $payload['divisionId'])->lockForUpdate()->first();
            if ($category && hash_equals($category->management_hash, $hash)) {
                return ['category' => $category, 'replayed' => true];
            }
            abort_if(in_array($tournament->status, ['completed', 'archived'], true), 409, 'مسابقه بسته شده است.');
            abort_if($category !== null, 409, 'این رده قبلاً دریافت شده است؛ تغییر اطلاعات باید جداگانه بررسی شود.');
            $this->setup->check(! $tournament->categories()->where('name', $payload['divisionName'])->exists(), 'رده‌ای با این نام وجود دارد.');
            $judgeCount = $payload['judgeCount'];
            $rule = ScoringRuleSet::create([
                'name' => 'tkdhub-'.Str::uuid(),
                'version' => 1, 'discipline' => 'recognized',
                'definition' => [
                    'algorithm' => 'component_trimmed_mean_v1', 'accuracy_max' => 400, 'presentation_max' => 600,
                    'accuracy_deduction_options' => [10, 30], 'presentation_component_max' => 200,
                    'input_method' => 'deductions_and_components_v1', 'discard_each_end' => $judgeCount === 3 ? 0 : 1,
                    'aggregation' => 'sum_two_forms', 'tie_break' => 'restore_all_judges_mean_then_operator',
                    'age_basis' => 'birth_date_range', 'external_birth_date_range' => [
                        'from' => $payload['birthDateFrom'], 'to' => $payload['birthDateTo'],
                    ],
                ],
                'approved_by' => $actor->id, 'approved_at' => now(),
            ]);
            $formIds = [];
            foreach ($payload['allowedFormCodes'] as $code) {
                $name = 'فرم '.$code;
                $formIds[$code] = PoomsaeForm::firstOrCreate(['code' => 'local-'.substr(hash('sha256', $name), 0, 24)], ['name' => $name])->id;
            }
            $selectedForms = [$formIds[$payload['form1Code']], $formIds[$payload['form2Code']]];
            $version = 1;
            $fields = [
                'tkdhub_division_id' => $payload['divisionId'], 'name' => $payload['divisionName'], 'scoring_rule_set_id' => $rule->id,
                'discipline' => 'recognized', 'entry_type' => 'individual', 'gender' => $payload['gender'],
                'format' => 'round_robin', 'execution_mode' => 'alternating', 'performance_order' => 'consecutive',
                'judge_count' => (string) $judgeCount, 'forms_per_round' => 2, 'draw_timing' => 'day_start',
                'form_sequence' => $selectedForms, 'planned_round_count' => 1, 'allow_form_repetition' => true,
                'form_draw_timing' => 'morning', 'form_draw_time' => '08:00',
                'management_source' => 'tkdhub', 'management_version' => $version, 'management_hash' => $hash,
                'management_snapshot' => $payload, 'synced_at' => now(),
            ];
            $category = $tournament->categories()->create($fields);
            $category->forms()->attach(array_values($formIds));
            foreach ($payload['athletes'] as $athlete) {
                $entry = $category->entries()->create(['external_id' => $athlete['entryId'],
                    'display_name' => $athlete['firstName'].' '.$athlete['lastName'],
                    'seed' => $athlete['drawPosition'], 'status' => $athlete['status'] === 'active' ? 'checked_in' : 'withdrawn',
                ]);
                $attributes = ['first_name' => $athlete['firstName'], 'last_name' => $athlete['lastName'],
                    'birth_date' => $athlete['birthDate'], 'gender' => $athlete['gender'], 'club' => $athlete['teamName'],
                    'country_code' => $athlete['countryCode'] ?? $athlete['country'] ?? null];
                $member = Athlete::create($attributes);
                $entry->athletes()->attach($member->id, ['category_id' => $category->id, 'position' => 1]);
            }
            $round = $category->rounds()->create(['sequence' => 1,
                'name' => 'مرحلهٔ نهایی', 'status' => 'pending', 'form_sequence' => $selectedForms,
                'forms_drawn_at' => now(), 'source_version' => $version, 'source_snapshot' => $payload, 'source_hash' => $hash,
            ]);
            $draw = Draw::create([
                'category_id' => $category->id, 'competition_round_id' => $round->id, 'created_by' => $actor->id,
                'kind' => 'forms', 'algorithm_version' => 'tkdhub_division_forms_v1', 'random_seed' => $hash,
                'input_snapshot' => ['eventId' => $payload['eventId'], 'divisionId' => $payload['divisionId'], 'version' => $version],
                'output_snapshot' => ['form_ids' => $selectedForms, 'form_codes' => [$payload['form1Code'], $payload['form2Code']]],
            ]);
            $round->update(['form_draw_id' => $draw->id]);
            $tournament->update(['tkdhub_event_id' => $payload['eventId']]);
            $this->setup->audit($actor, $tournament, 'tkdhub.division_imported', 'category', $category->id, [
                'eventId' => $payload['eventId'], 'divisionId' => $payload['divisionId'], 'version' => $version,
                'athlete_count' => count($payload['athletes']), 'snapshot' => $payload,
            ]);

            return ['category' => $category->fresh(), 'replayed' => false];
        }, 3);
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    private function normalize(array $payload): array
    {
        $forms = array_map('intval', $payload['allowedFormCodes']);
        sort($forms, SORT_NUMERIC);
        $gender = $payload['gender'] ?? 'open';
        $athletes = array_map(fn (array $athlete): array => [
            'entryId' => $athlete['entryId'], 'firstName' => trim($athlete['firstName']), 'lastName' => trim($athlete['lastName']),
            'teamName' => $athlete['teamName'], 'birthDate' => $athlete['birthDate'],
            'drawPosition' => $athlete['drawPosition'] === null ? null : (int) $athlete['drawPosition'],
            'status' => $athlete['status'] ?? 'active',
            'gender' => $athlete['gender'] ?? (in_array($gender, ['male', 'female'], true) ? $gender : null),
        ], $payload['athletes']);
        usort($athletes, fn (array $left, array $right): int => [$left['drawPosition'] ?? PHP_INT_MAX, $left['entryId']] <=> [$right['drawPosition'] ?? PHP_INT_MAX, $right['entryId']]);

        return [
            'eventId' => $payload['eventId'], 'divisionId' => $payload['divisionId'], 'divisionName' => trim($payload['divisionName']),
            'birthDateFrom' => $payload['birthDateFrom'] ?? null, 'birthDateTo' => $payload['birthDateTo'] ?? null,
            'allowedFormCodes' => $forms, 'form1Code' => (int) $payload['form1Code'], 'form2Code' => (int) $payload['form2Code'],
            'gender' => $gender, 'judgeCount' => (int) ($payload['judgeCount'] ?? 5), 'athletes' => $athletes,
        ];
    }

    /** @param array<string,mixed> $payload */
    private function validateRoster(array $payload, Tournament $tournament): void
    {
        $this->setup->check($payload['birthDateFrom'] === null || $payload['birthDateTo'] === null || $payload['birthDateFrom'] <= $payload['birthDateTo'], 'بازهٔ تاریخ تولد معتبر نیست.');
        $activePositions = [];
        foreach ($payload['athletes'] as $index => $athlete) {
            $key = 'athletes.'.$index;
            if (($payload['birthDateFrom'] !== null && $athlete['birthDate'] < $payload['birthDateFrom'])
                || ($payload['birthDateTo'] !== null && $athlete['birthDate'] > $payload['birthDateTo'])
                || $athlete['birthDate'] > $tournament->starts_on->format('Y-m-d')) {
                throw ValidationException::withMessages([$key.'.birthDate' => 'تاریخ تولد خارج از بازهٔ مجاز رده است.']);
            }
            $this->setup->check(! in_array($payload['gender'], ['male', 'female'], true) || $athlete['gender'] === $payload['gender'], 'جنسیت ورزشکار با رده سازگار نیست.');
            if ($athlete['status'] === 'active') {
                if ($athlete['drawPosition'] === null) {
                    throw ValidationException::withMessages([$key.'.drawPosition' => 'جایگاه قرعهٔ ورزشکار حاضر الزامی است.']);
                }
                $activePositions[] = $athlete['drawPosition'];
            } else {
                $this->setup->check($athlete['drawPosition'] === null, 'ورزشکار غایب یا منصرف نباید در قرعهٔ فعال باشد.');
            }
        }
        sort($activePositions, SORT_NUMERIC);
        $this->setup->check($activePositions === [] || $activePositions === range(1, count($activePositions)), 'جایگاه‌های قرعهٔ حاضرین باید از یک و بدون فاصله باشند.');
    }
}
