<?php

namespace App\Actions;

use App\Models\Athlete;
use App\Models\Category;
use App\Models\CompetitionRound;
use App\Models\PoomsaeForm;
use App\Models\Tournament;
use App\Models\User;

class ImportManagementSnapshot
{
    public function __construct(private CompetitionSetup $setup, private ScheduleRound $schedule, private DrawRoundForms $forms) {}

    /** @return array{category_id:int, version:int, replayed:bool} */
    public function handle(User $actor, Tournament $tournament, Category $category, array $data): array
    {
        return $this->setup->locked($actor, $tournament, function (Tournament $tournament) use ($actor, $category, $data): array {
            $category = Category::with('scoringRuleSet')->findOrFail($category->id);
            abort_unless($category->tournament_id === $tournament->id, 404);
            $this->setup->check($category->planned_round_count !== null, 'قبل از ورود اطلاعات، تعداد و ترتیب مراحل رده را مشخص کنید.');
            $this->setup->check($category->scoringRuleSet?->approved_at !== null && ($category->scoringRuleSet->definition['algorithm'] ?? null) === 'component_trimmed_mean_v1', 'قواعد داوری رده باید قبل از ورود اطلاعات تأیید شده باشند.');
            $content = array_diff_key($data, array_flip(['expected_version', 'reason']));
            $hash = $this->fingerprint($content);
            abort_unless($category->management_source === null || $category->management_source === $data['source'], 409, 'منبع اطلاعات این رده متفاوت است.');
            if ($category->management_version === (int) $data['version']) {
                abort_unless($category->management_hash === $hash, 409, 'این نسخه قبلاً با اطلاعات دیگری دریافت شده است.');

                return ['category_id' => $category->id, 'version' => $category->management_version, 'replayed' => true];
            }
            abort_unless((int) $data['expected_version'] === $category->management_version && (int) $data['version'] > $category->management_version, 409, 'نسخهٔ اطلاعات قدیمی است؛ ابتدا نسخهٔ فعلی را دریافت کنید.');
            $this->setup->check($category->management_version === 0 || mb_strlen(trim($data['reason'] ?? '')) >= 5, 'برای اصلاح اطلاعات، دلیل تغییر را ثبت کنید.');
            $started = $category->rounds()->where(fn ($query) => $query->whereNotNull('started_at')->orWhere('status', '!=', 'pending'))->exists();
            if ($started) {
                abort_unless($category->management_snapshot && $this->fingerprint($data['entries']) === $this->fingerprint($category->management_snapshot['entries']), 409, 'بعد از شروع مسابقه، فهرست و مشخصات اعضا قفل است.');
                abort_unless($this->fingerprint($data['category'] ?? []) === $this->fingerprint($category->management_snapshot['category'] ?? []), 409, 'ردهٔ سنی مرحلهٔ شروع‌شده قابل تغییر نیست.');
            } else {
                $categoryFields = $data['category'] ?? [];
                unset($categoryFields['form_names']);
                $category->update($categoryFields);
                if (isset($data['category']['form_names'])) {
                    $formIds = array_map(fn (string $name): int => PoomsaeForm::firstOrCreate(['code' => 'local-'.substr(hash('sha256', trim($name)), 0, 24)], ['name' => trim($name)])->id, $data['category']['form_names']);
                    $this->setup->check(count(array_unique($formIds)) === 8, 'فهرست هشت پومسه باید بدون تکرار باشد.');
                    $category->forms()->sync($formIds);
                }
                $this->setup->check($category->minimum_age === null || $category->maximum_age === null || $category->minimum_age <= $category->maximum_age, 'بازهٔ سن رده معتبر نیست.');
                $this->importEntries($tournament, $category, $data['entries']);
            }
            $entries = $category->entries()->whereNotNull('external_id')->get()->keyBy('external_id');
            $changes = [];
            foreach ($data['stages'] as $stage) {
                $round = $category->rounds()->where('sequence', $stage['sequence'])->first();
                $this->setup->check($round !== null, 'شمارهٔ مرحله خارج از برنامهٔ رده است.');
                $stageHash = $this->fingerprint($stage);
                $this->setup->check($tournament->courts()->whereKey($stage['court_id'])->exists(), 'زمین مرحله باید متعلق به همین مسابقه باشد.');
                $this->setup->check(count(array_unique($stage['judge_ids'])) === $category->judge_count && $tournament->users()->wherePivot('role', 'judge')->where('is_active', true)->whereIn('users.id', $stage['judge_ids'])->count() === $category->judge_count, 'پنل داوری مرحله باید متعلق به همین مسابقه باشد.');
                $pairs = [];
                foreach ($stage['bouts'] as $pair) {
                    $pairIds = [];
                    foreach ($pair as $externalId) {
                        $entry = $entries->get($externalId);
                        $this->setup->check($entry && $entry->status === 'checked_in', 'تمام ورودی‌های جدول باید در فهرست همین رده حاضر باشند.');
                        $pairIds[] = $entry->id;
                    }
                    $pairs[] = $pairIds;
                }
                $this->validateBracket($category, $round, $pairs);
                if ($round->source_hash === $stageHash) {
                    $round->update(['source_version' => $data['version']]);

                    continue;
                }
                abort_unless($round->started_at === null && $round->status === 'pending' && ! $round->bouts()->whereHas('performances', fn ($query) => $query->whereNotNull('started_at')->orWhereHas('scoreSheets'))->exists(), 409, 'جدول یا فرم مرحلهٔ شروع‌شده قابل جایگزینی نیست.');
                $chosenForms = $stage['form_ids'];
                if (isset($stage['form_names'])) {
                    $this->setup->check($chosenForms === [], 'نام فرم‌ها یا شناسهٔ آن‌ها را ارسال کنید، نه هر دو.');
                    $chosenForms = array_map(fn ($name) => $category->forms()->where('name', trim($name))->value('poomsae_forms.id'), $stage['form_names']);
                    $this->setup->check(! in_array(null, $chosenForms, true), 'فرم مرحله باید در فهرست هشت پومسهٔ رده باشد.');
                    $chosenForms = array_map('intval', $chosenForms);
                }
                $this->setup->check($category->discipline !== 'freestyle' || $chosenForms === [], 'ابداعی قرعهٔ فرم استاندارد ندارد.');
                $this->setup->check($chosenForms === [] || count($chosenForms) === 2, 'قرعهٔ فرم‌ها باید خالی یا شامل دو پومسه باشد.');
                $this->clearPendingBouts($round);
                $round->update(['name' => 'مرحله '.$round->sequence, 'status' => 'pending', 'form_sequence' => [], 'forms_drawn_at' => null, 'form_draw_id' => null, 'scheduled_at' => null]);
                $changes[] = [$round, $stage, $pairs, $stageHash, $chosenForms];
            }
            foreach ($changes as [$round, $stage, $pairs, $stageHash, $chosenForms]) {
                if ($chosenForms !== []) {
                    $this->setup->check(now()->greaterThanOrEqualTo($this->forms->availableAt($tournament, $category)), 'زمان انتشار قرعهٔ فرم‌ها هنوز نرسیده است.');
                    $this->forms->assign($actor, $tournament, $category, $round, $chosenForms, 'management_forms_v1', $data['source'].':'.$data['version']);
                }
                if ($pairs !== []) {
                    $this->schedule->createBouts($actor, $tournament, $category, $round, $pairs, (int) $stage['court_id'], array_map('intval', $stage['judge_ids']), $data['source'].':'.$data['version'], 'management_random_bracket_v1');
                }
                $round->update(['source_version' => $data['version'], 'source_snapshot' => $stage, 'source_hash' => $stageHash]);
            }
            $allSelectedForms = $category->rounds()->get()->flatMap(fn ($round) => $round->form_sequence ?? [])->unique()->all();
            $this->setup->check($category->forms()->whereIn('poomsae_forms.id', $allSelectedForms)->count() === count($allSelectedForms), 'تغییر فهرست مجاز نباید فرم مرحلهٔ ذخیره‌شده را نامعتبر کند.');
            $category->update(['management_source' => $data['source'], 'management_version' => $data['version'], 'management_hash' => $hash, 'management_snapshot' => $content, 'synced_at' => now()]);
            $this->setup->audit($actor, $tournament, 'management.snapshot_imported', 'category', $category->id, ['source' => $data['source'], 'version' => $data['version'], 'snapshot' => $content, 'reason' => $data['reason'] ?? null]);

            return ['category_id' => $category->id, 'version' => $category->management_version, 'replayed' => false];
        });
    }

