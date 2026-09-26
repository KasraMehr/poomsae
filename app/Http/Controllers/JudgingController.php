<?php

namespace App\Http\Controllers;

use App\Actions\CompetitionView;
use App\Actions\SubmitScore;
use App\Http\Requests\SubmitScoreRequest;
use App\Models\Performance;
use App\Models\Tournament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class JudgingController extends Controller
{
    public function index(Request $request, Tournament $tournament, CompetitionView $view): Response
    {
        abort_unless($request->user()->hasTournamentRole($tournament, ['judge']), 403);

        return Inertia::render('Competition/Judge', ['tournament' => $view->snapshot($tournament, $request->user())]);
    }

    public function store(SubmitScoreRequest $request, Tournament $tournament, Performance $performance, SubmitScore $submit): RedirectResponse
    {
        $submit->handle($request->user(), $tournament, $performance, $request->validated());

        return back()->with('success','نمرهٔ شما ذخیره شد.');
    }
}
