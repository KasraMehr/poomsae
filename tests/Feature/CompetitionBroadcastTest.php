<?php

namespace Tests\Feature;

use App\Actions\CompetitionSetup;
use App\Actions\ConfirmProxyScore;
use App\Actions\DrawRoundForms;
use App\Actions\ImportManagementSnapshot;
use App\Actions\RunCompetition;
use App\Actions\ScheduleRound;
use App\Actions\SubmitScore;
use App\Events\CompetitionDomainUpdated;
use App\Events\CompetitionUpdated;
use App\Models\AuditLog;
use App\Models\Bout;
use App\Models\Performance;
use App\Models\ScoreSheet;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Contracts\Broadcasting\Factory;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Mockery;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Tests\CreatesCompetition;
use Tests\TestCase;

class CompetitionBroadcastTest extends TestCase
{
    use CreatesCompetition, DatabaseMigrations;

    public function test_registration_and_check_in_notify_athletes_without_personal_data(): void
    {
        $fixture = $this->competition(5, 0);
        Event::fake([CompetitionUpdated::class, CompetitionDomainUpdated::class]);

        $entry = app(CompetitionSetup::class)->entry($fixture['admin'], $fixture['tournament'], $fixture['category'], [
            'first_name' => 'Private athlete', 'last_name' => 'Private surname', 'birth_date' => '2005-01-01', 'gender' => 'male', 'club' => 'Private club',
        ]);
        app(CompetitionSetup::class)->entryStatus($fixture['admin'], $fixture['tournament'], $entry, 'checked_in');

        $this->assertDatabaseHas('entries', ['id' => $entry->id, 'status' => 'checked_in']);
        Event::assertDispatched(CompetitionDomainUpdated::class, fn ($event) => $event->domain === 'athletes' && $event->change['action'] === 'entry.status' && $event->change['context'] === ['category_id' => $fixture['category']->id, 'entry_id' => $entry->id]);
        Event::assertDispatchedTimes(CompetitionUpdated::class, 2);
        $serialized = json_encode(Event::dispatched(CompetitionDomainUpdated::class)->all());
        $this->assertStringNotContainsString('Private athlete', $serialized);
        $this->assertStringNotContainsString('2005-01-01', $serialized);
    }

    public function test_four_court_schedule_and_execution_identify_the_affected_courts(): void
    {
        $fixture = $this->competition(5, 8);
        $panels = [['court_id' => $fixture['tournament']->courts()->firstOrFail()->id, 'judge_ids' => $fixture['judges']->pluck('id')->all()]];
        for ($number = 2; $number <= 4; $number++) {
            app(CompetitionSetup::class)->court($fixture['admin'], $fixture['tournament'], ['name' => 'Court '.$number]);
            $judges = User::factory()->count(5)->create();
            foreach ($judges as $judge) {
                $fixture['tournament']->users()->attach($judge, ['role' => 'judge']);
            }
            $panels[] = ['court_id' => $fixture['tournament']->courts()->orderByDesc('id')->firstOrFail()->id, 'judge_ids' => $judges->pluck('id')->all()];
        }
        Event::fake([CompetitionUpdated::class, CompetitionDomainUpdated::class]);

        app(ScheduleRound::class)->handle($fixture['admin'], $fixture['tournament'], $fixture['category'], ['courts' => $panels]);
        $round = $fixture['category']->rounds()->firstOrFail();
        $courtIds = array_column($panels, 'court_id');
        Event::assertDispatched(CompetitionDomainUpdated::class, fn ($event) => $event->domain === 'draw' && $event->change['context'] === ['category_id' => $fixture['category']->id, 'round_id' => $round->id, 'court_ids' => $courtIds]);

        foreach ($round->bouts()->orderBy('sequence')->get() as $bout) {
            $performance = $bout->performances()->orderBy('id')->firstOrFail();
            app(RunCompetition::class)->command($fixture['admin'], $fixture['tournament'], $performance, ['command' => 'start', 'expected_version' => 1]);
            Event::assertDispatched(CompetitionDomainUpdated::class, fn ($event) => $event->domain === 'courts' && $event->change['action'] === 'performance.start' && $event->change['context']['court_ids'] === [$bout->court_id] && $event->change['context']['performance_id'] === $performance->id);
        }
        $this->assertSame(4, Performance::where('status', 'running')->count());
        $bouts = $round->bouts()->orderBy('sequence')->get();
        foreach ($bouts as $index => $bout) {
            app(RunCompetition::class)->resolve($fixture['admin'], $fixture['tournament'], $bout, [
                'decision_type' => 'walkover', 'confirmed' => true, 'winner_entry_id' => $bout->entries()->firstOrFail()->id, 'reason' => 'Confirmed withdrawal',
            ]);
            if ($index < 3) {
                Event::assertNotDispatched(CompetitionDomainUpdated::class, fn ($event) => $event->change['action'] === 'round.completed');
            }
        }
        Event::assertDispatched(CompetitionDomainUpdated::class, fn ($event) => $event->domain === 'results' && $event->change['action'] === 'round.completed' && $event->change['context']['court_ids'] === $courtIds);
        $this->assertSame('completed', $round->fresh()->status);
    }

