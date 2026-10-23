<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CompetitionAppealController;
use App\Http\Controllers\CompetitionReportController;
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
    Route::resource('tournaments', TournamentController::class)->only(['index', 'store', 'show', 'update']);
    Route::prefix('tournaments/{tournament}')->group(function () {
        Route::get('/appeals', [CompetitionAppealController::class, 'index'])->name('appeals.index');
        Route::post('/appeals', [CompetitionAppealController::class, 'store'])->name('appeals.store');
        Route::post('/appeals/{appeal}/review', [CompetitionAppealController::class, 'review'])->name('appeals.review');
        Route::post('/appeals/{appeal}/decision', [CompetitionAppealController::class, 'decide'])->name('appeals.decide');
        Route::post('/appeals/{appeal}/apply', [CompetitionAppealController::class, 'apply'])->name('appeals.apply');
        Route::get('/reports/judges', [CompetitionReportController::class, 'judges'])->name('reports.judges');
        Route::get('/reports/audit', [CompetitionReportController::class, 'audit'])->name('reports.audit');
        Route::get('/reports/medals', [CompetitionReportController::class, 'medals'])->name('reports.medals');
        Route::post('/archive', [CompetitionReportController::class, 'archive'])->name('tournaments.archive');
        Route::post('/restore', [CompetitionReportController::class, 'restore'])->name('tournaments.restore');
        Route::get('/setup', [TournamentController::class, 'setup'])->name('tournaments.setup');
        Route::post('/courts', [OperationsController::class, 'court'])->name('operations.court');
        Route::put('/courts/{court}', [OperationsController::class, 'updateCourt'])->name('operations.court.update');
        Route::post('/members', [OperationsController::class, 'member'])->name('operations.member');
        Route::post('/categories', [OperationsController::class, 'category'])->name('operations.category');
        Route::put('/categories/{category}', [OperationsController::class, 'category'])->name('operations.category.update');
        Route::put('/categories/{category}/timing', [OperationsController::class, 'timing'])->name('operations.category.timing');
        Route::post('/categories/{category}/entries', [OperationsController::class, 'entry'])->name('operations.entry');
        Route::patch('/entries/{entry}/status', [OperationsController::class, 'entryStatus'])->name('operations.entry.status');
        Route::put('/entries/{entry}', [OperationsController::class, 'updateEntry'])->name('operations.entry.update');
        Route::post('/categories/{category}/rounds', [OperationsController::class, 'schedule'])->name('operations.schedule');
        Route::post('/categories/{category}/forms/draw', [OperationsController::class, 'drawForms'])->name('operations.forms.draw');
        Route::post('/categories/{category}/forms/assign', [OperationsController::class, 'assignForms'])->name('operations.forms.assign');
        Route::post('/performances/{performance}/command', [OperationsController::class, 'command'])->name('operations.command');
        Route::post('/performances/{performance}/timing-penalty', [OperationsController::class, 'adjustTimingPenalty'])->name('operations.performance.timing-penalty');
        Route::post('/performances/{performance}/boundary-penalty', [OperationsController::class, 'adjustBoundaryPenalty'])->name('operations.performance.boundary-penalty');
        Route::post('/performances/{performance}/referee-penalty', [OperationsController::class, 'adjustRefereePenalty'])->name('operations.performance.referee-penalty');
        Route::post('/performances/{performance}/restart', [OperationsController::class, 'recordRestart'])->name('operations.performance.restart');
        Route::get('/performances/{performance}/music', [OperationsController::class, 'music'])->name('operations.performance.music');
        Route::post('/performances/{performance}/proxy-scores', ProxyScoreController::class)->name('operations.proxy-score');
        Route::post('/performances/{performance}/proxy-scores/{scoreSheet}/confirm', [ProxyScoreController::class, 'confirm'])->name('operations.proxy-score.confirm');
        Route::post('/bouts/{bout}/resolve', [OperationsController::class, 'resolve'])->name('operations.resolve');
        Route::post('/complete', [OperationsController::class, 'complete'])->name('operations.complete');
        Route::get('/judge', [JudgingController::class, 'index'])->name('judging.index');
        Route::post('/performances/{performance}/scores', [JudgingController::class, 'store'])->name('judging.store');
        Route::get('/scoreboard', [ScoreboardController::class, 'show'])->name('scoreboard.show');
        Route::get('/rtds', [ScoreboardController::class, 'rtds'])->name('scoreboard.rtds');
        Route::get('/display-data', [ScoreboardController::class, 'data'])->name('scoreboard.data');
        Route::get('/results.csv', [ScoreboardController::class, 'export'])->name('scoreboard.export');
        Route::get('/results', [ScoreboardController::class, 'results'])->name('scoreboard.results');
        Route::get('/results.xlsx', [ScoreboardController::class, 'excel'])->name('scoreboard.excel');
    });
});
