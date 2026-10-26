<?php

namespace Tests\Feature;

use App\Actions\ExportCompetitionResults;
use App\Actions\ExportResultsWorkbook;
use App\Actions\RunCompetition;
use App\Models\Performance;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\CreatesCompetition;
use Tests\TestCase;
use ZipArchive;

class ResultsExportTest extends TestCase
{
    use CreatesCompetition, RefreshDatabase;

    public function test_results_page_and_download_show_separate_rows_and_columns(): void
    {
        $fixture = $this->competition();
        $fixture['tournament']->update(['name' => '<script>alert("test")</script> مسابقه']);
        $this->schedule($fixture);
        $performance = $this->publishFirstPerformance($fixture);

        $response = $this->actingAs($fixture['admin'])->get(route('scoreboard.results', $fixture['tournament']))
            ->assertViewIs('exports.results')
            ->assertSee('دریافت جدول برای مرورگر')
            ->assertSee('دریافت Excel مرتب (.xlsx)')
            ->assertSee('دریافت CSV')
            ->assertSee('8.500')
            ->assertSee($fixture['tournament']->name)
            ->assertDontSee('<script>alert("test")</script>', false);

        $document = new DOMDocument;
        $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($document);
        $this->assertSame(16, $xpath->query('//thead/tr/th')->length);
        $this->assertSame(1, $xpath->query('//tbody/tr')->length);
        $this->assertSame(16, $xpath->query('//tbody/tr/td')->length);
        $this->assertSame($performance->entry->display_name, $xpath->query('//tbody/tr/td[7]')->item(0)->textContent);

        $download = $this->get(route('scoreboard.results', [$fixture['tournament'], 'download' => 1]))
            ->assertDownload('tournament-'.$fixture['tournament']->id.'-results.html')
            ->assertHeader('Content-Type', 'text/html; charset=utf-8');

        $this->assertSame($response->getContent(), $download->getContent());
    }

    public function test_csv_keeps_every_record_on_one_line_with_matching_columns_and_exact_scores(): void
    {
        $fixture = $this->competition(5, 2, 'round_robin');
        $this->schedule($fixture);
        $performance = $this->publishFirstPerformance($fixture);
        $performance->entry->update(['display_name' => "علی \"آزمایشی\", یک\nخط"]);
        $performance->entry->athletes()->firstOrFail()->update(['club' => '=1+1']);

        $response = $this->actingAs($fixture['admin'])->get(route('scoreboard.export', $fixture['tournament']))
            ->assertDownload('tournament-'.$fixture['tournament']->id.'-results.csv');

        $content = $response->streamedContent();
        $lines = explode("\r\n", rtrim($content, "\r\n"));
        $this->assertCount(2, $lines);
        $this->assertStringStartsWith("\xEF\xBB\xBFردیف,", $content);
        $this->assertStringNotContainsString('sep=', $content);
        $header = str_getcsv(substr($lines[0], 3), ',', '"', '');
        $row = str_getcsv($lines[1], ',', '"', '');
        $this->assertCount(16, $header);
        $this->assertCount(16, $row);
        $this->assertSame('علی "آزمایشی", یک خط', $row[6]);
        $this->assertSame("'=1+1", $row[7]);
        $this->assertSame('8.500000', $row[9]);
        $this->assertSame('', $row[12]);
        $this->assertSame('در حال تکمیل', $row[14]);
    }

    public function test_results_page_explains_when_no_scores_are_published(): void
    {
        $fixture = $this->competition();

        $this->actingAs($fixture['admin'])->get(route('scoreboard.results', $fixture['tournament']))
            ->assertSee('هنوز نتیجهٔ تأییدشده‌ای ثبت نشده است.')
            ->assertDontSee('<table>', false);
    }

    #[TestWith(['scoreboard.results'])]
    #[TestWith(['scoreboard.export'])]
    #[TestWith(['scoreboard.excel'])]
    public function test_results_and_downloads_require_operator_permission(string $routeName): void
    {
        $fixture = $this->competition();

        $this->actingAs($fixture['judges'][0])->get(route($routeName, $fixture['tournament']))->assertForbidden();
        $this->actingAs(User::factory()->create())->get(route($routeName, $fixture['tournament']))->assertForbidden();
    }

    private function publishFirstPerformance(array $fixture): Performance
    {
        $performance = $fixture['category']->rounds()->firstOrFail()->bouts()->orderBy('sequence')->firstOrFail()->performances()->orderBy('id')->firstOrFail();
        $this->startScoring($fixture, $performance);
        $this->scoreAll($fixture, $performance);
        app(RunCompetition::class)->command($fixture['admin'], $fixture['tournament'], $performance, [
            'command' => 'approve', 'expected_version' => $performance->fresh()->version,
        ]);

        return $performance->fresh();
    }

