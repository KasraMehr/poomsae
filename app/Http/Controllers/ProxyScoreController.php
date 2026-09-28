<?php

namespace App\Http\Controllers;

use App\Actions\ConfirmProxyScore;
use App\Actions\SubmitScore;
use App\Http\Requests\ConfirmProxyScoreRequest;
use App\Http\Requests\SubmitProxyScoreRequest;
use App\Models\Performance;
use App\Models\ScoreSheet;
use App\Models\Tournament;
use Illuminate\Http\RedirectResponse;

class ProxyScoreController extends Controller
{
    public function __invoke(SubmitProxyScoreRequest $request, Tournament $tournament, Performance $performance, SubmitScore $submit): RedirectResponse
    {
        $submit->handle($request->user(), $tournament, $performance, $request->validated(), true);

        return back()->with('success', 'نمرهٔ جایگزین ثبت شد و در انتظار تأیید مسئول دوم است.');
    }

    public function confirm(ConfirmProxyScoreRequest $request, Tournament $tournament, Performance $performance, ScoreSheet $scoreSheet, ConfirmProxyScore $confirm): RedirectResponse
    {
        $confirm->handle($request->user(), $tournament, $performance, $scoreSheet, $request->integer('expected_revision'));

        return back()->with('success', 'نمرهٔ دستی توسط مسئول دوم تأیید شد.');
    }
}
