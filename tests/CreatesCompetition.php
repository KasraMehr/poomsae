<?php

namespace Tests;

use App\Actions\CompetitionSetup;
use App\Actions\RunCompetition;
use App\Actions\ScheduleRound;
use App\Actions\SubmitScore;
use App\Models\Performance;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Support\Str;

trait CreatesCompetition
{
    protected function competition(int $judgeCount = 5, int $entryCount = 2, string $format = 'knockout', int $discardEachEnd = 1): array
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $tournament = Tournament::factory()->create(['created_by' => $admin->id]);
        $setup = app(CompetitionSetup::class);
        $setup->court($admin, $tournament, ['name' => 'زمین یک']);
        $setup->category($admin, $tournament, [
            'name' => 'انفرادی', 'gender' => 'open', 'minimum_age' => 10, 'maximum_age' => 40, 'format' => $format,
            'judge_count' => $judgeCount, 'accuracy_max' => 300, 'discard_each_end' => $discardEachEnd, 'rules_acknowledged' => true, 'form_names' => ['فرم اول', 'فرم دوم'],
        ]);
        $category = $tournament->categories()->firstOrFail();
        for ($i = 0; $i < $entryCount; $i++) {
            $setup->entry($admin, $tournament, $category, ['first_name' => 'ورزشکار'.$i, 'last_name' => 'آزمایشی', 'birth_date' => '2005-01-01', 'gender' => 'male', 'club' => 'باشگاه']);
        }
        foreach ($category->entries()->get() as $entry) {
            $setup->entryStatus($admin, $tournament, $entry, 'checked_in');
        }
        $judges = User::factory()->count($judgeCount)->create();
        foreach ($judges as $judge) {
            $tournament->users()->attach($judge->id, ['role' => 'judge']);
        }

        return compact('admin', 'tournament', 'category', 'judges');
    }

    protected function schedule(array $fixture): void
    {
        app(ScheduleRound::class)->handle($fixture['admin'], $fixture['tournament'], $fixture['category'], [
            'court_id' => $fixture['tournament']->courts()->firstOrFail()->id, 'judge_ids' => $fixture['judges']->pluck('id')->all(),
        ]);
    }

    protected function startScoring(array $fixture, Performance $performance): void
    {
        $run = app(RunCompetition::class);
        $run->command($fixture['admin'], $fixture['tournament'], $performance, ['command' => 'start', 'expected_version' => $performance->fresh()->version]);
        $run->command($fixture['admin'], $fixture['tournament'], $performance, ['command' => 'finish', 'expected_version' => $performance->fresh()->version]);
    }

    protected function scoreAll(array $fixture, Performance $performance, string $accuracy = '2.50', string $presentation = '6.00'): void
    {
        foreach ($fixture['judges'] as $judge) {
            app(SubmitScore::class)->handle($judge, $fixture['tournament'], $performance, [
                'request_id' => (string) Str::uuid(), 'expected_version' => $performance->fresh()->version, 'expected_revision' => 0, 'accuracy' => $accuracy, 'presentation' => $presentation,
            ]);
        }
    }
}
