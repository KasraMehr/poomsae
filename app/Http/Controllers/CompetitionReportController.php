<?php

namespace App\Http\Controllers;

use App\Actions\CompetitionReports;
use App\Actions\ExportResultsWorkbook;
use App\Actions\UpdateTournamentArchive;
use App\Http\Requests\CompetitionReportRequest;
use App\Http\Requests\UpdateTournamentArchiveRequest;
use App\Models\Tournament;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CompetitionReportController extends Controller
{
    public function judges(CompetitionReportRequest $request, Tournament $tournament, CompetitionReports $reports, ExportResultsWorkbook $workbook): Response|StreamedResponse|BinaryFileResponse
    {
        return $this->report($request, $tournament, $reports, $workbook, 'judges', 'گزارش داورها');
    }

    public function audit(CompetitionReportRequest $request, Tournament $tournament, CompetitionReports $reports, ExportResultsWorkbook $workbook): Response|StreamedResponse|BinaryFileResponse
    {
        return $this->report($request, $tournament, $reports, $workbook, 'audit', 'سوابق تغییرات');
    }

    public function medals(CompetitionReportRequest $request, Tournament $tournament, CompetitionReports $reports, ExportResultsWorkbook $workbook): Response|StreamedResponse|BinaryFileResponse
    {
        return $this->report($request, $tournament, $reports, $workbook, 'medals', 'مدال‌بندی');
    }

    public function archive(UpdateTournamentArchiveRequest $request, Tournament $tournament, UpdateTournamentArchive $archive): RedirectResponse
    {
        $archive->handle($request->user(), $tournament, true, $request->validated());

        return back()->with('success', 'مسابقه آرشیو شد؛ گزارش‌ها و نتایج محفوظ‌اند.');
    }

    public function restore(UpdateTournamentArchiveRequest $request, Tournament $tournament, UpdateTournamentArchive $archive): RedirectResponse
    {
        $archive->handle($request->user(), $tournament, false, $request->validated());

        return back()->with('success', 'مسابقه از آرشیو به وضعیت پایان‌یافته بازگردانده شد.');
    }

    private function report(CompetitionReportRequest $request, Tournament $tournament, CompetitionReports $reports, ExportResultsWorkbook $workbook, string $type, string $title): Response|StreamedResponse|BinaryFileResponse
    {
        $allowedFilters = match ($type) {
            'judges' => ['judge_id', 'category_id', 'from', 'to'],
            'audit' => ['user_id', 'action', 'from', 'to'],
            default => ['category_id'],
        };
        $filters = array_intersect_key($request->validated(), array_flip($allowedFilters));
        $export = $request->input('export');
        $report = $type === 'medals'
            ? $reports->medals($tournament, $request->user(), $filters)
            : $reports->{$type}($tournament, $request->user(), $filters, $export !== null);
        $filename = 'tournament-'.$tournament->id.'-'.$type;
        if ($export === 'xlsx') {
            return response()->download($workbook->create($report), $filename.'.xlsx', [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'no-store, private',
            ])->deleteFileAfterSend();
        }
        if ($export === 'csv') {
            return response()->streamDownload(function () use ($report): void {
                $out = fopen('php://output', 'w');
                fwrite($out, "\xEF\xBB\xBF");
                fputcsv($out, $report['headers'], ',', '"', '', "\r\n");
                foreach ($report['rows'] as $row) {
                    $cells = array_map(function (int|string $value): int|string {
                        if (is_string($value)) {
                            $value = preg_replace('/[\r\n\t]+/u', ' ', $value);
                        }

                        return preg_match('/^\s*[=+@-]/u', (string) $value) ? "'".$value : $value;
                    }, $row);
                    fputcsv($out, $cells, ',', '"', '', "\r\n");
                }
                fclose($out);
            }, $filename.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store, private']);
        }

        return Inertia::render('Competition/Reports', [
            'tournament' => $tournament->only('id', 'name', 'status', 'timezone'),
            'type' => $type, 'title' => $title, 'report' => $report, 'filters' => $filters,
            'options' => $reports->options($tournament),
            'canArchive' => $request->user()->can('update', $tournament),
            'reportUrls' => [
                'judges' => route('reports.judges', $tournament), 'audit' => route('reports.audit', $tournament),
                'medals' => route('reports.medals', $tournament), 'back' => route('tournaments.show', $tournament),
                'archive' => route('tournaments.archive', $tournament), 'restore' => route('tournaments.restore', $tournament),
                'archives' => route('tournaments.index', ['status' => 'archived']),
                'csv' => route('reports.'.$type, [$tournament, ...$filters, 'export' => 'csv']),
                'excel' => route('reports.'.$type, [$tournament, ...$filters, 'export' => 'xlsx']),
            ],
        ]);
    }
}