    /** @param array<int, array<string, mixed>> $entries */
    private function importEntries(Tournament $tournament, Category $category, array $entries): void
    {
        foreach ($entries as $data) {
            $entry = $category->entries()->where('external_id', $data['external_id'])->first();
            if (! $entry && isset($data['local_entry_id'])) {
                $entry = $category->entries()->find($data['local_entry_id']);
                $this->setup->check($entry && ($entry->external_id === null || $entry->external_id === $data['external_id']), 'شناسهٔ ورودی محلی متعلق به این رده یا این شرکت‌کننده نیست.');
            }
            $this->setup->validateMembers($tournament, $category, $data['members'], $entry?->id);
            $this->setup->check($category->discipline !== 'freestyle' || $entry?->music_path !== null, 'برای ابداعی، ابتدا فایل موسیقی را در ثبت‌نام محلی بارگذاری و local_entry_id را ارسال کنید.');
            $fields = ['external_id' => $data['external_id'], 'status' => $data['status'], 'display_name' => implode(' / ', array_map(fn ($member) => $member['first_name'].' '.$member['last_name'], $data['members']))];
            if ($entry) {
                $entry->update($fields);
                $athletes = $entry->athletes()->orderByPivot('position')->get();
                foreach ($data['members'] as $index => $member) {
                    $athletes[$index]->update($member);
                }
            } else {
                $entry = $category->entries()->create($fields);
                foreach ($data['members'] as $index => $member) {
                    $athlete = Athlete::create($member);
                    $entry->athletes()->attach($athlete->id, ['category_id' => $category->id, 'position' => $index + 1]);
                }
            }
        }
        $category->entries()->whereNotNull('external_id')->whereNotIn('external_id', array_column($entries, 'external_id'))->update(['status' => 'withdrawn']);
    }

