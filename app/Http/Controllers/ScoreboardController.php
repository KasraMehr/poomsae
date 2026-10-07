<?php

namespace App\Http\Controllers;

use App\Actions\CompetitionDisplay;
use App\Actions\CompetitionView;
use App\Actions\ExportCompetitionResults;
use App\Actions\ExportResultsWorkbook;
use App\Models\Tournament;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HtmlResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ScoreboardController extends Controller
{
    public function show(Request $request, Tournament $tournament, CompetitionDisplay $display): Response
    {
        Gate::authorize('view', $tournament);

        return Inertia::render('Competition/Scoreboard', ['display' => $display->snapshot($tournament, $request->user()), 'displayUrls' => $this->urls($tournament)]);
    }

    public function rtds(Request $request, Tournament $tournament, CompetitionDisplay $display): Response
    {
        Gate::authorize('view', $tournament);

        return Inertia::render('Competition/Rtds', ['display' => $display->snapshot($tournament, $request->user()), 'displayUrls' => $this->urls($tournament)]);
    }

    public function data(Request $request, Tournament $tournament, CompetitionDisplay $display): JsonResponse
    {
        Gate::authorize('view', $tournament);
        $revision = $display->revision($tournament);
        if ($request->query('revision') !== null && (string) $revision === $request->query('revision')) {
            return response()->json(['changed' => false, 'revision' => $revision, 'generated_at' => now()->toISOString()])->header('Cache-Control', 'no-store, private');
        }

        return response()->json(['changed' => true, 'data' => $display->snapshot($tournament, $request->user())])->header('Cache-Control', 'no-store, private');
    }

    /** @return array<string, string> */
    private function urls(Tournament $tournament): array
    {
        return ['data' => route('scoreboard.data', $tournament), 'scoreboard' => route('scoreboard.show', $tournament),
            'rtds' => route('scoreboard.rtds', $tournament), 'back' => route('tournaments.show', $tournament)];
    }

    public function results(Request $request, Tournament $tournament, CompetitionView $view, ExportCompetitionResults $exporter): HtmlResponse
    {
        Gate::authorize('operate', $tournament);
        $table = $exporter->table($view->snapshot($tournament, $request->user(), true));
        $response = response()->view('exports.results', compact('tournament', 'table'))->header('Cache-Control', 'no-store, private');
        if ($request->boolean('download')) {
            $response->header('Content-Disposition', 'attachment; filename="tournament-'.$tournament->id.'-results.html"');
        }

        return $response;
    }

    public function export(Request $request, Tournament $tournament, CompetitionView $view, ExportCompetitionResults $exporter): StreamedResponse
    {
        Gate::authorize('operate', $tournament);
        $table = $exporter->table($view->snapshot($tournament, $request->user(), true));

        return response()->streamDownload(function () use ($table): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $table['headers'], ',', '"', '', "\r\n");
            foreach ($table['rows'] as $cells) {
                $cells = array_map(fn (int|string $value): int|string => preg_match('/^\\s*[=+@-]/u', (string) $value) ? "'".$value : $value, $cells);
                fputcsv($out, $cells, ',', '"', '', "\r\n");
            }
            fclose($out);
        }, 'tournament-'.$tournament->id.'-results.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store, private']);
    }

    public function excel(Request $request, Tournament $tournament, CompetitionView $view, ExportCompetitionResults $exporter, ExportResultsWorkbook $workbook): BinaryFileResponse
    {
        Gate::authorize('operate', $tournament);
        $table = $exporter->table($view->snapshot($tournament, $request->user(), true));

        return response()->download($workbook->create($table), 'tournament-'.$tournament->id.'-results.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, private',
        ])->deleteFileAfterSend();
    }
}