    public function test_form_draw_notifies_judging_and_scoreboard_for_the_stage(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 1)->setTime(6, 0));
        $fixture = $this->competition(5, 2, 'knockout', 1, 1);
        Event::fake([CompetitionUpdated::class, CompetitionDomainUpdated::class]);

        app(DrawRoundForms::class)->handle($fixture['admin'], $fixture['tournament'], $fixture['category']);

        $round = $fixture['category']->rounds()->firstOrFail();
        $this->assertCount(2, $round->form_sequence);
        foreach (['draw', 'judging', 'console', 'scoreboard'] as $domain) {
            Event::assertDispatched(CompetitionDomainUpdated::class, fn ($event) => $event->domain === $domain && $event->change['action'] === 'round.forms_drawn' && $event->change['context']['round_id'] === $round->id);
        }
    }

    public function test_score_notifications_are_private_and_retries_do_not_send_again(): void
    {
        $fixture = $this->competition();
        $this->schedule($fixture);
        $performance = Performance::firstOrFail();
        $this->startScoring($fixture, $performance);
        $this->freezeTime();
        Event::fake([CompetitionUpdated::class, CompetitionDomainUpdated::class]);
        $payload = ['request_id' => (string) Str::uuid(), 'expected_version' => $performance->fresh()->version, 'expected_revision' => 0, ...$this->detailedScoreInput('2.50', '6.00')];

        $submit = app(SubmitScore::class);
        $response = $submit->handle($fixture['judges'][0], $fixture['tournament'], $performance, $payload);
        $this->assertSame($response, $submit->handle($fixture['judges'][0], $fixture['tournament'], $performance, $payload));
        $this->assertDatabaseCount('score_sheets', 1);
        Event::assertDispatchedTimes(CompetitionUpdated::class, 1);
        Event::assertDispatchedTimes(CompetitionDomainUpdated::class, 3);
        $event = Event::dispatched(CompetitionDomainUpdated::class)->first()[0];
        $audit = AuditLog::where('action', 'score.submitted')->firstOrFail();
        $bout = $performance->bout()->firstOrFail();
        $this->assertSame([
            'schema_version' => 1, 'tournament_id' => $fixture['tournament']->id, 'revision' => $audit->id,
            'action' => 'score.submitted', 'subject' => ['type' => 'score_sheet', 'id' => $response['score_sheet_id']],
            'context' => ['category_id' => $fixture['category']->id, 'round_id' => $bout->competition_round_id, 'bout_id' => $bout->id, 'court_ids' => [$bout->court_id], 'performance_id' => $performance->id, 'entry_id' => $performance->entry_id],
            'occurred_at' => $audit->created_at->toISOString(),
        ], $event->broadcastWith());
        $this->assertSame('private-tournaments.'.$fixture['tournament']->id, $event->broadcastOn()[0]->name);
        $this->assertDatabaseCount('results', 0);
    }

    public function test_proxy_confirmation_notifies_received_score_without_publishing_result(): void
    {
        $fixture = $this->competition();
        $this->schedule($fixture);
        $performance = Performance::firstOrFail();
        $this->startScoring($fixture, $performance);
        $assignment = $performance->bout()->firstOrFail()->judges()->firstOrFail();
        Event::fake([CompetitionUpdated::class, CompetitionDomainUpdated::class]);

        app(SubmitScore::class)->handle($fixture['admin'], $fixture['tournament'], $performance, [
            'request_id' => (string) Str::uuid(), 'expected_version' => $performance->fresh()->version, 'expected_revision' => 0,
            'judge_assignment_id' => $assignment->id, 'reason' => 'Tablet unavailable', ...$this->detailedScoreInput('2.50', '6.00'),
        ], true);
        $sheet = ScoreSheet::firstOrFail();
        app(ConfirmProxyScore::class)->handle($fixture['admin'], $fixture['tournament'], $performance, $sheet, 1);

        $this->assertSame('submitted', $sheet->fresh()->status);
        $this->assertDatabaseCount('results', 0);
        foreach (['score.proxy_submitted', 'score.proxy_confirmed'] as $action) {
            Event::assertDispatched(CompetitionDomainUpdated::class, fn ($event) => $event->domain === 'console' && $event->change['action'] === $action && $event->change['context']['performance_id'] === $performance->id);
        }
    }

    public function test_approval_and_completion_notify_results_advancement_and_final(): void
    {
        $fixture = $this->competition();
        $this->schedule($fixture);
        $bout = Bout::firstOrFail();
        $winner = $bout->entries()->firstOrFail()->id;
        Event::fake([CompetitionUpdated::class, CompetitionDomainUpdated::class]);

        foreach ($bout->performances()->orderBy('form_number')->orderBy('id')->get() as $performance) {
            $this->startScoring($fixture, $performance);
            $this->scoreAll($fixture, $performance, '2.50', $performance->entry_id === $winner ? '6.00' : '5.00');
            app(RunCompetition::class)->command($fixture['admin'], $fixture['tournament'], $performance, ['command' => 'approve', 'expected_version' => $performance->fresh()->version]);
        }
        app(RunCompetition::class)->complete($fixture['admin'], $fixture['tournament']);

        $this->assertSame($winner, $bout->fresh()->winner_entry_id);
        $this->assertSame('completed', $fixture['tournament']->fresh()->status);
        foreach (['performance.approve', 'bout.completed', 'round.completed', 'tournament.completed'] as $action) {
            Event::assertDispatched(CompetitionDomainUpdated::class, fn ($event) => $event->domain === 'results' && $event->change['action'] === $action);
        }
    }

    public function test_events_wait_for_outer_commit_and_disappear_on_rollback(): void
    {
        $fixture = $this->competition(5, 0);
        Queue::fake([BroadcastEvent::class]);
        DB::beginTransaction();
        app(CompetitionSetup::class)->court($fixture['admin'], $fixture['tournament'], ['name' => 'Committed court']);
        Queue::assertNothingPushed();
        DB::commit();
        Queue::assertPushed(BroadcastEvent::class, 3);

        Queue::fake([BroadcastEvent::class]);
        DB::beginTransaction();
        app(CompetitionSetup::class)->court($fixture['admin'], $fixture['tournament'], ['name' => 'Rolled back court']);
        DB::rollBack();
        Queue::assertNothingPushed();
        $this->assertDatabaseMissing('courts', ['name' => 'Rolled back court']);
        $this->assertDatabaseHas('courts', ['name' => 'Committed court']);
    }

    public function test_member_and_category_changes_notify_their_sections(): void
    {
        $fixture = $this->competition(5, 0);
        $judge = User::factory()->create();
        Event::fake([CompetitionUpdated::class, CompetitionDomainUpdated::class]);

        app(CompetitionSetup::class)->member($fixture['admin'], $fixture['tournament'], ['email' => $judge->email, 'role' => 'judge']);
        app(CompetitionSetup::class)->category($fixture['admin'], $fixture['tournament'], [
            'name' => 'Updated category', 'gender' => 'male', 'minimum_age' => 10, 'maximum_age' => 40, 'format' => 'knockout',
            'judge_count' => 5, 'discard_each_end' => 1, 'rules_acknowledged' => true, 'form_names' => ['Form one', 'Form two'],
        ], $fixture['category']);

        $this->assertSame('Updated category', $fixture['category']->fresh()->name);
        Event::assertDispatched(CompetitionDomainUpdated::class, fn ($event) => $event->domain === 'members' && $event->change['action'] === 'member.added' && $event->change['subject']['id'] === $judge->id);
        Event::assertDispatched(CompetitionDomainUpdated::class, fn ($event) => $event->domain === 'draw' && $event->change['action'] === 'category.saved' && $event->change['context']['category_id'] === $fixture['category']->id);
    }

    public function test_management_import_notifies_registration_and_draw_without_snapshot_data(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 1)->setTime(6, 0));
        $fixture = $this->competition(5, 0, 'knockout', 1, 1);
        $court = $fixture['tournament']->courts()->firstOrFail();
        Event::fake([CompetitionUpdated::class, CompetitionDomainUpdated::class]);

        app(ImportManagementSnapshot::class)->handle($fixture['admin'], $fixture['tournament'], $fixture['category'], [
            'source' => 'management', 'version' => 1, 'expected_version' => 0, 'draw_method' => 'random',
            'category' => ['name' => 'Imported category', 'minimum_age' => 18, 'maximum_age' => 30],
            'entries' => array_map(fn ($number) => [
                'external_id' => 'private-athlete-'.$number, 'status' => 'checked_in',
                'members' => [['first_name' => 'Athlete '.$number, 'last_name' => 'Surname', 'birth_date' => '2005-01-01', 'gender' => 'male']],
            ], [1, 2]),
            'stages' => [[
                'sequence' => 1, 'name' => 'Final', 'form_ids' => $fixture['category']->forms()->orderBy('id')->limit(2)->pluck('poomsae_forms.id')->all(),
                'court_id' => $court->id, 'judge_ids' => $fixture['judges']->pluck('id')->all(), 'bouts' => [['private-athlete-1', 'private-athlete-2']],
            ]],
        ]);

        $this->assertDatabaseCount('performances', 4);
        foreach (['athletes', 'draw', 'courts', 'judging', 'console', 'scoreboard', 'results'] as $domain) {
            Event::assertDispatched(CompetitionDomainUpdated::class, fn ($event) => $event->domain === $domain && $event->change['action'] === 'management.snapshot_imported' && $event->change['context']['court_ids'] === [$court->id]);
        }
        $this->assertStringNotContainsString('private-athlete', json_encode(Event::dispatched(CompetitionDomainUpdated::class)->all()));
    }

    public function test_corrected_published_result_notifies_results_with_performance_context(): void
    {
        $fixture = $this->competition();
        $this->schedule($fixture);
        $performance = Performance::firstOrFail();
        $this->startScoring($fixture, $performance);
        $this->scoreAll($fixture, $performance);
        app(RunCompetition::class)->command($fixture['admin'], $fixture['tournament'], $performance, ['command' => 'approve', 'expected_version' => $performance->fresh()->version]);
        Event::fake([CompetitionUpdated::class, CompetitionDomainUpdated::class]);

        app(SubmitScore::class)->handle($fixture['judges'][0], $fixture['tournament'], $performance, [
            'request_id' => (string) Str::uuid(), 'expected_version' => $performance->fresh()->version, 'expected_revision' => 1,
            'reason' => 'Correct input error', ...$this->detailedScoreInput('2.50', '5.50'),
        ]);

        $this->assertDatabaseHas('score_sheets', ['performance_id' => $performance->id, 'revision' => 2]);
        Event::assertDispatched(CompetitionDomainUpdated::class, fn ($event) => $event->domain === 'results' && $event->change['action'] === 'result.corrected' && $event->change['context']['performance_id'] === $performance->id);
    }

    public function test_failed_command_does_not_notify_or_change_state(): void
    {
        $fixture = $this->competition();
        $this->schedule($fixture);
        $performance = Performance::firstOrFail();
        Event::fake([CompetitionUpdated::class, CompetitionDomainUpdated::class]);

        $this->actingAs($fixture['operator'])->post('/tournaments/'.$fixture['tournament']->id.'/performances/'.$performance->id.'/command', ['command' => 'finish', 'expected_version' => 1])->assertSessionHasErrors('operation');

        $this->assertSame('pending', $performance->fresh()->status);
        Event::assertNotDispatched(CompetitionUpdated::class);
        Event::assertNotDispatched(CompetitionDomainUpdated::class);
    }

    public function test_queued_notification_publishes_private_mercure_update(): void
    {
        $fixture = $this->competition(5, 0);
        Queue::fake([BroadcastEvent::class]);
        app(CompetitionSetup::class)->court($fixture['admin'], $fixture['tournament'], ['name' => 'New court']);
        Queue::assertPushed(BroadcastEvent::class, 3);
        $job = Queue::pushed(BroadcastEvent::class)->first(fn ($job) => $job->event->broadcastAs() === 'courts.updated');
        $this->assertSame(3, $job->tries);
        $this->assertSame([1, 5, 15], $job->backoff);
        $this->configureMercure();
        $hub = $this->mock(HubInterface::class);
        $hub->shouldReceive('getCookieName')->andReturn('mercure_access_token');
        $hub->shouldReceive('publish')->once()->with(Mockery::on(function (Update $update) use ($fixture): bool {
            $data = json_decode($update->getData(), true, flags: JSON_THROW_ON_ERROR);

            return $update->isPrivate() && $data['event'] === 'courts.updated'
                && $data['channels'] === ['private-tournaments.'.$fixture['tournament']->id]
                && $data['payload']['action'] === 'court.created'
                && ! isset($data['payload']['after']);
        }))->andReturn('event-id');
        Broadcast::connection('mercure')->setHub($hub);

        $job->handle(app(Factory::class));
    }

    #[TestWith(['manager', false])]
    #[TestWith(['operator', false])]
    #[TestWith(['judge', false])]
    public function test_mercure_authorizes_tournament_and_limits_presence_by_role(string $role, bool $presenceDenied): void
    {
        $this->configureMercure();
        $tournament = Tournament::factory()->create();
        $user = User::factory()->create();
        $tournament->users()->attach($user, ['role' => $role]);
        Sanctum::actingAs($user, ['tournaments:read']);

        $response = $this->postJson('/api/v1/broadcasting/auth', ['channel_names' => ['private-tournaments.'.$tournament->id, 'presence-tournaments.'.$tournament->id.'.judges']])
            ->assertOk()->assertJsonMissingPath('channel_names.0.denied')->assertJsonPath('expires_in', 300);
        $this->assertSame($presenceDenied, (bool) $response->json('channel_names.1.denied'));
        $response->assertCookie('mercure_access_token');
    }

    public function test_mercure_batch_does_not_grant_another_tournament(): void
    {
        $this->configureMercure();
        $allowed = Tournament::factory()->create();
        $denied = Tournament::factory()->create();
        $user = User::factory()->create();
        $allowed->users()->attach($user, ['role' => 'judge']);
        Sanctum::actingAs($user, ['tournaments:read']);

        $this->postJson('/api/v1/broadcasting/auth', ['channel_names' => ['private-tournaments.'.$allowed->id, 'private-tournaments.'.$denied->id, 'presence-tournaments.'.$denied->id.'.judges']])
            ->assertOk()->assertJsonMissingPath('channel_names.0.denied')->assertJsonPath('channel_names.1.denied', true)->assertJsonPath('channel_names.2.denied', true);
    }

    public function test_mercure_auth_returns_403_for_token_without_read_ability(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['scores:write']);
        $this->postJson('/api/v1/broadcasting/auth', ['channel_names' => ['private-tournaments.1']])->assertForbidden();
    }

    public function test_mercure_auth_returns_403_for_inactive_user(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => false]), ['tournaments:read']);
        $this->postJson('/api/v1/broadcasting/auth', ['channel_names' => ['private-tournaments.1']])->assertForbidden();
    }

    public function test_mercure_auth_returns_401_without_authentication(): void
    {
        $this->postJson('/api/v1/broadcasting/auth', ['channel_names' => ['private-tournaments.1']])->assertUnauthorized();
    }

    public function test_mobile_snapshot_lists_domain_events_for_subscribers(): void
    {
        $fixture = $this->competition(5, 0);
        Sanctum::actingAs($fixture['admin'], ['tournaments:read']);
        $this->getJson('/api/v1/tournaments/'.$fixture['tournament']->id.'/competition')
            ->assertOk()->assertJsonPath('data.revision', AuditLog::where('tournament_id', $fixture['tournament']->id)->max('id'))
            ->assertJsonPath('realtime.events', ['scoreboard.updated', 'console.updated', 'judging.updated', 'athletes.updated', 'draw.updated', 'results.updated', 'courts.updated', 'members.updated', 'appeals.updated']);
    }

    private function configureMercure(): void
    {
        config([
            'broadcasting.default' => 'mercure',
            'broadcasting.connections.mercure.url' => 'http://localhost/.well-known/mercure',
            'broadcasting.connections.mercure.public_url' => 'http://localhost/.well-known/mercure',
            'broadcasting.connections.mercure.secret' => str_repeat('t', 32),
            'broadcasting.connections.mercure.cookie_name' => 'mercure_access_token',
        ]);
        require base_path('routes/channels.php');
    }

    public function test_same_origin_hub_authorization_uses_the_lan_host(): void
    {
        $this->configureMercure();
        config(['broadcasting.connections.mercure.public_url' => '/.well-known/mercure']);
        $user = User::factory()->create();
        $tournament = Tournament::factory()->create();
        $tournament->users()->attach($user, ['role' => 'judge']);
        Sanctum::actingAs($user, ['tournaments:read']);

        $this->postJson('http://192.168.1.100:8000/api/v1/broadcasting/auth', ['channel_names' => ['private-tournaments.'.$tournament->id]])
            ->assertOk()->assertJsonMissingPath('channel_names.0.denied')->assertCookie('mercure_access_token');
        $this->assertSame('http://192.168.1.100:8000/.well-known/mercure', Broadcast::connection()->getHub()->getPublicUrl());
    }

    public function test_old_queued_event_without_change_metadata_still_broadcasts(): void
    {
        $event = new CompetitionUpdated(1, 2);
        $legacyEvent = (new \ReflectionClass(CompetitionUpdated::class))->newInstanceWithoutConstructor();
        $legacyEvent->tournamentId = $event->tournamentId;
        $legacyEvent->auditId = $event->auditId;

        $this->assertSame(['schema_version' => 1, 'tournament_id' => 1, 'revision' => 2], $legacyEvent->broadcastWith());
    }
}