    /** @param array<int, array<int, int>> $pairs */
    private function validateBracket(Category $category, CompetitionRound $round, array $pairs): void
    {
        if ($pairs === []) {
            return;
        }
        $ids = array_merge(...$pairs);
        $this->setup->check(count($ids) >= 2 && count($ids) === count(array_unique($ids)), 'تعداد ورودی‌ها یا تکرار آن‌ها در مرحله معتبر نیست.');
        if ($category->format === 'knockout') {
            $size = 2 ** (int) ceil(log(count($ids), 2));
            $byes = count(array_filter($pairs, fn ($pair) => count($pair) === 1));
            $this->setup->check(count($pairs) === intdiv($size, 2) && $byes === $size - count($ids) && $category->planned_round_count - $round->sequence + 1 === (int) ceil(log(count($ids), 2)), 'جدول تک‌حذفی با تعداد ورودی‌ها و مراحل باقیمانده سازگار نیست.');
        }
    }

    private function clearPendingBouts(CompetitionRound $round): void
    {
        foreach ($round->bouts()->get() as $bout) {
            $bout->performances()->delete();
            $bout->judges()->delete();
            $bout->entries()->detach();
            $bout->delete();
        }
    }

    private function fingerprint(array $value): string
    {
        $normalize = function (array $items) use (&$normalize): array {
            if (! array_is_list($items)) {
                ksort($items);
            }
            foreach ($items as $key => $item) {
                $items[$key] = is_array($item) ? $normalize($item) : $item;
            }

            return $items;
        };

        return hash('sha256', json_encode($normalize($value), JSON_THROW_ON_ERROR));
    }
}
