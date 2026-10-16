<?php

namespace Tests\Feature;

use App\Actions\CompetitionConsole;
use App\Actions\CompetitionDisplay;
use App\Actions\CompetitionSetup;
use App\Actions\RunCompetition;
use App\Actions\ScheduleRound;
use App\Models\Performance;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestWith;
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
            'gender' => 'male', 'minimum_age' => 10, 'maximum_age' => 40,
            'format' => $format, 'judge_count' => 5, 'discard_each_end' => 1,
            'accuracy_max' => $discipline === 'freestyle' ? 600 : 400, 'rules_acknowledged' => true,
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
                    'expected_revision' => 0, 'accuracy' => $item->id === $performance->id ? '5.00' : '4.00',
                    'presentation' => '3.00', 'accuracy_components' => $item->id === $performance->id ? ['2.00', '2.00', '1.00'] : ['2.00', '1.00', '1.00'],
                    'presentation_penalties' => ['0.30', '0.30', '0.30', '0.10'],
                ])->assertSessionHasNoErrors();
            }
            $run->command($fixture['admin'], $fixture['tournament'], $item, ['command' => 'approve', 'expected_version' => $item->fresh()->version]);
        }

        $this->assertSame('completed', $bout->fresh()->status);
        $this->assertSame($performance->entry_id, $bout->fresh()->winner_entry_id);
        $this->assertDatabaseCount('results', 2);
    }

    public function test_freestyle_individual_allows_optional_music_and_schedules_one_per_entry(): void
    {
        Storage::fake('local');
        $fixture = $this->fixture('freestyle', 'individual');
        $url = '/tournaments/'.$fixture['tournament']->id.'/categories/'.$fixture['category']->id.'/entries';

        // Entry without music succeeds (null/blank music)
        $this->post($url, ['first_name' => 'بی', 'last_name' => 'موسیقی', 'birth_date' => '2005-01-01', 'gender' => 'male'])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        // Entry with music also succeeds
        $this->register($fixture, 2, 1, true);

        $entries = $fixture['category']->entries()->orderBy('id')->get();
        $this->assertCount(2, $entries);
        $this->assertNull($entries[0]->music_path);
        $this->assertNotNull($entries[1]->music_path);

        $this->schedule($fixture);

        $this->assertDatabaseCount('performances', 2);
        $this->assertSame(1, $fixture['category']->fresh()->forms_per_round);

        // Performance without music can start and run without errors
        $performances = Performance::whereIn('entry_id', $entries->pluck('id'))->orderBy('id')->get();
        $this->assertCount(2, $performances);
        $firstPerf = $performances->firstWhere('entry_id', $entries[0]->id);
        $secondPerf = $performances->firstWhere('entry_id', $entries[1]->id);
        $this->assertNull($firstPerf->music_path);
        $this->assertNotNull($secondPerf->music_path);

        $run = app(RunCompetition::class);
        $scheduledFirst = $performances->first();
        $run->command($fixture['admin'], $fixture['tournament'], $scheduledFirst, ['command' => 'start', 'expected_version' => $scheduledFirst->version]);
        $this->assertSame('running', $scheduledFirst->fresh()->status);
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
            'expected_revision' => 0, 'accuracy' => '6.01', 'presentation' => '4.00',
            'accuracy_components' => ['2.01', '2.00', '2.00'], 'presentation_penalties' => [],
        ])->assertSessionHasErrors('operation');
        $this->assertDatabaseCount('score_sheets', 0);
    }

    #[TestWith(['knockout', 'execution_mode', 'simultaneous', 'اجراهای ابداعی فقط نوبتی برگزار می‌شوند؛ اجرای همزمان دو حریف مجاز نیست.'])]
    #[TestWith(['round_robin', 'execution_mode', 'simultaneous', 'اجراهای ابداعی فقط نوبتی برگزار می‌شوند؛ اجرای همزمان دو حریف مجاز نیست.'])]
    #[TestWith(['knockout', 'performance_order', 'phased', 'در ابداعی هر ورودی در هر مرحله یک اجرا دارد و ورودی‌ها به‌ترتیب اجرا می‌کنند.'])]
    #[TestWith(['round_robin', 'performance_order', 'phased', 'در ابداعی هر ورودی در هر مرحله یک اجرا دارد و ورودی‌ها به‌ترتیب اجرا می‌کنند.'])]
    public function test_freestyle_settings_reject_simultaneous_and_two_form_orders(string $format, string $field, string $value, string $message): void
    {
        $fixture = $this->fixture('freestyle', 'individual', $format);
        $data = [
            'name' => 'ردهٔ ابداعی جدید', 'discipline' => 'freestyle', 'entry_type' => 'individual',
            'gender' => 'male', 'format' => $format, 'judge_count' => 5,
            'discard_each_end' => 1, 'rules_acknowledged' => true, $field => $value,
        ];

        $this->post(route('operations.category', $fixture['tournament']), $data)->assertSessionHasErrors([$field => $message]);

        $this->assertDatabaseCount('categories', 1);
        $this->assertDatabaseCount('scoring_rule_sets', 1);
    }

    #[TestWith(['individual', 1])]
    #[TestWith(['pair', 2])]
    public function test_legacy_freestyle_simultaneous_settings_run_opponents_one_at_a_time(string $entryType, int $memberCount): void
    {
        Storage::fake('local');
        $fixture = $this->fixture('freestyle', $entryType);
        $fixture['category']->update(['execution_mode' => 'simultaneous', 'performance_order' => 'phased']);
        $this->register($fixture, 1, $memberCount, true);
        $this->register($fixture, 2, $memberCount, true);
        $this->schedule($fixture);
        $bout = $fixture['category']->rounds()->firstOrFail()->bouts()->firstOrFail();
        [$first, $second] = $bout->performances()->orderBy('id')->get()->all();
        $display = app(CompetitionDisplay::class)->snapshot($fixture['tournament'], $fixture['admin']);
        $console = app(CompetitionConsole::class)->snapshot($fixture['tournament'], $fixture['admin']);

        $this->assertSame('alternating', $display['tournament']['categories'][0]['execution_mode']);
        $this->assertSame('consecutive', $display['tournament']['categories'][0]['performance_order']);
        $this->assertCount(2, $display['courts'][0]['upcoming']);
        $this->assertCount(1, $display['courts'][0]['upcoming'][0]['performances']);
        $this->assertNull($console['courts'][0]['queue'][0]['paired_entry_name']);

        $this->post(route('operations.command', [$fixture['tournament'], $first]), ['command' => 'start', 'expected_version' => $first->version])->assertSessionHasNoErrors();

        $this->assertSame('running', $first->fresh()->status);
        $this->assertSame('pending', $second->fresh()->status);

        $this->post(route('operations.command', [$fixture['tournament'], $first]), ['command' => 'finish', 'expected_version' => $first->fresh()->version])->assertSessionHasNoErrors();

        $this->assertSame('scoring', $first->fresh()->status);
        $this->assertSame('pending', $second->fresh()->status);

        $this->post(route('operations.command', [$fixture['tournament'], $second]), ['command' => 'start', 'expected_version' => $second->fresh()->version])
            ->assertSessionHasErrors(['operation' => 'زمین در اختیار اجرای دیگر یا رقابت تعیین‌تکلیف‌نشده است.']);

        $this->assertSame('pending', $second->fresh()->status);
        foreach ($fixture['judges'] as $judge) {
            $this->actingAs($judge)->post(route('judging.store', [$fixture['tournament'], $first]), [
                'request_id' => (string) Str::uuid(), 'expected_version' => $first->fresh()->version, 'expected_revision' => 0,
                'accuracy' => '5.00', 'presentation' => '3.00', 'accuracy_components' => ['2.00', '2.00', '1.00'],
                'presentation_penalties' => ['0.30', '0.30', '0.30', '0.10'],
            ])->assertSessionHasNoErrors();
        }
        $this->actingAs($fixture['admin'])->post(route('operations.command', [$fixture['tournament'], $first]), ['command' => 'approve', 'expected_version' => $first->fresh()->version])->assertSessionHasNoErrors();
        $this->post(route('operations.command', [$fixture['tournament'], $second]), ['command' => 'start', 'expected_version' => $second->fresh()->version])->assertSessionHasNoErrors();

        $this->assertSame('approved', $first->fresh()->status);
        $this->assertSame('running', $second->fresh()->status);
        $this->assertDatabaseCount('performances', 2);
    }

    public function test_legacy_freestyle_phased_round_robin_has_one_sequential_performance_per_entry(): void
    {
        Storage::fake('local');
        $fixture = $this->fixture('freestyle', 'individual', 'round_robin');
        $fixture['category']->update(['execution_mode' => 'simultaneous', 'performance_order' => 'phased']);
        $this->register($fixture, 1, 1, true);
        $this->register($fixture, 2, 1, true);
        $this->register($fixture, 3, 1, true);
        $this->schedule($fixture);

        $display = app(CompetitionDisplay::class)->snapshot($fixture['tournament'], $fixture['admin']);

        $this->assertSame('alternating', $display['tournament']['categories'][0]['execution_mode']);
        $this->assertSame('consecutive', $display['tournament']['categories'][0]['performance_order']);
        $this->assertSame([1, 2, 3], array_column($display['courts'][0]['upcoming'], 'bout_sequence'));
        $this->assertSame([1, 1, 1], array_column($display['courts'][0]['upcoming'], 'form_number'));
        $this->assertDatabaseCount('performances', 3);

        $lastPerformance = $fixture['category']->rounds()->firstOrFail()->bouts()->orderByDesc('sequence')->firstOrFail()->performances()->firstOrFail();
        $this->post(route('operations.command', [$fixture['tournament'], $lastPerformance]), ['command' => 'start', 'expected_version' => $lastPerformance->version])
            ->assertSessionHasErrors(['operation' => 'نوبت اجرای ابداعی رعایت نشده است؛ ابتدا نتیجهٔ اجرای قبلی را تأیید کنید.']);

        $this->assertSame('pending', $lastPerformance->fresh()->status);
    }

    #[TestWith(['knockout'])]
    #[TestWith(['round_robin'])]
    public function test_freestyle_settings_are_saved_as_sequential_even_when_setup_is_called_directly(string $format): void
    {
        $fixture = $this->fixture('freestyle', 'individual', $format);

        app(CompetitionSetup::class)->category($fixture['admin'], $fixture['tournament'], [
            'name' => 'ردهٔ ابداعی جدید', 'discipline' => 'freestyle', 'entry_type' => 'individual',
            'gender' => 'male', 'format' => $format, 'judge_count' => 5, 'discard_each_end' => 1,
            'rules_acknowledged' => true, 'execution_mode' => 'simultaneous', 'performance_order' => 'phased',
        ]);

        $this->assertDatabaseHas('categories', [
            'name' => 'ردهٔ ابداعی جدید', 'execution_mode' => 'alternating', 'performance_order' => 'consecutive', 'forms_per_round' => 1,
        ]);
    }
}
