<?php

namespace Tests\Feature;

use App\Actions\RunCompetition;
use App\Actions\SubmitScore;
use App\Models\AuditLog;
use App\Models\Performance;
use App\Models\User;
use Carbon\CarbonImmutable;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\CreatesCompetition;
use Tests\TestCase;
use ZipArchive;

class CompetitionReportsTest extends TestCase
{
    use CreatesCompetition, RefreshDatabase;

    public function test_judge_report_shows_latest_raw_score_separately_from_published_result(): void
    {
        $fixture = $this->competition();
        $this->schedule($fixture);
        $performance = $fixture['category']->rounds()->firstOrFail()->bouts()->firstOrFail()->performances()->orderBy('id')->firstOrFail();
        $this->startScoring($fixture, $performance);
        $this->scoreAll($fixture, $performance);
        app(RunCompetition::class)->command($fixture['admin'], $fixture['tournament'], $performance, ['command' => 'approve', 'expected_version' => $performance->fresh()->version]);
        app(SubmitScore::class)->handle($fixture['judges'][0], $fixture['tournament'], $performance, [
            'request_id' => (string) Str::uuid(), 'expected_version' => $performance->fresh()->version, 'expected_revision' => 1,
            ...$this->detailedScoreInput('3.50', '6.00'), 'reason' => 'Correction after review',
        ]);

        $this->actingAs($fixture['admin'])->get(route('reports.judges', [$fixture['tournament'], 'judge_id' => $fixture['judges'][0]->id]))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->component('Competition/Reports')
            ->where('report.summary.total', 1)->where('report.summary.corrected', 1)
            ->where('report.rows.0.1', $fixture['judges'][0]->name)->where('report.rows.0.8', '3.50')
            ->where('report.rows.0.10', '9.50')->where('report.rows.0.11', '8.500000')->where('report.rows.0.14', 2));
    }

    public function test_judge_report_distinguishes_unconfirmed_proxy_scores(): void
    {
        $fixture = $this->competition();
        $this->schedule($fixture);
        $performance = $fixture['category']->rounds()->firstOrFail()->bouts()->firstOrFail()->performances()->orderBy('id')->firstOrFail();
        $this->startScoring($fixture, $performance);
        app(SubmitScore::class)->handle($fixture['admin'], $fixture['tournament'], $performance, [
            'request_id' => (string) Str::uuid(), 'expected_version' => $performance->fresh()->version, 'expected_revision' => 0,
            'judge_assignment_id' => $performance->bout->judges()->firstOrFail()->id,
            ...$this->detailedScoreInput('2.50', '6.00'), 'reason' => 'Judge device disconnected',
        ], true);

        $this->actingAs($fixture['admin'])->get(route('reports.judges', $fixture['tournament']))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('report.summary.draft', 1)->where('report.summary.submitted', 0)
            ->where('report.rows.0.11', '')->where('report.rows.0.12', 'جایگزین · '.$fixture['admin']->name)
            ->where('report.rows.0.13', 'منتظر تأیید جایگزین'));
    }

    public function test_audit_report_preserves_before_and_after_scores_and_reason(): void
    {
        $fixture = $this->competition();
        $this->schedule($fixture);
        $performance = $fixture['category']->rounds()->firstOrFail()->bouts()->firstOrFail()->performances()->orderBy('id')->firstOrFail();
        $this->startScoring($fixture, $performance);
        $this->scoreAll($fixture, $performance);
        app(SubmitScore::class)->handle($fixture['judges'][0], $fixture['tournament'], $performance, [
            'request_id' => (string) Str::uuid(), 'expected_version' => $performance->fresh()->version, 'expected_revision' => 1,
            ...$this->detailedScoreInput('3.50', '6.00'), 'reason' => 'Corrected deduction',
        ]);
        $this->actingAs($fixture['admin'])->get(route('reports.audit', [$fixture['tournament'], 'user_id' => $fixture['judges'][0]->id, 'action' => 'score.submitted']))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('report.summary.total', 2)->where('report.rows.0.2', $fixture['judges'][0]->name)
            ->where('report.rows.0.6', fn (string $value) => json_decode($value, true)['accuracy'] === 250)
            ->where('report.rows.0.7', fn (string $value) => json_decode($value, true)['score']['accuracy'] === 350)
            ->where('report.rows.0.8', 'Corrected deduction'));
    }

    public function test_date_filter_uses_tournament_timezone_and_accepts_only_end_date(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-07 20:45:00', 'UTC'));
        $fixture = $this->competition();
        $this->schedule($fixture);
        $performance = $fixture['category']->rounds()->firstOrFail()->bouts()->firstOrFail()->performances()->orderBy('id')->firstOrFail();
        $this->startScoring($fixture, $performance);
        $this->scoreAll($fixture, $performance);
        $this->actingAs($fixture['admin']);
        $this->get(route('reports.judges', [$fixture['tournament'], 'from' => '2026-10-08', 'to' => '2026-10-08']))
            ->assertInertia(fn (Assert $page) => $page->where('report.summary.total', 5)->where('report.rows.0.15', '2026-10-08 00:15:00'));
        $this->get(route('reports.judges', [$fixture['tournament'], 'to' => '2026-10-07']))
            ->assertInertia(fn (Assert $page) => $page->where('report.summary.total', 0));
        $this->getJson(route('reports.judges', [$fixture['tournament'], 'from' => '2026-10-08', 'to' => '2026-10-07']))
            ->assertUnprocessable()->assertJsonValidationErrors('to');
    }

    public function test_filters_reject_records_from_another_tournament(): void
    {
        $fixture = $this->competition();
        $other = $this->competition();
        $this->schedule($fixture);
        $this->schedule($other);
        $this->actingAs($fixture['admin'])->getJson(route('reports.judges', [
            $fixture['tournament'], 'category_id' => $other['category']->id, 'judge_id' => $other['judges'][0]->id,
        ]))->assertUnprocessable()->assertJsonValidationErrors(['category_id', 'judge_id']);
        $this->getJson(route('reports.audit', [$fixture['tournament'], 'user_id' => $other['admin']->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('user_id');
    }

    public function test_paginated_audit_exports_all_filtered_rows_and_safe_unicode_cells(): void
    {
        $fixture = $this->competition();
        $fixture['admin']->update(['name' => '=HYPERLINK("unsafe") نام فارسی']);
        for ($index = 0; $index < 60; $index++) {
            AuditLog::create(['tournament_id' => $fixture['tournament']->id, 'user_id' => $fixture['admin']->id,
                'action' => 'tournament.updated', 'subject_type' => 'tournament', 'subject_id' => $fixture['tournament']->id,
                'before' => ['name' => 'قبلی'], 'after' => ['name' => 'جدید', 'reason' => "دلیل\nاصلاح"]]);
        }
        $this->actingAs($fixture['admin']);
        $parameters = [$fixture['tournament'], 'action' => 'tournament.updated', 'page' => 2];
        $this->get(route('reports.audit', $parameters))->assertInertia(fn (Assert $page) => $page
            ->has('report.rows', 10)->where('report.pagination.total', 60)->where('report.rows.0.0', 51));
        $content = $this->get(route('reports.audit', [...$parameters, 'export' => 'csv']))
            ->assertDownload('tournament-'.$fixture['tournament']->id.'-audit.csv')->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $lines = explode("\r\n", rtrim($content, "\r\n"));
        $this->assertCount(61, $lines);
        $cells = str_getcsv($lines[1], ',', '"', '');
        $this->assertCount(10, $cells);
        $this->assertSame("'".$fixture['admin']->name, $cells[2]);
        $this->assertSame('دلیل اصلاح', $cells[8]);
        $response = $this->get(route('reports.audit', [...$parameters, 'export' => 'xlsx']))
            ->assertDownload('tournament-'.$fixture['tournament']->id.'-audit.xlsx');
        $file = $response->baseResponse->getFile()->getPathname();
        $zip = new ZipArchive;
        $zip->open($file);
        $document = new DOMDocument;
        $document->loadXML($zip->getFromName('xl/tables/table1.xml'));
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $this->assertSame('A1:J61', $document->documentElement->getAttribute('ref'));
        $this->assertSame(10, $xpath->query('//s:tableColumn')->length);
        $this->assertSame('زمان', $xpath->query('//s:tableColumn')->item(1)->getAttribute('name'));
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $this->assertStringContainsString('نام فارسی', $sheet);
        $this->assertStringNotContainsString('<f>', $sheet);
        $zip->close();
        unlink($file);
    }

    #[TestWith(['judges'])]
    #[TestWith(['audit'])]
    #[TestWith(['medals'])]
    public function test_reports_require_login_and_an_operational_role(string $type): void
    {
        $fixture = $this->competition();
        $url = route('reports.'.$type, $fixture['tournament']);
        $this->get($url)->assertRedirect(route('login'));
        $this->actingAs($fixture['judges'][0])->get($url)->assertForbidden();
        $outsider = User::factory()->create();
        $this->actingAs($outsider)->get($url)->assertForbidden();
        $operator = User::factory()->create();
        $fixture['tournament']->users()->attach($operator, ['role' => 'operator']);
        $this->actingAs($operator)->get($url)->assertOk();
        $this->get($url.'?export=csv')->assertOk();
    }

    #[TestWith([2, 0])]
    #[TestWith([3, 1])]
    #[TestWith([4, 2])]
    #[TestWith([8, 2])]
    public function test_knockout_medals_use_finalists_and_semifinal_losers_including_byes(int $entryCount, int $bronzeCount): void
    {
        $fixture = $this->competition(5, $entryCount);
        $run = app(RunCompetition::class);
        do {
            $this->schedule($fixture);
            $round = $fixture['category']->rounds()->orderByDesc('sequence')->firstOrFail();
            foreach ($round->bouts()->where('status', 'pending')->get() as $bout) {
                $run->resolve($fixture['admin'], $fixture['tournament'], $bout, ['decision_type' => 'walkover', 'winner_entry_id' => $bout->entries()->firstOrFail()->id, 'confirmed' => true, 'reason' => 'Opponent withdrawn']);
            }
        } while ($round->bouts()->count() > 1);
        $champion = $round->bouts()->firstOrFail()->fresh()->winner_entry_id;
        $name = $fixture['category']->entries()->findOrFail($champion)->display_name;
        $this->actingAs($fixture['admin'])->get(route('reports.medals', $fixture['tournament']))
            ->assertInertia(fn (Assert $page) => $page->where('report.summary.gold', 1)->where('report.summary.silver', 1)
                ->where('report.summary.bronze', $bronzeCount)->has('report.rows', 2 + $bronzeCount)
                ->where('report.rows.0.4', 'طلا')->where('report.rows.0.5', $name));
    }

    public function test_unfinished_categories_do_not_receive_medals(): void
    {
        $fixture = $this->competition();
        $this->schedule($fixture);
        $this->actingAs($fixture['admin'])->get(route('reports.medals', $fixture['tournament']))
            ->assertInertia(fn (Assert $page) => $page->has('report.rows', 0)->where('report.pending_categories.0', $fixture['category']->name));
    }

    public function test_round_robin_shared_ranks_are_preserved_in_medals(): void
    {
        $fixture = $this->completedTable(['3.90', '3.90', '3.80', '3.70']);
        $this->actingAs($fixture['admin'])->get(route('reports.medals', $fixture['tournament']))
            ->assertInertia(fn (Assert $page) => $page->where('report.summary.gold', 2)->where('report.summary.silver', 0)->where('report.summary.bronze', 1)
                ->where('report.rows.0.3', 1)->where('report.rows.1.3', 1)->where('report.rows.2.3', 3)
                ->where('report.rows.0.9', 'رتبهٔ مشترک'));
    }

    public function test_medals_follow_score_corrections_after_publication(): void
    {
        $fixture = $this->completedTable(['3.90', '3.80']);
        $entries = $fixture['category']->entries()->orderBy('id')->get();
        foreach (Performance::where('entry_id', $entries[0]->id)->get() as $performance) {
            foreach ($fixture['judges'] as $judge) {
                app(SubmitScore::class)->handle($judge, $fixture['tournament'], $performance, [
                    'request_id' => (string) Str::uuid(), 'expected_version' => $performance->fresh()->version, 'expected_revision' => 1,
                    ...$this->detailedScoreInput('3.00', '6.00'), 'reason' => 'Correction of missed deductions',
                ]);
            }
        }
        $this->actingAs($fixture['admin'])->get(route('reports.medals', $fixture['tournament']))
            ->assertInertia(fn (Assert $page) => $page->where('report.rows.0.5', $entries[1]->display_name)->where('report.rows.0.4', 'طلا')->where('report.rows.0.8', '19.600000'));
    }

    private function completedTable(array $accuracies): array
    {
        $fixture = $this->competition(5, count($accuracies), 'round_robin');
        $this->schedule($fixture);
        $scores = array_combine($fixture['category']->entries()->orderBy('id')->pluck('id')->all(), $accuracies);
        foreach ($fixture['category']->rounds()->firstOrFail()->bouts()->orderBy('sequence')->get() as $bout) {
            foreach ($bout->performances()->orderBy('form_number')->get() as $performance) {
                $this->startScoring($fixture, $performance);
                $this->scoreAll($fixture, $performance, $scores[$performance->entry_id]);
                app(RunCompetition::class)->command($fixture['admin'], $fixture['tournament'], $performance, ['command' => 'approve', 'expected_version' => $performance->fresh()->version]);
            }
        }

        return $fixture;
    }
}
