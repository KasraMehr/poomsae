<?php

namespace Tests\Feature;

use App\Actions\ImportTkdhubDivision;
use App\Actions\RunCompetition;
use App\Actions\SubmitScore;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Performance;
use App\Models\Result;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\CreatesCompetition;
use Tests\TestCase;

class TkdhubIntegrationTest extends TestCase
{
    use CreatesCompetition, RefreshDatabase;

    private function payload(int $count = 1): array
    {
        return [
            'eventId' => 'event-123', 'divisionId' => 'division-456', 'divisionName' => 'زیر ۱۲ سال دختران',
            'birthDateFrom' => '2014-01-01', 'birthDateTo' => '2017-12-31',
            'allowedFormCodes' => [2, 3, 4, 5, 6, 7, 8, 9], 'form1Code' => 2, 'form2Code' => 3,
            'athletes' => array_map(fn (int $position): array => [
                'entryId' => 'entry-00'.$position, 'firstName' => $position === 1 ? 'سارا' : 'ورزشکار'.$position,
                'lastName' => 'احمدی', 'teamName' => 'تیم نمونه', 'birthDate' => '2016-05-12', 'drawPosition' => $position,
            ], range(1, $count)),
        ];
    }

    private function base(array $fixture): string
    {
        return '/api/v1/tournaments/'.$fixture['tournament']->id.'/tkdhub';
    }

    private function imported(int $count = 1): array
    {
        $fixture = $this->competition(5, 0);
        $fixture['category'] = app(ImportTkdhubDivision::class)->handle($fixture['admin'], $fixture['tournament'], $this->payload($count))['category'];
        $fixture['tournament'] = $fixture['tournament']->fresh();

        return $fixture;
    }

    private function remoteSheet(int $count = 1): array
    {
        $payload = $this->payload($count);

        return [
            'event' => ['id' => $payload['eventId'], 'rules' => ['accuracyMax' => '4.00', 'presentationMax' => '6.00', 'precision' => 2, 'formSelection' => 'division']],
            'division' => ['name' => $payload['divisionName'], 'competitionType' => 'individual', 'gender' => 'female',
                'birthDateFrom' => $payload['birthDateFrom'], 'birthDateTo' => $payload['birthDateTo'],
                'allowedFormCodes' => $payload['allowedFormCodes'], 'form1Code' => 2, 'form2Code' => 3],
            'divisionId' => 'division-456', 'revision' => 10, 'final' => false,
            'draw' => ['active' => true, 'entryIds' => array_column($payload['athletes'], 'entryId')],
            'rows' => array_map(fn (array $athlete): array => ['entry' => [
                'id' => $athlete['entryId'], 'firstName' => $athlete['firstName'], 'lastName' => $athlete['lastName'],
                'teamName' => $athlete['teamName'], 'birthDate' => $athlete['birthDate'], 'gender' => 'female', 'status' => 'active',
                'scores' => [
                    'form1' => ['code' => 2, 'accuracy' => null, 'presentation' => null],
                    'form2' => ['code' => 3, 'accuracy' => null, 'presentation' => null],
                ],
            ]], $payload['athletes']),
        ];
    }

    private function remoteUrl(): string
    {
        return 'https://tkdhub.example/api/v1/organizations/org-123/poomsae-events/event-123/divisions/division-456';
    }

    private function configureConnection(): void
    {
        config(['tkdhub.base_url' => 'https://tkdhub.example/api/v1', 'tkdhub.organization_id' => 'org-123', 'tkdhub.token' => 'test-secret']);
        Http::preventStrayRequests();
    }

    private function approve(array $fixture, Performance $performance, string $accuracy = '2.50', string $presentation = '6.00'): void
    {
        $this->startScoring($fixture, $performance);
        $this->scoreAll($fixture, $performance, $accuracy, $presentation);
        app(RunCompetition::class)->command($fixture['admin'], $fixture['tournament'], $performance->fresh(), [
            'command' => 'approve', 'expected_version' => $performance->fresh()->version,
        ]);
    }

