<?php

namespace App\Http\Controllers;

use App\Actions\CompetitionDisplay;
use App\Actions\CompetitionView;
use App\Models\Tournament;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
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

    public function export(Request $request, Tournament $tournament, CompetitionView $view): StreamedResponse
    {
        Gate::authorize('operate', $tournament);
        $snapshot = $view->snapshot($tournament, $request->user(), true);

        return response()->streamDownload(function () use ($snapshot): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['رده', 'دور', 'رقابت', 'ورزشکار', 'فرم', 'نمره تأییدشده', 'برنده رقابت', 'دلیل'], ',', '"', '');
            foreach ($snapshot['categories'] as $category) {
                foreach ($category['rounds'] as $round) {
                    foreach ($round['bouts'] as $bout) {
                        foreach ($bout['performances'] as $performance) {
                            if ($performance['result'] === null) {
                                continue;
                            }
                            $entry = $bout['entries']->firstWhere('id', $performance['entry_id']);
                            $cells = [$category['name'], $round['name'], $bout['sequence'], $entry['name'], $performance['form_name'], $performance['result'], $bout['winner_entry_id'] === $performance['entry_id'] ? 'بله' : '', $bout['resolution_reason'] ?? ''];
                            $cells = array_map(fn ($value) => preg_match('/^\\s*[=+@-]/u', (string) $value) ? "'".$value : $value, $cells);
                            fputcsv($out, $cells, ',', '"', '');
                        }
                    }
                }
            }
            fclose($out);
        }, 'tournament-'.$tournament->id.'-results.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
