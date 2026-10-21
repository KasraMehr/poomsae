<?php

namespace App\Http\Controllers;

use App\Actions\ManageCompetitionAppeals;
use App\Http\Requests\DecideCompetitionAppealRequest;
use App\Http\Requests\StoreCompetitionAppealRequest;
use App\Models\CompetitionAppeal;
use App\Models\Tournament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CompetitionAppealController extends Controller
{
    public function index(Request $request, Tournament $tournament, ManageCompetitionAppeals $appeals): Response
    {
        Gate::authorize('viewAppeals', $tournament);
        $filters = $request->validate(['status' => ['nullable', 'in:submitted,reviewing,accepted,rejected,applied']]);

        return Inertia::render('Competition/Appeals', [
            'tournament' => $tournament->only('id', 'name', 'status'),
            ...$appeals->listing($request->user(), $tournament, $filters['status'] ?? null),
            'statusFilter' => $filters['status'] ?? '',
            'canFile' => $request->user()->can('operate', $tournament) && in_array($tournament->status, ['running', 'completed'], true),
            'canApply' => $request->user()->can('operate', $tournament) && $tournament->status !== 'archived',
            'urls' => ['index' => route('appeals.index', $tournament), 'store' => route('appeals.store', $tournament), 'back' => $request->user()->can('operate', $tournament) ? route('tournaments.show', $tournament) : route('dashboard')],
        ]);
    }

    public function store(StoreCompetitionAppealRequest $request, Tournament $tournament, ManageCompetitionAppeals $appeals): RedirectResponse
    {
        $appeals->file($request->user(), $tournament, $request->validated());

        return back()->with('success', 'اعتراض ثبت و نتیجه برای بررسی ناظر علامت‌گذاری شد.');
    }

    public function review(Request $request, Tournament $tournament, CompetitionAppeal $appeal, ManageCompetitionAppeals $appeals): RedirectResponse
    {
        Gate::authorize('reviewAppeals', $tournament);
        $data = $request->validate(['expected_version' => ['required', 'integer', 'min:1']]);
        $appeals->beginReview($request->user(), $tournament, $appeal, (int) $data['expected_version']);

        return back()->with('success', 'بررسی اعتراض به شما سپرده شد.');
    }

    public function decide(DecideCompetitionAppealRequest $request, Tournament $tournament, CompetitionAppeal $appeal, ManageCompetitionAppeals $appeals): RedirectResponse
    {
        $appeals->decide($request->user(), $tournament, $appeal, $request->validated());

        return back()->with('success', $request->input('decision') === 'accepted' ? 'رأی پذیرش ثبت شد؛ اصلاح‌های تأییدشده در انتظار اعمال مدیر یا اپراتور است.' : 'اعتراض با ثبت دلیل رد شد؛ نتیجه محفوظ است.');
    }

    public function apply(Request $request, Tournament $tournament, CompetitionAppeal $appeal, ManageCompetitionAppeals $appeals): RedirectResponse
    {
        Gate::authorize('operate', $tournament);
        $data = $request->validate(['expected_version' => ['required', 'integer', 'min:1']]);
        $appeals->apply($request->user(), $tournament, $appeal, (int) $data['expected_version']);

        return back()->with('success', 'رأی ناظر اعمال، نتیجه دوباره محاسبه و سابقهٔ پرونده تکمیل شد.');
    }
}