    public function test_exact_payload_imports_once_and_returns_the_requested_unwrapped_results_contract(): void
    {
        $fixture = $this->competition(5, 0);
        Sanctum::actingAs($fixture['admin'], ['competition:manage', 'tournaments:read']);
        $response = $this->putJson($this->base($fixture).'/divisions', $this->payload())
            ->assertOk()->assertHeader('ETag', '"1"')->assertJsonPath('replayed', false);
        $category = Category::findOrFail($response->json('categoryId'));
        $this->assertSame('tkdhub', $category->management_source);
        $this->assertSame('event-123', $fixture['tournament']->fresh()->tkdhub_event_id);
        $this->assertSame([2, 3, 4, 5, 6, 7, 8, 9], $category->management_snapshot['allowedFormCodes']);
        $this->assertSame(5, $category->judge_count);
        $this->assertDatabaseHas('athletes', ['first_name' => 'سارا', 'club' => 'تیم نمونه']);
        $this->assertSame('2016-05-12', $category->entries()->firstOrFail()->athletes()->firstOrFail()->birth_date->format('Y-m-d'));
        $this->putJson($this->base($fixture).'/divisions', $this->payload())->assertOk()->assertJsonPath('replayed', true);
        $this->assertDatabaseCount('entries', 1);
        $this->assertDatabaseCount('draws', 1);
        $this->assertSame(1, AuditLog::where('action', 'tkdhub.division_imported')->count());
        $this->getJson($response->json('resultsUrl'))->assertExactJson([
            'eventId' => 'event-123', 'divisionId' => 'division-456', 'results' => [[
                'entryId' => 'entry-001', 'status' => 'active',
                'form1' => ['code' => 2, 'accuracy' => null, 'presentation' => null],
                'form2' => ['code' => 3, 'accuracy' => null, 'presentation' => null],
            ]],
        ]);
    }

    public function test_changed_import_cannot_delete_or_rewrite_existing_registration_even_before_scoring(): void
    {
        $fixture = $this->imported(2);
        $payload = $this->payload();
        $payload['athletes'][0]['firstName'] = 'نام جدید';
        Sanctum::actingAs($fixture['admin'], ['competition:manage']);
        $this->putJson($this->base($fixture).'/divisions', $payload)->assertConflict();
        $this->assertDatabaseCount('entries', 2);
        $this->assertDatabaseHas('athletes', ['first_name' => 'سارا']);
        $this->assertSame(1, $fixture['category']->fresh()->management_version);
        $fixture['tournament']->update(['status' => 'archived']);
        $this->putJson($this->base($fixture).'/divisions', $this->payload(2))->assertOk()->assertJsonPath('replayed', true);
    }

    public function test_imported_draw_is_scheduled_in_source_order_and_a_single_athlete_can_compete(): void
    {
        $fixture = $this->imported();
        Sanctum::actingAs($fixture['admin'], ['competition:manage']);
        $this->postJson($this->base($fixture).'/divisions/division-456/schedule', [
            'court_id' => $fixture['tournament']->courts()->firstOrFail()->id, 'judge_ids' => $fixture['judges']->pluck('id')->all(),
        ])->assertOk()->assertJsonPath('scheduled', true);
        $this->assertDatabaseCount('performances', 2);
        $this->assertSame('pending', Performance::firstOrFail()->status);
        $this->startScoring($fixture, Performance::firstOrFail());
        $this->postJson($this->base($fixture).'/divisions/division-456/schedule', [
            'court_id' => $fixture['tournament']->courts()->firstOrFail()->id, 'judge_ids' => $fixture['judges']->pluck('id')->all(),
        ])->assertUnprocessable();
    }

