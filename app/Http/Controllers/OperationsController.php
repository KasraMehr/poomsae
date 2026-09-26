<?php

namespace App\Http\Controllers;

use App\Actions\CompetitionSetup;
use App\Actions\RunCompetition;
use App\Actions\ScheduleRound;
use App\Http\Requests\CompetitionSetupRequest;
use App\Http\Requests\PerformanceCommandRequest;
use App\Http\Requests\ResolveBoutRequest;
use App\Http\Requests\ScheduleRoundRequest;
use App\Models\Bout;
use App\Models\Category;
use App\Models\Entry;
use App\Models\Performance;
use App\Models\Tournament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OperationsController extends Controller
{
    public function court(CompetitionSetupRequest $request, Tournament $tournament, CompetitionSetup $setup): RedirectResponse
    {
        $setup->court($request->user(), $tournament, $request->validated());

        return back()->with('success', 'زمین اضافه شد.');
    }

    public function member(CompetitionSetupRequest $request, Tournament $tournament, CompetitionSetup $setup): RedirectResponse
    {
        $setup->member($request->user(), $tournament, $request->validated());

        return back()->with('success', 'عضو به مسابقه اضافه شد؛ رمز حساب قبلی تغییر نکرد.');
    }

    public function category(CompetitionSetupRequest $request, Tournament $tournament, CompetitionSetup $setup, ?Category $category = null): RedirectResponse
    {
        $setup->category($request->user(), $tournament, $request->validated(), $category);

        return back()->with('success', 'رده و تنظیمات محاسبه ذخیره شد.');
    }

    public function entry(CompetitionSetupRequest $request, Tournament $tournament, Category $category, CompetitionSetup $setup): RedirectResponse
    {
        $setup->entry($request->user(), $tournament, $category, $request->validated());

        return back()->with('success', 'ورزشکار ثبت شد؛ حضور را قبل از قرعه تأیید کنید.');
    }

    public function entryStatus(CompetitionSetupRequest $request, Tournament $tournament, Entry $entry, CompetitionSetup $setup): RedirectResponse
    {
        $setup->entryStatus($request->user(), $tournament, $entry, $request->validated('status'));

        return back();
    }

    public function schedule(ScheduleRoundRequest $request, Tournament $tournament, Category $category, ScheduleRound $schedule): RedirectResponse
    {
        $schedule->handle($request->user(), $tournament, $category, $request->validated());

        return back()->with('success', 'جدول دور ساخته شد. فهرست این رده قفل شد.');
    }

    public function command(PerformanceCommandRequest $request, Tournament $tournament, Performance $performance, RunCompetition $run): RedirectResponse
    {
        $run->command($request->user(), $tournament, $performance, $request->validated());

        return back()->with('success', 'وضعیت اجرا به‌روزرسانی شد.');
    }

    public function resolve(ResolveBoutRequest $request, Tournament $tournament, Bout $bout, RunCompetition $run): RedirectResponse
    {
        $run->resolve($request->user(), $tournament, $bout, $request->validated());

        return back()->with('success', 'تصمیم تساوی ثبت شد.');
    }

    public function complete(Request $request,Tournament $tournament,RunCompetition $run): RedirectResponse
    {
        $run->complete($request->user(),$tournament);

        return back()->with('success','مسابقه پایان یافت.');
    }
}