    public function test_excel_preserves_persian_and_precise_numeric_scores_in_a_formatted_table(): void
    {
        $fixture = $this->competition();
        $this->schedule($fixture);
        $performance = $this->publishFirstPerformance($fixture);
        $performance->entry->update(['display_name' => 'نام فارسی & <ورزشکار>']);
        $performance->entry->athletes()->firstOrFail()->update(['club' => '=1+1']);

        $response = $this->actingAs($fixture['admin'])->get(route('scoreboard.excel', $fixture['tournament']))
            ->assertDownload('tournament-'.$fixture['tournament']->id.'-results.xlsx')
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $path = $response->baseResponse->getFile()->getPathname();
        $archive = new ZipArchive;
        $this->assertTrue($archive->open($path));
        try {
            $document = new DOMDocument;
            $this->assertTrue($document->loadXML($archive->getFromName('xl/worksheets/sheet1.xml')));
            $xpath = new DOMXPath($document);
            $xpath->registerNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $this->assertSame(2, $xpath->query('//s:sheetData/s:row')->length);
            $this->assertSame(16, $xpath->query('//s:row[@r="2"]/s:c')->length);
            $this->assertSame('ردیف', $xpath->evaluate('string(//s:c[@r="A1"]/s:is/s:t)'));
            $this->assertSame('نام فارسی & <ورزشکار>', $xpath->evaluate('string(//s:c[@r="G2"]/s:is/s:t)'));
            $this->assertSame('=1+1', $xpath->evaluate('string(//s:c[@r="H2"]/s:is/s:t)'));
            $this->assertSame(0, $xpath->query('//s:f')->length);
            $this->assertSame('8.500000', $xpath->evaluate('string(//s:c[@r="J2"]/s:v)'));
            $this->assertSame('1', $xpath->evaluate('string(//s:sheetView/@rightToLeft)'));
            $this->assertSame('1', $xpath->evaluate('string(//s:pane/@ySplit)'));
            $this->assertGreaterThan(10, $xpath->query('//s:cols/s:col')->length);
            $this->assertStringContainsString('formatCode="0.000"', $archive->getFromName('xl/styles.xml'));
            $filter = new DOMDocument;
            $this->assertTrue($filter->loadXML($archive->getFromName('xl/tables/table1.xml')));
            $this->assertSame('A1:P2', $filter->documentElement->getAttribute('ref'));
            $this->assertSame('A1:P2', $filter->getElementsByTagName('autoFilter')->item(0)->getAttribute('ref'));
        } finally {
            $archive->close();
            unlink($path);
        }
    }

    public function test_excel_download_with_no_results_has_only_headers_and_an_empty_table_row(): void
    {
        $fixture = $this->competition();

        $response = $this->actingAs($fixture['admin'])->get(route('scoreboard.excel', $fixture['tournament']))->assertDownload();

        $path = $response->baseResponse->getFile()->getPathname();
        $archive = new ZipArchive;
        $this->assertTrue($archive->open($path));
        try {
            $document = new DOMDocument;
            $this->assertTrue($document->loadXML($archive->getFromName('xl/worksheets/sheet1.xml')));
            $xpath = new DOMXPath($document);
            $xpath->registerNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $this->assertSame(2, $xpath->query('//s:sheetData/s:row')->length);
            $this->assertSame('', $xpath->evaluate('string(//s:c[@r="G2"]/s:is/s:t)'));
            $this->assertSame(0, $xpath->query('//s:row[@r="2"]/s:c/s:v')->length);
        } finally {
            $archive->close();
            unlink($path);
        }
    }

    public function test_excel_table_filter_expands_to_include_all_result_rows(): void
    {
        $table = app(ExportCompetitionResults::class)->table(['courts' => [], 'categories' => []]);
        $table['rows'] = array_map(fn (int $index): array => [
            $index, 'ابداعی', 'دوره‌ای', 'مرحله 1', $index, 'زمین A', 'ورزشکار '.$index, 'باشگاه',
            'اجرای ابداعی', '8.123456', '', '', '8.123456', $index, 'ثبت‌شده', '',
        ], range(1, 6));

        $path = app(ExportResultsWorkbook::class)->create($table);

        $archive = new ZipArchive;
        $this->assertTrue($archive->open($path));
        try {
            $document = new DOMDocument;
            $this->assertTrue($document->loadXML($archive->getFromName('xl/worksheets/sheet1.xml')));
            $xpath = new DOMXPath($document);
            $xpath->registerNamespace('s', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $this->assertSame(7, $xpath->query('//s:sheetData/s:row')->length);
            $this->assertSame('ورزشکار 6', $xpath->evaluate('string(//s:c[@r="G7"]/s:is/s:t)'));
            $this->assertSame('8.123456', $xpath->evaluate('string(//s:c[@r="M7"]/s:v)'));
            $filter = new DOMDocument;
            $this->assertTrue($filter->loadXML($archive->getFromName('xl/tables/table1.xml')));
            $this->assertSame('A1:P7', $filter->documentElement->getAttribute('ref'));
            $this->assertSame('A1:P7', $filter->getElementsByTagName('autoFilter')->item(0)->getAttribute('ref'));
        } finally {
            $archive->close();
            unlink($path);
        }
    }
}