    public function test_imported_source_order_is_preserved_across_four_independent_courts(): void
    {
        $fixture = $this->competition(5, 0);
        $payload = $this->payload(8);
        $payload['athletes'] = array_reverse($payload['athletes']);
        $fixture['category'] = app(ImportTkdhubDivision::class)->handle($fixture['admin'], $fixture['tournament'], $payload)['category'];
        for ($number = 2; $number <= 4; $number++) {
            $fixture['tournament']->courts()->create(['name' => 'زمین '.$number]);
        }
        $additionalJudges = User::factory()->count(15)->create();
        foreach ($additionalJudges as $judge) {
            $fixture['tournament']->users()->attach($judge->id, ['role' => 'judge']);
        }
        $judges = $fixture['judges']->concat($additionalJudges)->pluck('id')->chunk(5)->values();
        $panels = $fixture['tournament']->courts()->orderBy('id')->get()->map(fn ($court, $index): array => [
            'court_id' => $court->id, 'judge_ids' => $judges[$index]->values()->all(),
        ])->all();
        Sanctum::actingAs($fixture['admin'], ['competition:manage']);
        $this->postJson($this->base($fixture).'/divisions/division-456/schedule', ['courts' => $panels])->assertOk();
        $bouts = $fixture['category']->rounds()->firstOrFail()->bouts()->with('entries')->orderBy('sequence')->get();
        $this->assertSame(array_column($this->payload(8)['athletes'], 'entryId'), $bouts->map(fn ($bout) => $bout->entries->first()->external_id)->all());
        $this->assertSame(4, $bouts->pluck('court_id')->unique()->count());
        $this->assertSame(16, Performance::count());
    }

    public function test_results_use_the_published_component_means_from_all_judges_and_preserve_zero_scores(): void
    {
        $fixture = $this->imported();
        $this->schedule($fixture);
        $performance = Performance::orderBy('id')->firstOrFail();
        $this->startScoring($fixture, $performance);
        $accuracy = ['2.00', '2.50', '3.00', '3.50', '4.00'];
        $presentation = ['3.00', '4.00', '4.01', '4.02', '6.00'];
        foreach ($fixture['judges'] as $index => $judge) {
            app(SubmitScore::class)->handle($judge, $fixture['tournament'], $performance, [
                'request_id' => (string) Str::uuid(), 'expected_version' => $performance->fresh()->version, 'expected_revision' => 0,
                ...$this->detailedScoreInput($accuracy[$index], $presentation[$index]),
            ]);
        }
        Sanctum::actingAs($fixture['admin'], ['tournaments:read']);
        $url = $this->base($fixture).'/divisions/division-456/results';
        $this->getJson($url)->assertOk()->assertJsonPath('results.0.form1.accuracy', null);
        app(RunCompetition::class)->command($fixture['admin'], $fixture['tournament'], $performance, [
            'command' => 'approve', 'expected_version' => $performance->fresh()->version,
        ]);
        $this->getJson($url)->assertOk()->assertJsonPath('results.0.form1.accuracy', '3.00')
            ->assertJsonPath('results.0.form1.presentation', '4.01')->assertJsonPath('results.0.form2.accuracy', null);
        $second = Performance::where('form_number', 2)->firstOrFail();
        $this->approve($fixture, $second, '0.00', '0.00');
        $this->getJson($url)->assertOk()->assertJsonPath('results.0.form2.accuracy', '0.00')->assertJsonPath('results.0.form2.presentation', '0.00');
    }

    public static function invalidPayloads(): array
    {
        return [
            'out of age range' => ['athletes.0.birthDate', '2013-12-31'],
            'invalid leap date' => ['athletes.0.birthDate', '2016-02-30'],
            'disallowed selected form' => ['form2Code', 16],
            'form beyond sixteen' => ['allowedFormCodes.0', 17],
            'duplicate allowed form' => ['allowedFormCodes.0', 3],
            'missing active draw' => ['athletes.0.drawPosition', null],
            'draw gap' => ['athletes.0.drawPosition', 2],
            'inverted birth range' => ['birthDateFrom', '2020-01-01'],
            'judge panel count' => ['judgeCount', 4],
            'path injection' => ['eventId', '../another-event'],
            'unexpected athlete score' => ['athletes.0.score', '9.99'],
        ];
    }

