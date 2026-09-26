<?php

namespace App\Http\Controllers;

use App\Actions\CompetitionView;
use App\Models\Tournament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ScoreboardController extends Controller
{
    public function show(Request $request, Tournament $tournament, CompetitionView $view): Response
    {
        Gate::authorize('view', $tournament);

        return Inertia::render('Competition/Scoreboard', ['tournament' => $view->snapshot($tournament, $request->user(), true)]);
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
