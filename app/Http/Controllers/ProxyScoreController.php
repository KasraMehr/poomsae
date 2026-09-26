<?php

namespace App\Http\Controllers;

use App\Actions\SubmitScore;
use App\Http\Requests\SubmitProxyScoreRequest;
use App\Models\Performance;
use App\Models\Tournament;
use Illuminate\Http\RedirectResponse;

class ProxyScoreController extends Controller
{
    public function __invoke(SubmitProxyScoreRequest $request, Tournament $tournament, Performance $performance, SubmitScore $submit): RedirectResponse
    {
        $submit->handle($request->user(), $tournament, $performance, $request->validated(), true);

        return back()->with('success', 'نمرهٔ جایگزین با نام اپراتور و دلیل ثبت شد.');
    }
}