    #[DataProvider('invalidPayloads')]
    public function test_invalid_import_returns_422_without_partial_registration(string $key, mixed $value): void
    {
        $fixture = $this->competition(5, 0);
        $payload = $this->payload();
        data_set($payload, $key, $value);
        Sanctum::actingAs($fixture['admin'], ['competition:manage']);
        $this->putJson($this->base($fixture).'/divisions', $payload)->assertUnprocessable()->assertJsonStructure(['message']);
        $this->assertDatabaseCount('entries', 0);
        $this->assertDatabaseCount('draws', 0);
        $this->assertNull($fixture['tournament']->fresh()->tkdhub_event_id);
    }

    public function test_birth_date_boundaries_and_absent_entries_are_preserved(): void
    {
        $fixture = $this->competition(3, 0, 'round_robin', 0);
        $payload = $this->payload(3);
        $payload['judgeCount'] = 3;
        $payload['athletes'][0]['birthDate'] = '2014-01-01';
        $payload['athletes'][1]['birthDate'] = '2017-12-31';
        $payload['athletes'][2]['status'] = 'absent';
        $payload['athletes'][2]['drawPosition'] = null;
        Sanctum::actingAs($fixture['admin'], ['competition:manage', 'tournaments:read']);
        $response = $this->putJson($this->base($fixture).'/divisions', $payload)->assertOk();
        $this->getJson($response->json('resultsUrl'))->assertOk()->assertJsonPath('results.2.status', 'absent');
        $category = Category::findOrFail($response->json('categoryId'));
        $this->assertSame(3, $category->judge_count);
        $this->assertSame(0, $category->scoringRuleSet->definition['discard_each_end']);
    }

    public function test_three_judge_panel_rounds_the_final_mean_to_two_decimals_and_keeps_form_codes(): void
    {
        $fixture = $this->competition(3, 0, 'round_robin', 0);
        $payload = $this->payload();
        $payload['judgeCount'] = 3;
        $payload['allowedFormCodes'] = [16];
        $payload['form1Code'] = 16;
        $payload['form2Code'] = 16;
        $payload['birthDateFrom'] = null;
        $fixture['category'] = app(ImportTkdhubDivision::class)->handle($fixture['admin'], $fixture['tournament'], $payload)['category'];
        $this->schedule($fixture);
        $performance = Performance::orderBy('id')->firstOrFail();
        $this->startScoring($fixture, $performance);
        foreach ($fixture['judges'] as $index => $judge) {
            app(SubmitScore::class)->handle($judge, $fixture['tournament'], $performance, [
                'request_id' => (string) Str::uuid(), 'expected_version' => $performance->fresh()->version, 'expected_revision' => 0,
                ...$this->detailedScoreInput($index === 2 ? '2.60' : '2.50', '6.00'),
            ]);
        }
        app(RunCompetition::class)->command($fixture['admin'], $fixture['tournament'], $performance, [
            'command' => 'approve', 'expected_version' => $performance->fresh()->version,
        ]);
        Sanctum::actingAs($fixture['admin'], ['tournaments:read']);
        $this->getJson($this->base($fixture).'/divisions/division-456/results')->assertOk()
            ->assertJsonPath('results.0.form1', ['code' => 16, 'accuracy' => '2.53', 'presentation' => '6.00'])
            ->assertJsonPath('results.0.form2.code', 16);
    }

