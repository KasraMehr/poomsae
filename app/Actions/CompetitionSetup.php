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
        $this->check(! $category->rounds()->exists(), 'بعد از قرعه، تغییر رده یا فهرست ورزشکاران مجاز نیست.');
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

    public function category(User $actor, Tournament $tournament, array $data, ?Category $category = null): void
    {
        $this->locked($actor, $tournament, function (Tournament $tournament) use ($actor, $data, $category): void {
            if ($category) {
                abort_unless($category->tournament_id === $tournament->id, 404);
                $this->editable($category);
            }
            $discardEachEnd = (int) $data['discard_each_end'];
            $this->check(in_array($discardEachEnd, [1, 2], true) && ($discardEachEnd === 1 || (int) $data['judge_count'] === 7), 'حذف دو نمره از هر طرف فقط با هفت داور مجاز است.');
            $rule = ScoringRuleSet::create([
                'name' => 'تنظیمات برگزارکننده '.Str::uuid(), 'version' => 1, 'discipline' => 'recognized',
                'definition' => ['algorithm' => 'component_trimmed_mean_v1', 'accuracy_max' => (int) $data['accuracy_max'], 'presentation_max' => 1000 - (int) $data['accuracy_max'], 'discard_each_end' => $discardEachEnd, 'aggregation' => 'mean_two_forms', 'tie_break' => 'restore_all_judges_mean_then_operator', 'age_basis' => 'birthday_on_start_date'],
                'approved_by' => $actor->id, 'approved_at' => now(),
            ]);
            $formIds = collect($data['form_names'])->map(function (string $name): int {
                return PoomsaeForm::firstOrCreate(['code' => 'local-'.substr(hash('sha256', trim($name)), 0, 24)], ['name' => trim($name)])->id;
            })->all();
            $this->check(count(array_unique($formIds)) === 2, 'دو فرم متفاوت وارد کنید.');
            $fields = [
                'name' => $data['name'], 'gender' => $data['gender'], 'minimum_age' => $data['minimum_age'] ?? null, 'maximum_age' => $data['maximum_age'] ?? null,
                'format' => $data['format'], 'judge_count' => (string) $data['judge_count'],
                'discipline' => 'recognized', 'entry_type' => 'individual', 'execution_mode' => 'alternating',
                'forms_per_round' => 2, 'draw_timing' => 'day_start', 'scoring_rule_set_id' => $rule->id, 'form_sequence' => $formIds,
            ];
            $before = $category?->toArray();
            if ($category) {
                $this->check(! $category->entries()->exists(), 'برای تغییر قوانین رده باید رده بدون ورزشکار باشد.');
                $category->update($fields);
            } else {
                $category = $tournament->categories()->create($fields);
            }
            $category->forms()->sync($formIds);
            $this->audit($actor, $tournament, 'category.saved', 'category', $category->id, $fields, $before);
        });
    }

    public function entry(User $actor, Tournament $tournament, Category $category, array $data): void
    {
        $this->locked($actor, $tournament, function (Tournament $tournament) use ($actor, $category, $data): void {
            abort_unless($category->tournament_id === $tournament->id, 404);
            $this->editable($category);
            $this->check($category->discipline === 'recognized' && $category->entry_type === 'individual', 'این نسخه برای استاندارد انفرادی است.');
            $this->check($category->gender === 'open' || $category->gender === $data['gender'], 'جنسیت ورزشکار با رده سازگار نیست.');
            $age = (int) CarbonImmutable::parse($data['birth_date'])->diffInYears(CarbonImmutable::parse($tournament->starts_on));
            $this->check(($category->minimum_age === null || $age >= $category->minimum_age) && ($category->maximum_age === null || $age <= $category->maximum_age), 'سن ورزشکار در روز شروع مسابقه با رده سازگار نیست.');
            $this->check(! $category->entries()->whereHas('athletes', fn ($q) => $q->where('first_name', $data['first_name'])->where('last_name', $data['last_name'])->whereDate('birth_date', $data['birth_date']))->exists(), 'ورزشکار با همین نام و تاریخ تولد قبلاً در این رده ثبت شده است.');
            $athlete = Athlete::create($data);
            $entry = $category->entries()->create(['display_name' => $data['first_name'].' '.$data['last_name'], 'status' => 'registered']);
            $entry->athletes()->attach($athlete->id, ['category_id' => $category->id, 'position' => 1]);
            $this->audit($actor, $tournament, 'entry.created', 'entry', $entry->id, ['athlete_id' => $athlete->id]);
        });
    }

    public function entryStatus(User $actor, Tournament $tournament, Entry $entry, string $status): void
    {
        $this->locked($actor, $tournament, function (Tournament $tournament) use ($actor, $entry, $status): void {
            $category = Category::findOrFail($entry->category_id);
            abort_unless($category->tournament_id === $tournament->id, 404);
            $this->editable($category);
            $before = ['status' => $entry->status];
            $entry->update(['status' => $status]);
            $this->audit($actor, $tournament, 'entry.status', 'entry', $entry->id, ['status' => $status], $before);
        });
    }
}
