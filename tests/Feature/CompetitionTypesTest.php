<?php

namespace Tests\Feature;

use App\Actions\CompetitionSetup;
use App\Actions\RunCompetition;
use App\Actions\ScheduleRound;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class CompetitionTypesTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(string $discipline, string $entryType, string $format = 'knockout'): array
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $tournament = Tournament::factory()->create(['created_by' => $admin->id]);
        $setup = app(CompetitionSetup::class);
        $setup->court($admin, $tournament, ['name' => 'زمین یک']);
        $judges = User::factory()->count(5)->create();
        foreach ($judges as $judge) {
            $tournament->users()->attach($judge->id, ['role' => 'judge']);
        }
        $data = [
            'name' => 'ردهٔ آزمایشی', 'discipline' => $discipline, 'entry_type' => $entryType,
            'gender' => 'open', 'minimum_age' => 10, 'maximum_age' => 40,
            'format' => $format, 'judge_count' => 5, 'discard_each_end' => 1,
            'accuracy_max' => $discipline === 'freestyle' ? 1000 : 400, 'rules_acknowledged' => true,
        ];
        if ($discipline === 'recognized') {
            $data['form_names'] = ['فرم یک', 'فرم دو'];
        }
        $this->actingAs($admin)->post('/tournaments/'.$tournament->id.'/categories', $data)->assertSessionHasNoErrors();

        return compact('admin', 'tournament', 'judges') + ['category' => $tournament->categories()->firstOrFail()];
    }

    private function register(array $fixture, int $index, int $memberCount, bool $freestyle = false): void
    {
        $members = [];
        for ($member = 0; $member < $memberCount; $member++) {
            $members[] = ['first_name' => 'ورزشکار'.$index.$member, 'last_name' => 'آزمایشی', 'birth_date' => '2005-01-01', 'gender' => 'male', 'club' => 'باشگاه'];
        }
        $data = $memberCount === 1 ? $members[0] : ['members' => $members];
        if ($freestyle) {
            $data['music'] = UploadedFile::fake()->create('track.mp3', 100, 'audio/mpeg');
        }
        $this->post('/tournaments/'.$fixture['tournament']->id.'/categories/'.$fixture['category']->id.'/entries', $data)->assertSessionHasNoErrors();
    }

    private function schedule(array $fixture): void
    {
        $setup = app(CompetitionSetup::class);
        foreach ($fixture['category']->entries()->get() as $entry) {
            $setup->entryStatus($fixture['admin'], $fixture['tournament'], $entry, 'checked_in');
        }
        app(ScheduleRound::class)->handle($fixture['admin'], $fixture['tournament'], $fixture['category'], [
            'court_id' => $fixture['tournament']->courts()->firstOrFail()->id,
            'judge_ids' => $fixture['judges']->pluck('id')->all(),
        ]);
    }

    public function test_standard_team_registration_schedules_two_forms_for_three_member_teams(): void
    {
        $fixture = $this->fixture('recognized', 'team');
        $this->register($fixture, 1, 3);
        $this->register($fixture, 2, 3);
        $this->schedule($fixture);

        $this->assertDatabaseCount('entry_members', 6);
        $this->assertDatabaseCount('performances', 4);
        $this->assertSame(2, $fixture['category']->fresh()->forms_per_round);
    }

    public function test_freestyle_pair_has_one_music_and_one_score_per_judge(): void
    {
        Storage::fake('local');
        $fixture = $this->fixture('freestyle', 'pair');
        $this->register($fixture, 1, 2, true);
        $this->register($fixture, 2, 2, true);
        $this->schedule($fixture);

        $this->assertDatabaseCount('entry_members', 4);
        $this->assertDatabaseCount('performances', 2);
        $bout = $fixture['category']->rounds()->firstOrFail()->bouts()->firstOrFail();
        $performance = $bout->performances()->orderBy('id')->firstOrFail();
        $this->assertNull($performance->poomsae_form_id);
        Storage::disk('local')->assertExists($performance->music_path);
        $url = '/tournaments/'.$fixture['tournament']->id.'/performances/'.$performance->id.'/music';
        $this->get($url)->assertOk();
        $this->actingAs($fixture['judges'][0])->get($url)->assertForbidden();

        $run = app(RunCompetition::class);
        foreach ($bout->performances()->orderBy('id')->get() as $item) {
            $run->command($fixture['admin'], $fixture['tournament'], $item, ['command' => 'start', 'expected_version' => $item->fresh()->version]);
            $run->command($fixture['admin'], $fixture['tournament'], $item, ['command' => 'finish', 'expected_version' => $item->fresh()->version]);
            foreach ($fixture['judges'] as $judge) {
                $this->actingAs($judge)->post('/tournaments/'.$fixture['tournament']->id.'/performances/'.$item->id.'/scores', [
                    'request_id' => (string) Str::uuid(), 'expected_version' => $item->fresh()->version,
                    'expected_revision' => 0, 'score' => $item->id === $performance->id ? '8.00' : '7.00',
                ])->assertSessionHasNoErrors();
            }
            $run->command($fixture['admin'], $fixture['tournament'], $item, ['command' => 'approve', 'expected_version' => $item->fresh()->version]);
        }

        $this->assertSame('completed', $bout->fresh()->status);
        $this->assertSame($performance->entry_id, $bout->fresh()->winner_entry_id);
        $this->assertDatabaseCount('results', 2);
    }

    public function test_freestyle_individual_requires_music_and_schedules_one_per_entry(): void
    {
        Storage::fake('local');
        $fixture = $this->fixture('freestyle', 'individual');
        $url = '/tournaments/'.$fixture['tournament']->id.'/categories/'.$fixture['category']->id.'/entries';
        $this->post($url, ['first_name' => 'بی', 'last_name' => 'موسیقی', 'birth_date' => '2005-01-01', 'gender' => 'male'])->assertSessionHasErrors('music');
        $this->register($fixture, 1, 1, true);
        $this->register($fixture, 2, 1, true);
        $this->schedule($fixture);

        $this->assertDatabaseCount('performances', 2);
        $this->assertSame(1, $fixture['category']->fresh()->forms_per_round);
    }

    public function test_freestyle_round_robin_schedules_one_solo_bout_per_entry(): void
    {
        Storage::fake('local');
        $fixture = $this->fixture('freestyle', 'individual', 'round_robin');
        $this->register($fixture, 1, 1, true);
        $this->register($fixture, 2, 1, true);
        $this->schedule($fixture);

        $bouts = $fixture['category']->rounds()->firstOrFail()->bouts()->get();
        $this->assertCount(2, $bouts);
        $this->assertTrue($bouts->every(fn ($bout) => $bout->entries()->count() === 1 && $bout->performances()->count() === 1));
    }

    public function test_freestyle_score_over_ten_is_rejected(): void
    {
        Storage::fake('local');
        $fixture = $this->fixture('freestyle', 'individual');
        $this->register($fixture, 1, 1, true);
        $this->register($fixture, 2, 1, true);
        $this->schedule($fixture);
        $performance = $fixture['category']->rounds()->firstOrFail()->bouts()->firstOrFail()->performances()->orderBy('id')->firstOrFail();
        $run = app(RunCompetition::class);
        $run->command($fixture['admin'], $fixture['tournament'], $performance, ['command' => 'start', 'expected_version' => $performance->version]);
        $run->command($fixture['admin'], $fixture['tournament'], $performance, ['command' => 'finish', 'expected_version' => $performance->fresh()->version]);

        $this->actingAs($fixture['judges'][0])->post('/tournaments/'.$fixture['tournament']->id.'/performances/'.$performance->id.'/scores', [
            'request_id' => (string) Str::uuid(), 'expected_version' => $performance->fresh()->version,
            'expected_revision' => 0, 'score' => '10.01',
        ])->assertSessionHasErrors('operation');
        $this->assertDatabaseCount('score_sheets', 0);
    }
}