    public function test_push_limits_each_batch_to_fifty_and_returns_the_remaining_entry_ids(): void
    {
        $this->freezeTime();
        $fixture = $this->imported(51);
        $this->schedule($fixture);
        foreach (Performance::where('form_number', 1)->get() as $performance) {
            $performance->update(['status' => 'approved', 'approved_at' => now(), 'approved_by' => $fixture['admin']->id]);
            Result::create([
                'performance_id' => $performance->id, 'scoring_rule_set_id' => $fixture['category']->scoring_rule_set_id,
                'score' => '8.500000', 'published_at' => now(), 'calculation_snapshot' => [
                    'rules' => ['accuracy_max' => 400, 'presentation_max' => 600],
                    'components' => ['accuracy' => ['mean' => '2.500000'], 'presentation' => ['mean' => '6.000000']],
                ],
            ]);
        }
        $this->configureConnection();
        $endpoints = [$this->remoteUrl().'/sheet?mode=results' => Http::response($this->remoteSheet(51))];
        foreach (range(1, 50) as $position) {
            $endpoints[$this->remoteUrl().'/entries/entry-00'.$position.'/scores'] = Http::response(['revision' => 10 + $position]);
        }
        Http::fake($endpoints);
        Sanctum::actingAs($fixture['admin'], ['competition:manage']);
        $this->postJson($this->base($fixture).'/divisions/division-456/push')->assertOk()
            ->assertJsonCount(50, 'sentEntryIds')->assertJsonPath('pendingEntryIds', ['entry-0051'])->assertJsonPath('revision', 60);
        Http::assertSentCount(51);
        Http::assertNotSent(fn (ClientRequest $request): bool => str_ends_with($request->url(), '/entry-0051/scores'));
        $this->assertSame(50, AuditLog::where('action', 'tkdhub.result_sent')->count());
    }

    public function test_import_requires_authentication_manager_role_and_token_scope(): void
    {
        $fixture = $this->competition(5, 0);
        $url = $this->base($fixture).'/divisions';
        $this->putJson($url, $this->payload())->assertUnauthorized();
        Sanctum::actingAs($fixture['admin'], ['tournaments:read']);
        $this->putJson($url, $this->payload())->assertForbidden();
        Sanctum::actingAs($fixture['judges'][0], ['competition:manage']);
        $this->putJson($url, $this->payload())->assertForbidden();
        $outsider = User::factory()->create();
        Sanctum::actingAs($outsider, ['competition:manage']);
        $this->putJson($url, $this->payload())->assertForbidden();
        $this->assertDatabaseCount('entries', 0);
    }

    public function test_results_require_operator_access_and_do_not_resolve_other_tournaments_divisions(): void
    {
        $fixture = $this->imported();
        Sanctum::actingAs($fixture['judges'][0], ['tournaments:read']);
        $this->getJson($this->base($fixture).'/divisions/division-456/results')->assertForbidden();
        $other = Tournament::factory()->create(['created_by' => $fixture['admin']->id]);
        Sanctum::actingAs($fixture['admin'], ['tournaments:read']);
        $this->getJson('/api/v1/tournaments/'.$other->id.'/tkdhub/divisions/division-456/results')->assertNotFound();
        Sanctum::actingAs($fixture['admin'], ['scores:write']);
        $this->getJson($this->base($fixture).'/divisions/division-456/results')->assertForbidden();
    }

    public function test_an_event_cannot_be_attached_to_two_local_tournaments(): void
    {
        $fixture = $this->imported();
        $other = Tournament::factory()->create(['created_by' => $fixture['admin']->id]);
        Sanctum::actingAs($fixture['admin'], ['competition:manage']);
        $this->putJson('/api/v1/tournaments/'.$other->id.'/tkdhub/divisions', $this->payload())->assertConflict();
        $this->assertDatabaseCount('entries', 1);
        $this->assertNull($other->fresh()->tkdhub_event_id);
    }

