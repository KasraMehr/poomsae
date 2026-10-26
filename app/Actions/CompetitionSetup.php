<?php

namespace App\Actions;

use App\Models\Athlete;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Draw;
use App\Models\Entry;
use App\Models\PoomsaeForm;
use App\Models\ScoringRuleSet;
use App\Models\Tournament;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CompetitionSetup
{
    public function locked(User $actor, Tournament $tournament, callable $callback): mixed
    {
        Gate::forUser($actor)->authorize('update', $tournament);

        return DB::transaction(function () use ($tournament, $callback) {
            $tournament = Tournament::lockForUpdate()->findOrFail($tournament->id);
            $this->check(! in_array($tournament->status, ['completed', 'archived']), 'مسابقه بسته شده است.');

            return $callback($tournament);
        });
    }

    public function check(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['operation' => $message]);
        }
    }

    public function editable(Category $category): void
    {
        $this->check(! $category->rounds()->whereHas('bouts')->exists(), 'بعد از قرعه، تغییر رده یا فهرست ورزشکاران مجاز نیست.');
    }

    public function prepareKnockoutStages(User $actor, Tournament $tournament, Category $category): void
    {
        if ($category->format !== 'knockout' || $category->management_source !== null || $category->planned_round_count === null || $category->rounds()->whereHas('bouts')->exists()) {
            return;
        }
        $this->check(! $category->entries()->where('status', 'registered')->exists(), 'حضور یا انصراف تمام ورزشکاران ثبت‌نام‌شده را مشخص کنید.');
        $entryCount = $category->entries()->where('status', 'checked_in')->count();
        $this->check($entryCount >= 2, 'حداقل دو ورودی حاضر لازم است.');
        $requiredStages = (int) ceil(log($entryCount, 2));
        $this->check($category->discipline === 'freestyle' || $category->allow_form_repetition || $requiredStages <= 4, 'این تعداد ورودی به بیش از چهار مرحله نیاز دارد؛ برای هشت پومسه، تکرار فرم بین مراحل باید مجاز باشد.');
        if ($category->planned_round_count === $requiredStages && $category->rounds()->count() === $requiredStages) {
            return;
        }
        $before = ['planned_round_count' => $category->planned_round_count];
        $removedRoundIds = $category->rounds()->where('sequence', '>', $requiredStages)->pluck('id');
        Draw::whereIn('competition_round_id', $removedRoundIds)->update(['competition_round_id' => null]);
        $category->rounds()->whereIn('id', $removedRoundIds)->delete();
        for ($sequence = 1; $sequence <= $requiredStages; $sequence++) {
            $category->rounds()->firstOrCreate(['sequence' => $sequence], ['name' => 'مرحله '.$sequence, 'status' => 'pending', 'form_sequence' => []]);
        }
        $category->update(['planned_round_count' => $requiredStages]);
        $this->audit($actor, $tournament, 'category.stages_calculated', 'category', $category->id, ['planned_round_count' => $requiredStages, 'checked_in_count' => $entryCount], $before);
    }

    public function audit(User $actor, Tournament $tournament, string $action, string $type, int $id, array $after = [], ?array $before = null): void
    {
        $audit = AuditLog::create(['tournament_id' => $tournament->id, 'user_id' => $actor->id, 'action' => $action, 'subject_type' => $type, 'subject_id' => $id, 'before' => $before, 'after' => $after]);
        app(PublishCompetitionUpdate::class)->handle($audit);
    }

    public function court(User $actor, Tournament $tournament, array $data): void
    {
        $this->locked($actor, $tournament, function (Tournament $tournament) use ($actor, $data): void {
            $court = $tournament->courts()->create($data);
            $this->audit($actor, $tournament, 'court.created', 'court', $court->id, $data);
        });
    }

    public function member(User $actor, Tournament $tournament, array $data): void
    {
        $this->locked($actor, $tournament, function (Tournament $tournament) use ($actor, $data): void {
            $user = User::where('email', $data['email'])->first();
            if (! $user) {
                $this->check(! empty($data['password']), 'برای حساب جدید رمز حداقل ۱۲ کاراکتری لازم است.');
                $user = User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password']]);
            }
            $this->check($user->is_active, 'حساب انتخاب‌شده غیرفعال است.');
            $this->check(! $user->is_admin, 'حساب مدیر سامانه نمی‌تواند نقش داور، اپراتور یا نمایشگر بگیرد.');
            $existingRole = DB::table('tournament_user')->where('user_id', $user->id)->value('role');
            $this->check($existingRole === null || $existingRole === $data['role'], 'هر حساب فقط می‌تواند یک نوع پنل داشته باشد.');
            if ($data['role'] === 'operator') {
                $this->check(
                    ! DB::table('tournament_user')->where('tournament_id', $tournament->id)->where('role', 'operator')->exists(),
                    'هر مسابقه فقط می‌تواند یک اپراتور داشته باشد.'
                );
            }
            DB::table('tournament_user')->insertOrIgnore(['tournament_id' => $tournament->id, 'user_id' => $user->id, 'role' => $data['role'], 'created_at' => now(), 'updated_at' => now()]);
            $this->audit($actor, $tournament, 'member.added', 'user', $user->id, ['role' => $data['role']]);
        });
    }

    public function category(User $actor, Tournament $tournament, array $data, ?Category $category = null): Category
    {
        return $this->locked($actor, $tournament, function (Tournament $tournament) use ($actor, $data, $category): Category {
            if ($category) {
                $category = Category::lockForUpdate()->findOrFail($category->id);
                abort_unless($category->tournament_id === $tournament->id, 404);
                $this->check($category->management_source === null, 'تنظیمات این رده از سیستم مدیریت مسابقات دریافت می‌شود.');
                $this->editable($category);
            }
            $discardEachEnd = (int) $data['discard_each_end'];
            $judgeCount = (int) $data['judge_count'];
            $this->check(in_array($judgeCount, [3, 5, 7], true), 'تعداد داورها باید ۳، ۵ یا ۷ باشد.');
            $this->check(in_array($discardEachEnd, match ($judgeCount) {
                3 => [0], 7 => [1, 2], default => [1],
            }, true), 'با ۳ داور نمره‌ای حذف نمی‌شود؛ حذف دو نمره از هر طرف فقط با ۷ داور مجاز است.');
            $this->check(in_array($data['gender'], ['male', 'female'], true), 'جنسیت رده باید بانوان یا آقایان باشد.');
            $discipline = $data['discipline'] ?? 'recognized';
            $entryType = $data['entry_type'] ?? 'individual';
            $ageCategory = null;
            if (isset($data['age_category'])) {
                $ageCategory = config('poomsae.age_categories')[$data['age_category']] ?? null;
                $this->check($ageCategory !== null && $ageCategory['discipline'] === $discipline && $ageCategory['entry_type'] === $entryType, 'ردهٔ سنی با سبک و نوع شرکت سازگار نیست.');
                $ageCategory = ['id' => $data['age_category'], 'year' => config('poomsae.age_table_year'), ...$ageCategory];
                $data['minimum_age'] = $ageCategory['minimum_age'];
                $data['maximum_age'] = $ageCategory['maximum_age'];
                $selectedFormNumbers = array_map('intval', $data['selected_form_numbers'] ?? $ageCategory['forms']);
                $this->check($discipline === 'freestyle' || count($selectedFormNumbers) >= 2, 'حداقل دو فرم از فهرست مجاز رده انتخاب کنید.');
                $this->check($discipline === 'freestyle' || count($selectedFormNumbers) === count(array_unique($selectedFormNumbers)), 'فرم‌های انتخابی نباید تکراری باشند.');
                $this->check($discipline === 'freestyle' || array_diff($selectedFormNumbers, $ageCategory['forms']) === [], 'فرم انتخابی در فهرست مجاز این رده نیست.');
                $data['form_names'] = array_map(fn (int $number): string => PoomsaeForm::defaultNameForNumber($number), $selectedFormNumbers);
                $data['planned_round_count'] ??= 1;
            }
            $plannedRoundCount = isset($data['planned_round_count']) ? (int) $data['planned_round_count'] : null;
            $allowRepetition = (bool) ($data['allow_form_repetition'] ?? true);
            $this->check($plannedRoundCount === null || $plannedRoundCount >= 1, 'تعداد مراحل باید حداقل ۱ باشد.');
            $this->check($discipline === 'freestyle' ? in_array($entryType, ['individual', 'pair'], true) : in_array($entryType, ['individual', 'pair', 'team'], true), 'نوع شرکت در این سبک معتبر نیست.');
            $accuracyMax = (int) ($data['accuracy_max'] ?? 400);
            $this->check($discipline === 'freestyle' ? in_array($accuracyMax, [400, 600], true) : $accuracyMax === 400, 'سقف نمرهٔ رده معتبر نیست.');
            $definition = $discipline === 'freestyle'
                ? ($accuracyMax === 400
                    ? ['algorithm' => 'component_trimmed_mean_v1', 'accuracy_max' => 400, 'presentation_max' => 600, 'accuracy_component_max' => 100, 'accuracy_component_count' => 4, 'presentation_component_max' => 100, 'presentation_component_count' => 6, 'input_method' => 'freestyle_components_v1', 'discard_each_end' => $discardEachEnd, 'aggregation' => 'single_performance', 'tie_break' => 'restore_all_judges_mean_then_operator', 'age_basis' => 'birthday_on_start_date']
                    : ['algorithm' => 'component_trimmed_mean_v1', 'accuracy_max' => 600, 'presentation_max' => 400, 'accuracy_component_max' => 200, 'presentation_deduction_options' => [10, 30], 'input_method' => 'components_and_deductions_v1', 'discard_each_end' => $discardEachEnd, 'aggregation' => 'single_performance', 'tie_break' => 'restore_all_judges_mean_then_operator', 'age_basis' => 'birthday_on_start_date'])
                : ['algorithm' => 'component_trimmed_mean_v1', 'accuracy_max' => 400, 'presentation_max' => 600, 'accuracy_deduction_options' => [10, 30], 'presentation_component_max' => 200, 'input_method' => 'deductions_and_components_v1', 'discard_each_end' => $discardEachEnd, 'aggregation' => 'sum_two_forms', 'tie_break' => 'restore_all_judges_mean_then_operator', 'age_basis' => 'birthday_on_start_date'];
            if ($ageCategory !== null) {
                $definition['age_basis'] = 'birth_date_range';
                $definition['age_category'] = $ageCategory;
            }
            $rule = ScoringRuleSet::create([
                'name' => $category?->scoringRuleSet?->name ?? 'تنظیمات برگزارکننده '.Str::uuid(), 'version' => ($category?->scoringRuleSet?->version ?? 0) + 1, 'discipline' => $discipline,
                'definition' => $definition,
                'approved_by' => $actor->id, 'approved_at' => now(),
            ]);
            $formIds = collect($data['form_names'] ?? [])->map(function (string $name): int {
                return PoomsaeForm::firstOrCreate(['code' => 'local-'.substr(hash('sha256', trim($name)), 0, 24)], ['name' => trim($name)])->id;
            })->all();
            $expectedFormCount = $discipline === 'freestyle' ? 0 : ($ageCategory !== null ? count($data['form_names']) : ($plannedRoundCount === null ? 2 : 8));
            $this->check($discipline === 'freestyle' || count(array_unique($formIds)) === $expectedFormCount, $ageCategory !== null ? 'فهرست فرم‌های انتخابی معتبر نیست.' : ($plannedRoundCount === null ? 'دو فرم متفاوت وارد کنید.' : 'هشت پومسهٔ متفاوت برای این ردهٔ سنی وارد کنید.'));
            $this->check($discipline === 'freestyle' || $allowRepetition || $plannedRoundCount === null || $plannedRoundCount <= intdiv(count($formIds), 2), 'با حالت غیرتکراری، برای هر مرحله دو فرم جدا لازم است؛ تعداد مراحل را با فهرست فرم‌های انتخابی هماهنگ کنید.');
            $fields = [
                'name' => $data['name'], 'gender' => $data['gender'], 'minimum_age' => $data['minimum_age'] ?? null, 'maximum_age' => $data['maximum_age'] ?? null,
                'format' => $data['format'], 'judge_count' => (string) $data['judge_count'],
                'discipline' => $discipline, 'entry_type' => $entryType, 'execution_mode' => $discipline === 'recognized' && $data['format'] === 'knockout' ? ($data['execution_mode'] ?? 'alternating') : 'alternating',
                'performance_order' => $discipline === 'recognized' && $data['format'] === 'round_robin' ? ($data['performance_order'] ?? 'consecutive') : 'consecutive',
                'forms_per_round' => $discipline === 'freestyle' ? 1 : 2, 'draw_timing' => 'day_start', 'scoring_rule_set_id' => $rule->id, 'form_sequence' => $plannedRoundCount === null ? $formIds : [],
                'planned_round_count' => $plannedRoundCount, 'allow_form_repetition' => $allowRepetition,
                'form_draw_timing' => $data['form_draw_timing'] ?? 'morning', 'form_draw_time' => $data['form_draw_time'] ?? '08:00',
            ];
            $before = $category?->toArray();
            if ($category) {
                $this->check(! $category->rounds()->whereNotNull('forms_drawn_at')->exists(), 'بعد از قرعهٔ فرم‌ها، تنظیم مراحل قابل تغییر نیست.');
                if ($category->entries()->exists()) {
                    $this->check($category->discipline === $discipline && $category->entry_type === $entryType, 'پس از ثبت ورزشکار، سبک و نوع شرکت رده قابل تغییر نیست.');
                    $candidate = clone $category;
                    $candidate->fill($fields)->setRelation('scoringRuleSet', $rule);
                    foreach ($category->entries()->with('athletes')->get() as $entry) {
                        $members = $entry->athletes->map(fn ($athlete): array => [...$athlete->only(['first_name', 'last_name', 'gender', 'club', 'country_code']), 'birth_date' => $athlete->birth_date?->format('Y-m-d')])->all();
                        $this->validateMembers($tournament, $candidate, $members, $entry->id);
                    }
                }
                $category->rounds()->delete();
                $category->update($fields);
            } else {
                $category = $tournament->categories()->create($fields);
            }
            $category->setRelation('scoringRuleSet', $rule);
            $category->forms()->sync($formIds);
            for ($sequence = 1; $sequence <= ($plannedRoundCount ?? 0); $sequence++) {
                $category->rounds()->create(['name' => 'مرحله '.$sequence, 'sequence' => $sequence, 'status' => 'pending', 'form_sequence' => []]);
            }
            $this->audit($actor, $tournament, 'category.saved', 'category', $category->id, $fields, $before);

            return $category;
        });
    }

    public function entry(User $actor, Tournament $tournament, Category $category, array $data): Entry
    {
        return $this->locked($actor, $tournament, function (Tournament $tournament) use ($actor, $category, $data): Entry {
            abort_unless($category->tournament_id === $tournament->id, 404);
            $this->editable($category);
            $this->check($category->management_source === null, 'فهرست این رده از سیستم مدیریت مسابقات دریافت می‌شود.');
            $members = $category->entry_type === 'individual' ? [$data] : $data['members'];
            $this->validateMembers($tournament, $category, $members);
            $musicPath = $category->discipline === 'freestyle' && ($data['music'] ?? null) instanceof UploadedFile ? $data['music']->store('competition-music', 'local') : null;
            $entry = $category->entries()->create(['display_name' => implode(' / ', array_map(fn (array $member): string => $member['first_name'].' '.$member['last_name'], $members)), 'status' => 'registered', 'music_path' => $musicPath]);
            foreach ($members as $index => $member) {
                $athlete = Athlete::create($member);
                $entry->athletes()->attach($athlete->id, ['category_id' => $category->id, 'position' => $index + 1]);
            }
            $this->audit($actor, $tournament, 'entry.created', 'entry', $entry->id, ['athlete_ids' => $entry->athletes()->pluck('athletes.id')->all()]);

            return $entry;
        });
    }

    /** @param array<int, array<string, mixed>> $members */
    public function validateMembers(Tournament $tournament, Category $category, array $members, ?int $entryId = null): void
    {
        $this->check(count($members) === match ($category->entry_type) {
            'individual' => 1, 'pair' => 2, 'team' => 3
        }, 'تعداد اعضای ورودی معتبر نیست.');
        $identities = [];
        $ageCategory = $category->scoringRuleSet?->definition['age_category'] ?? null;
        foreach ($members as $member) {
            $identity = mb_strtolower(trim($member['first_name']).'|'.trim($member['last_name']).'|'.$member['birth_date']);
            $this->check(! in_array($identity, $identities, true), 'یک ورزشکار در همین گروه تکرار شده است.');
            $identities[] = $identity;
            $this->check($category->gender === 'open' || $category->gender === 'mixed' || $category->gender === $member['gender'], 'جنسیت ورزشکار با رده سازگار نیست.');
            if ($ageCategory !== null) {
                $birthDate = CarbonImmutable::parse($member['birth_date'])->toDateString();
                $this->check(($ageCategory['birth_date_min'] === null || $birthDate >= $ageCategory['birth_date_min']) && ($ageCategory['birth_date_max'] === null || $birthDate <= $ageCategory['birth_date_max']), 'تاریخ تولد ورزشکار با ردهٔ سنی سازگار نیست: '.$ageCategory['birth_date_label']);
            } else {
                $age = (int) CarbonImmutable::parse($member['birth_date'])->diffInYears(CarbonImmutable::parse($tournament->starts_on));
                $this->check(($category->minimum_age === null || $age >= $category->minimum_age) && ($category->maximum_age === null || $age <= $category->maximum_age), 'سن ورزشکار در روز شروع مسابقه با رده سازگار نیست.');
            }
            $this->check(! $category->entries()->when($entryId, fn ($query) => $query->where('id', '!=', $entryId))->whereHas('athletes', fn ($q) => $q->where('first_name', $member['first_name'])->where('last_name', $member['last_name'])->whereDate('birth_date', $member['birth_date']))->exists(), 'ورزشکار با همین نام و تاریخ تولد قبلاً در این رده ثبت شده است.');
        }
        $this->check($category->gender !== 'mixed' || count(array_unique(array_column($members, 'gender'))) === 2, 'ردهٔ مختلط به ورزشکار زن و مرد نیاز دارد.');
    }

    public function entryStatus(User $actor, Tournament $tournament, Entry $entry, string $status): void
    {
        $this->locked($actor, $tournament, function (Tournament $tournament) use ($actor, $entry, $status): void {
            $category = Category::findOrFail($entry->category_id);
            abort_unless($category->tournament_id === $tournament->id, 404);
            $this->editable($category);
            $this->check($category->management_source === null, 'پذیرش این رده از سیستم مدیریت مسابقات دریافت می‌شود.');
            $before = ['status' => $entry->status];
            $entry->update(['status' => $status]);
            $this->audit($actor, $tournament, 'entry.status', 'entry', $entry->id, ['status' => $status], $before);
        });
    }
}
