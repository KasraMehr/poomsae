<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CompetitionAppealController;
use App\Http\Controllers\CompetitionReportController;
use App\Http\Controllers\CompetitionSystemController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\JudgingController;
use App\Http\Controllers\OperationsController;
use App\Http\Controllers\ProxyScoreController;
use App\Http\Controllers\ScoreboardController;
use App\Http\Controllers\TournamentController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:login');
});
Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::resource('tournaments', TournamentController::class)->only(['index', 'store', 'show']);
    Route::resource('tournaments', TournamentController::class)->only(['update'])->middleware('panel:admin');
    Route::prefix('tournaments/{tournament}')->group(function () {
        Route::get('/system', CompetitionSystemController::class)->middleware('panel:admin')->name('competition.system');
        Route::get('/appeals', [CompetitionAppealController::class, 'index'])->middleware('panel:admin,operator')->name('appeals.index');
        Route::post('/appeals', [CompetitionAppealController::class, 'store'])->middleware('panel:admin,operator')->name('appeals.store');
        Route::post('/appeals/{appeal}/review', [CompetitionAppealController::class, 'review'])->middleware('panel:admin,operator')->name('appeals.review');
        Route::post('/appeals/{appeal}/decision', [CompetitionAppealController::class, 'decide'])->middleware('panel:admin,operator')->name('appeals.decide');
        Route::post('/appeals/{appeal}/apply', [CompetitionAppealController::class, 'apply'])->middleware('panel:admin,operator')->name('appeals.apply');
        Route::get('/reports/judges', [CompetitionReportController::class, 'judges'])->middleware('panel:admin,operator')->name('reports.judges');
        Route::get('/reports/audit', [CompetitionReportController::class, 'audit'])->middleware('panel:admin,operator')->name('reports.audit');
        Route::get('/reports/medals', [CompetitionReportController::class, 'medals'])->middleware('panel:admin,operator')->name('reports.medals');
        Route::post('/archive', [CompetitionReportController::class, 'archive'])->middleware('panel:admin')->name('tournaments.archive');
        Route::post('/restore', [CompetitionReportController::class, 'restore'])->middleware('panel:admin')->name('tournaments.restore');
        Route::get('/setup', [TournamentController::class, 'setup'])->middleware('panel:admin')->name('tournaments.setup');
        Route::post('/courts', [OperationsController::class, 'court'])->middleware('panel:admin')->name('operations.court');
        Route::put('/courts/{court}', [OperationsController::class, 'updateCourt'])->middleware('panel:admin')->name('operations.court.update');
        Route::post('/members', [OperationsController::class, 'member'])->middleware('panel:admin')->name('operations.member');
        Route::post('/categories', [OperationsController::class, 'category'])->middleware('panel:admin')->name('operations.category');
        Route::put('/categories/{category}', [OperationsController::class, 'category'])->middleware('panel:admin')->name('operations.category.update');
        Route::put('/categories/{category}/timing', [OperationsController::class, 'timing'])->middleware('panel:admin')->name('operations.category.timing');
        Route::post('/categories/{category}/entries', [OperationsController::class, 'entry'])->middleware('panel:admin')->name('operations.entry');
        Route::patch('/entries/{entry}/status', [OperationsController::class, 'entryStatus'])->middleware('panel:admin')->name('operations.entry.status');
        Route::put('/entries/{entry}', [OperationsController::class, 'updateEntry'])->middleware('panel:admin')->name('operations.entry.update');
        Route::post('/categories/{category}/rounds', [OperationsController::class, 'schedule'])->middleware('panel:admin')->name('operations.schedule');
        Route::post('/categories/{category}/forms/draw', [OperationsController::class, 'drawForms'])->middleware('panel:admin')->name('operations.forms.draw');
        Route::post('/categories/{category}/forms/assign', [OperationsController::class, 'assignForms'])->middleware('panel:admin')->name('operations.forms.assign');
        Route::post('/performances/{performance}/command', [OperationsController::class, 'command'])->middleware('panel:operator')->name('operations.command');
        Route::get('/performances/{performance}/music', [OperationsController::class, 'music'])->middleware('panel:admin,operator')->name('operations.performance.music');
        Route::post('/performances/{performance}/proxy-scores', ProxyScoreController::class)->middleware('panel:operator')->name('operations.proxy-score');
        Route::post('/performances/{performance}/proxy-scores/{scoreSheet}/confirm', [ProxyScoreController::class, 'confirm'])->middleware('panel:operator')->name('operations.proxy-score.confirm');
        Route::post('/bouts/{bout}/resolve', [OperationsController::class, 'resolve'])->middleware('panel:operator')->name('operations.resolve');
        Route::post('/complete', [OperationsController::class, 'complete'])->middleware('panel:admin')->name('operations.complete');
        Route::get('/judge', [JudgingController::class, 'index'])->middleware('panel:judge')->name('judging.index');
        Route::post('/performances/{performance}/scores', [JudgingController::class, 'store'])->middleware('panel:judge')->name('judging.store');
        Route::get('/scoreboard', [ScoreboardController::class, 'show'])->middleware('panel:display')->name('scoreboard.show');
        Route::get('/scoreboard/{view}', [ScoreboardController::class, 'page'])->middleware('panel:display')->name('scoreboard.page');
        Route::get('/rtds', [ScoreboardController::class, 'rtds'])->middleware('panel:display')->name('scoreboard.rtds');
        Route::get('/display-data', [ScoreboardController::class, 'data'])->middleware('panel:display')->name('scoreboard.data');
        Route::get('/results.csv', [ScoreboardController::class, 'export'])->middleware('panel:admin,operator')->name('scoreboard.export');
        Route::get('/results', [ScoreboardController::class, 'results'])->middleware('panel:admin,operator')->name('scoreboard.results');
        Route::get('/results.xlsx', [ScoreboardController::class, 'excel'])->middleware('panel:admin,operator')->name('scoreboard.excel');
    });
});
