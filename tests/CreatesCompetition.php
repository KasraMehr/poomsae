<?php

namespace Tests;

use App\Actions\CalculateScore;
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
    protected function competition(int $judgeCount = 5, int $entryCount = 2, string $format = 'knockout', int $discardEachEnd = 1, ?int $plannedRoundCount = null, bool $allowRepetition = true, string $drawTiming = 'morning'): array
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $tournament = Tournament::factory()->create(['created_by' => $admin->id]);
        $setup = app(CompetitionSetup::class);
        $setup->court($admin, $tournament, ['name' => 'زمین یک']);
        $categoryData = [
            'name' => 'انفرادی', 'gender' => 'open', 'minimum_age' => 10, 'maximum_age' => 40, 'format' => $format,
            'judge_count' => $judgeCount, 'accuracy_max' => 400, 'discard_each_end' => $discardEachEnd, 'rules_acknowledged' => true, 'form_names' => ['فرم اول', 'فرم دوم'],
        ];
        if ($plannedRoundCount !== null) {
            $categoryData = [...$categoryData, 'planned_round_count' => $plannedRoundCount, 'allow_form_repetition' => $allowRepetition, 'form_draw_timing' => $drawTiming, 'form_names' => array_map(fn ($number) => 'پومسه '.$number, range(1, 8))];
        }
        $setup->category($admin, $tournament, $categoryData);
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
                'request_id' => (string) Str::uuid(), 'expected_version' => $performance->fresh()->version, 'expected_revision' => 0, ...$this->detailedScoreInput($accuracy, $presentation),
            ]);
        }
    }

    /** @return array{accuracy:string, presentation:string, accuracy_penalties:array<int,string>, presentation_components:array<int,string>} */
    protected function detailedScoreInput(string $accuracy, string $presentation): array
    {
        $calculator = app(CalculateScore::class);
        $deductions = 400 - $calculator->hundredths($accuracy);
        if ($deductions < 0 || $deductions % 10 !== 0) {
            throw new \InvalidArgumentException('Test accuracy must be reachable using 0.10 and 0.30 deductions.');
        }
        $penalties = [];
        while ($deductions >= 30) {
            $penalties[] = '0.30';
            $deductions -= 30;
        }
        while ($deductions >= 10) {
            $penalties[] = '0.10';
            $deductions -= 10;
        }

        $remaining = $calculator->hundredths($presentation);
        $components = [];
        for ($index = 0; $index < 3; $index++) {
            $component = min(200, $remaining);
            $components[] = number_format($component / 100, 2, '.', '');
            $remaining -= $component;
        }
        if ($remaining !== 0) {
            throw new \InvalidArgumentException('Test presentation exceeds three two-point components.');
        }

        return ['accuracy' => $accuracy, 'presentation' => $presentation, 'accuracy_penalties' => $penalties, 'presentation_components' => $components];
    }
}