    public function test_pull_reads_the_actual_tkdhub_sheet_and_preserves_gender_and_draw_order(): void
    {
        $fixture = $this->competition(5, 0);
        $this->configureConnection();
        $sheet = $this->remoteSheet(2);
        $sheet['draw']['entryIds'] = ['entry-002', 'entry-001'];
        Http::fake([$this->remoteUrl().'/sheet?mode=results' => Http::response($sheet)]);
        Sanctum::actingAs($fixture['admin'], ['competition:manage']);
        $response = $this->postJson($this->base($fixture).'/pull', ['eventId' => 'event-123', 'divisionId' => 'division-456'])->assertOk();
        $category = Category::findOrFail($response->json('categoryId'));
        $this->assertSame('female', $category->gender);
        $this->assertSame(['entry-002', 'entry-001'], $category->entries()->orderBy('seed')->pluck('external_id')->all());
        Http::assertSent(fn (ClientRequest $request): bool => $request->method() === 'GET' && $request->hasHeader('Authorization', 'Bearer test-secret'));
        Http::assertSentCount(1);
    }

    public function test_push_preserves_an_unpublished_second_form_and_is_idempotent(): void
    {
        $fixture = $this->imported();
        $this->schedule($fixture);
        $this->approve($fixture, Performance::orderBy('id')->firstOrFail());
        $this->configureConnection();
        $sheet = $this->remoteSheet();
        $sheet['rows'][0]['entry']['scores']['form2'] = ['code' => 3, 'accuracy' => '2.60', 'presentation' => '5.90'];
        $updated = $sheet;
        $updated['revision'] = 11;
        $updated['rows'][0]['entry']['scores']['form1'] = ['code' => 2, 'accuracy' => '2.50', 'presentation' => '6.00'];
        Http::fake([
            $this->remoteUrl().'/sheet?mode=results' => Http::sequence()->push($sheet)->push($updated),
            $this->remoteUrl().'/entries/entry-001/scores' => Http::response(['revision' => 11]),
        ]);
        Sanctum::actingAs($fixture['admin'], ['competition:manage']);
        $url = $this->base($fixture).'/divisions/division-456/push';
        $this->postJson($url)->assertOk()->assertJsonPath('sentEntryIds', ['entry-001'])->assertJsonPath('revision', 11);
        Http::assertSent(fn (ClientRequest $request): bool => $request->method() === 'PUT'
            && $request->hasHeader('If-Match', '"10"')
            && $request['form1'] === ['code' => 2, 'accuracy' => '2.50', 'presentation' => '6.00']
            && $request['form2'] === ['code' => 3, 'accuracy' => '2.60', 'presentation' => '5.90']);
        $this->postJson($url)->assertOk()->assertJsonPath('sentEntryIds', [])->assertJsonPath('skippedEntryIds', ['entry-001']);
        Http::assertSentCount(3);
        $this->assertSame(1, AuditLog::where('action', 'tkdhub.result_sent')->count());
    }

    public function test_remote_revision_is_updated_after_each_athlete_and_partial_conflicts_report_sent_ids(): void
    {
        $fixture = $this->imported(2);
        $this->schedule($fixture);
        foreach (Performance::orderBy('id')->get() as $performance) {
            $this->approve($fixture, $performance);
        }
        $this->configureConnection();
        Http::fake([
            $this->remoteUrl().'/sheet?mode=results' => Http::response($this->remoteSheet(2)),
            $this->remoteUrl().'/entries/entry-001/scores' => Http::response(['revision' => 11]),
            $this->remoteUrl().'/entries/entry-002/scores' => Http::response(['message' => 'stale version'], 409),
        ]);
        Sanctum::actingAs($fixture['admin'], ['competition:manage']);
        $this->postJson($this->base($fixture).'/divisions/division-456/push')->assertConflict()
            ->assertJsonPath('sentEntryIds', ['entry-001'])->assertJsonPath('failedEntryId', 'entry-002')->assertJsonPath('upstreamStatus', 409);
        Http::assertSent(fn (ClientRequest $request): bool => str_ends_with($request->url(), '/entry-002/scores') && $request->hasHeader('If-Match', '"11"'));
        Http::assertSentCount(3);
        $this->assertSame(1, AuditLog::where('action', 'tkdhub.result_sent')->count());
    }

