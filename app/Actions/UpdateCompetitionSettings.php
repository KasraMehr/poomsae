<?php

namespace App\Actions;

use App\Models\Category;
use App\Models\Court;
use App\Models\Entry;
use App\Models\Tournament;
use App\Models\User;

class UpdateCompetitionSettings
{
    public function __construct(private CompetitionSetup $setup) {}

    /** @param array{name:string, venue:?string, starts_on:string, ends_on:?string, timezone:string} $data */
    public function tournament(User $actor, Tournament $tournament, array $data): void
    {
        $this->setup->locked($actor, $tournament, function (Tournament $tournament) use ($actor, $data): void {
            $before = $tournament->only(['name', 'venue', 'starts_on', 'ends_on', 'timezone']);
            $tournament->fill($data);
            if ($tournament->isDirty(['starts_on', 'ends_on', 'timezone'])) {
                $this->setup->check(! $tournament->categories()->whereHas('rounds', fn ($query) => $query->whereNotNull('forms_drawn_at')->orWhereHas('bouts'))->exists(), 'بعد از قرعه، تغییر تاریخ یا منطقهٔ زمانی مسابقه مجاز نیست.');
                foreach ($tournament->categories()->with(['scoringRuleSet', 'entries.athletes'])->get() as $category) {
                    foreach ($category->entries as $entry) {
                        $members = $entry->athletes->map(fn ($athlete): array => [...$athlete->only(['first_name', 'last_name', 'gender', 'club']), 'birth_date' => $athlete->birth_date?->format('Y-m-d')])->all();
                        $this->setup->check(collect($members)->every(fn (array $member): bool => $member['birth_date'] !== null && $member['birth_date'] <= $tournament->starts_on->format('Y-m-d')), 'تاریخ شروع جدید با تاریخ تولد ورزشکاران سازگار نیست.');
                        $this->setup->validateMembers($tournament, $category, $members, $entry->id);
                    }
                }
            }
            $tournament->save();
            $this->setup->audit($actor, $tournament, 'tournament.updated', 'tournament', $tournament->id, $tournament->only(array_keys($before)), $before);
        });
    }

    /** @param array{name:string} $data */
    public function court(User $actor, Tournament $tournament, Court $court, array $data): void
    {
        $this->setup->locked($actor, $tournament, function (Tournament $tournament) use ($actor, $court, $data): void {
            $court = Court::lockForUpdate()->findOrFail($court->id);
            abort_unless($court->tournament_id === $tournament->id, 404);
            $before = $court->only(['name']);
            $court->update($data);
            $this->setup->audit($actor, $tournament, 'court.updated', 'court', $court->id, $data, $before);
        });
    }

    /** @param array{members:list<array{id:int, first_name:string, last_name:string, birth_date:string, gender:string, club:?string}>} $data */
    public function entry(User $actor, Tournament $tournament, Entry $entry, array $data): void
    {
        $this->setup->locked($actor, $tournament, function (Tournament $tournament) use ($actor, $entry, $data): void {
            $entry = Entry::lockForUpdate()->findOrFail($entry->id);
            $category = Category::with('scoringRuleSet')->findOrFail($entry->category_id);
            abort_unless($category->tournament_id === $tournament->id, 404);
            $this->setup->check($category->management_source === null, 'اطلاعات این ورودی از سیستم مدیریت مسابقات دریافت می‌شود.');
            $athletes = $entry->athletes()->orderByPivot('position')->get();
            $members = $data['members'];
            $this->setup->check($athletes->pluck('id')->all() === array_map(fn (array $member): int => (int) $member['id'], $members), 'اعضای ورودی و ترتیب آن‌ها قابل جایگزینی نیستند.');
            $registrationLocked = $category->rounds()->whereHas('bouts')->exists();
            $before = ['display_name' => $entry->display_name, 'members' => []];
            foreach ($athletes as $index => $athlete) {
                $this->setup->check(! $athlete->entries()->where('entries.id', '!=', $entry->id)->exists(), 'ورزشکار مشترک بین چند ورودی باید از سیستم مدیریت اصلاح شود.');
                $before['members'][] = [...$athlete->only(['id', 'first_name', 'last_name', 'gender', 'club', 'country_code']), 'birth_date' => $athlete->birth_date?->format('Y-m-d')];
                if ($registrationLocked) {
                    $this->setup->check($members[$index]['birth_date'] === $athlete->birth_date?->format('Y-m-d') && $members[$index]['gender'] === $athlete->gender, 'بعد از قرعه فقط نام و باشگاه ورزشکار قابل اصلاح است.');
                }
            }
            $this->setup->validateMembers($tournament, $category, $members, $entry->id);
            foreach ($athletes as $index => $athlete) {
                $athlete->update(collect($members[$index])->only(['first_name', 'last_name', 'birth_date', 'gender', 'club', 'country_code'])->all());
            }
            $entry->update(['display_name' => implode(' / ', array_map(fn (array $member): string => $member['first_name'].' '.$member['last_name'], $members))]);
            $this->setup->audit($actor, $tournament, 'entry.updated', 'entry', $entry->id, ['display_name' => $entry->display_name, 'members' => $members], $before);
        });
    }
}
