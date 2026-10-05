<?php

namespace App\Actions;

use App\Events\CompetitionUpdated;
use App\Models\Athlete;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Entry;
use App\Models\PoomsaeForm;
use App\Models\ScoringRuleSet;
use App\Models\Tournament;
use App\Models\User;
use Carbon\CarbonImmutable;
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

    public function audit(User $actor, Tournament $tournament, string $action, string $type, int $id, array $after = [], ?array $before = null): void
    {
        $audit = AuditLog::create(['tournament_id' => $tournament->id, 'user_id' => $actor->id, 'action' => $action, 'subject_type' => $type, 'subject_id' => $id, 'before' => $before, 'after' => $after]);
        CompetitionUpdated::dispatch($tournament->id, $audit->id);
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
            DB::table('tournament_user')->insertOrIgnore(['tournament_id' => $tournament->id, 'user_id' => $user->id, 'role' => $data['role'], 'created_at' => now(), 'updated_at' => now()]);
            $this->audit($actor, $tournament, 'member.added', 'user', $user->id, ['role' => $data['role']]);
        });
    }

    public function category(User $actor, Tournament $tournament, array $data, ?Category $category = null): Category
    {
        return $this->locked($actor, $tournament, function (Tournament $tournament) use ($actor, $data, $category): Category {
            if ($category) {
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
            $plannedRoundCount = isset($data['planned_round_count']) ? (int) $data['planned_round_count'] : null;
            $allowRepetition = (bool) ($data['allow_form_repetition'] ?? true);
            $this->check($plannedRoundCount === null || $plannedRoundCount >= 1, 'تعداد مراحل باید حداقل ۱ باشد.');
            $this->check($discipline === 'freestyle' || $allowRepetition || $plannedRoundCount <= 4, 'با هشت پومسه و دو فرم در هر مرحله، حالت غیرتکراری حداکثر چهار مرحله دارد.');
            $this->check($discipline === 'freestyle' ? in_array($entryType, ['individual', 'pair'], true) : in_array($entryType, ['individual', 'team'], true), 'نوع شرکت در این سبک معتبر نیست.');
            $this->check((int) ($data['accuracy_max'] ?? ($discipline === 'freestyle' ? 600 : 400)) === ($discipline === 'freestyle' ? 600 : 400), 'سقف نمرهٔ رده معتبر نیست.');
            $definition = $discipline === 'freestyle'
                ? ['algorithm' => 'component_trimmed_mean_v1', 'accuracy_max' => 600, 'presentation_max' => 400, 'accuracy_component_max' => 200, 'presentation_deduction_options' => [10, 30], 'input_method' => 'components_and_deductions_v1', 'discard_each_end' => $discardEachEnd, 'aggregation' => 'single_performance', 'tie_break' => 'restore_all_judges_mean_then_operator', 'age_basis' => 'birthday_on_start_date']
                : ['algorithm' => 'component_trimmed_mean_v1', 'accuracy_max' => 400, 'presentation_max' => 600, 'accuracy_deduction_options' => [10, 30], 'presentation_component_max' => 200, 'input_method' => 'deductions_and_components_v1', 'discard_each_end' => $discardEachEnd, 'aggregation' => 'sum_two_forms', 'tie_break' => 'restore_all_judges_mean_then_operator', 'age_basis' => 'birthday_on_start_date'];
            $rule = ScoringRuleSet::create([
                'name' => 'تنظیمات برگزارکننده '.Str::uuid(), 'version' => 1, 'discipline' => $discipline,
                'definition' => $definition,
                'approved_by' => $actor->id, 'approved_at' => now(),
            ]);
            $formIds = collect($data['form_names'] ?? [])->map(function (string $name): int {
                return PoomsaeForm::firstOrCreate(['code' => 'local-'.substr(hash('sha256', trim($name)), 0, 24)], ['name' => trim($name)])->id;
            })->all();
            $this->check($discipline === 'freestyle' || count(array_unique($formIds)) === ($plannedRoundCount === null ? 2 : 8), $plannedRoundCount === null ? 'دو فرم متفاوت وارد کنید.' : 'هشت پومسهٔ متفاوت برای این ردهٔ سنی وارد کنید.');
            $fields = [
                'name' => $data['name'], 'gender' => $data['gender'], 'minimum_age' => $data['minimum_age'] ?? null, 'maximum_age' => $data['maximum_age'] ?? null,
                'format' => $data['format'], 'judge_count' => (string) $data['judge_count'],
                'discipline' => $discipline, 'entry_type' => $entryType, 'execution_mode' => $data['format'] === 'knockout' ? ($data['execution_mode'] ?? 'alternating') : 'alternating',
                'performance_order' => $data['format'] === 'round_robin' ? ($data['performance_order'] ?? 'consecutive') : 'consecutive',
                'forms_per_round' => $discipline === 'freestyle' ? 1 : 2, 'draw_timing' => 'day_start', 'scoring_rule_set_id' => $rule->id, 'form_sequence' => $plannedRoundCount === null ? $formIds : [],
                'planned_round_count' => $plannedRoundCount, 'allow_form_repetition' => $allowRepetition,
                'form_draw_timing' => $data['form_draw_timing'] ?? 'morning', 'form_draw_time' => $data['form_draw_time'] ?? '08:00',
            ];
            $before = $category?->toArray();
            if ($category) {
                $this->check(! $category->entries()->exists(), 'برای تغییر قوانین رده باید رده بدون ورزشکار باشد.');
                $this->check(! $category->rounds()->whereNotNull('forms_drawn_at')->exists(), 'بعد از قرعهٔ فرم‌ها، تنظیم مراحل قابل تغییر نیست.');
                $category->rounds()->delete();
                $category->update($fields);
            } else {
                $category = $tournament->categories()->create($fields);
            }
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
            $musicPath = $category->discipline === 'freestyle' ? $data['music']->store('competition-music', 'local') : null;
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
        foreach ($members as $member) {
            $identity = mb_strtolower(trim($member['first_name']).'|'.trim($member['last_name']).'|'.$member['birth_date']);
            $this->check(! in_array($identity, $identities, true), 'یک ورزشکار در همین گروه تکرار شده است.');
            $identities[] = $identity;
            $this->check($category->gender === 'open' || $category->gender === 'mixed' || $category->gender === $member['gender'], 'جنسیت ورزشکار با رده سازگار نیست.');
            $age = (int) CarbonImmutable::parse($member['birth_date'])->diffInYears(CarbonImmutable::parse($tournament->starts_on));
            $this->check(($category->minimum_age === null || $age >= $category->minimum_age) && ($category->maximum_age === null || $age <= $category->maximum_age), 'سن ورزشکار در روز شروع مسابقه با رده سازگار نیست.');
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