    public function test_overwriting_a_remote_score_requires_a_reason_and_never_silently_clears_scores(): void
    {
        $fixture = $this->imported();
        $this->schedule($fixture);
        $this->approve($fixture, Performance::firstOrFail());
        $this->configureConnection();
        $sheet = $this->remoteSheet();
        $sheet['rows'][0]['entry']['scores']['form1']['accuracy'] = '1.00';
        Http::fake([
            $this->remoteUrl().'/sheet?mode=results' => Http::response($sheet),
            $this->remoteUrl().'/entries/entry-001/scores' => Http::response(['revision' => 11]),
        ]);
        Sanctum::actingAs($fixture['admin'], ['competition:manage']);
        $url = $this->base($fixture).'/divisions/division-456/push';
        $this->postJson($url)->assertUnprocessable();
        Http::assertSentCount(1);
        $this->postJson($url, ['reason' => 'اصلاح نتیجه با تأیید مدیر'])->assertOk();
        Http::assertSent(fn (ClientRequest $request): bool => $request->method() === 'PUT' && $request['reason'] === 'اصلاح نتیجه با تأیید مدیر');
        Http::assertSentCount(3);
    }

    public static function rejectedRemoteSheets(): array
    {
        return [
            'remote scoring caps differ' => ['event.rules.presentationMax', '7.00', 422],
            'entry-specific forms unsupported' => ['event.rules.formSelection', 'entry', 422],
            'finalized remote division' => ['final', true, 409],
            'draw changed' => ['draw.entryIds', [], 409],
            'forms changed' => ['division.form1Code', 4, 409],
            'wrong event response' => ['event.id', 'other-event', 502],
            'invalid version response' => ['revision', null, 502],
            'missing division name' => ['division.name', null, 502],
            'malformed extra row' => ['rows.1', null, 502],
            'malformed remote score' => ['rows.0.entry.scores.form1.accuracy', 2.50, 502],
        ];
    }

    #[DataProvider('rejectedRemoteSheets')]
    public function test_incompatible_remote_sheet_cannot_receive_any_score(string $key, mixed $value, int $status): void
    {
        $fixture = $this->imported();
        $this->configureConnection();
        $sheet = $this->remoteSheet();
        data_set($sheet, $key, $value);
        Http::fake([$this->remoteUrl().'/sheet?mode=results' => Http::response($sheet)]);
        Sanctum::actingAs($fixture['admin'], ['competition:manage']);
        $this->postJson($this->base($fixture).'/divisions/division-456/push')->assertStatus($status);
        Http::assertSentCount(1);
        $this->assertSame(0, AuditLog::where('action', 'tkdhub.result_sent')->count());
    }

    public function test_missing_connection_credentials_return_503_and_network_failures_return_502(): void
    {
        $fixture = $this->competition(5, 0);
        config(['tkdhub.organization_id' => null, 'tkdhub.token' => null]);
        Http::preventStrayRequests();
        Sanctum::actingAs($fixture['admin'], ['competition:manage']);
        $url = $this->base($fixture).'/pull';
        $body = ['eventId' => 'event-123', 'divisionId' => 'division-456'];
        $this->postJson($url, $body)->assertStatus(503);
        Http::assertNothingSent();
        $this->configureConnection();
        Http::fake([$this->remoteUrl().'/sheet?mode=results' => Http::failedConnection()]);
        $this->postJson($url, $body)->assertStatus(502);
        $this->assertDatabaseCount('entries', 0);
    }

    public function test_push_lock_refuses_concurrent_sends_without_contacting_tkdhub(): void
    {
        $fixture = $this->imported();
        Http::preventStrayRequests();
        $lock = Cache::lock('tkdhub.push.event-123.division-456', 1800);
        $this->assertTrue($lock->get());
        Sanctum::actingAs($fixture['admin'], ['competition:manage']);
        try {
            $this->postJson($this->base($fixture).'/divisions/division-456/push')->assertConflict();
            Http::assertNothingSent();
        } finally {
            $lock->release();
        }
    }
}
