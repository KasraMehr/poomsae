<?php

use App\Http\Controllers\AuthController;
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
    Route::prefix('tournaments/{tournament}')->group(function () {
        Route::get('/setup', [TournamentController::class, 'setup'])->name('tournaments.setup');
        Route::post('/courts', [OperationsController::class, 'court'])->name('operations.court');
        Route::post('/members', [OperationsController::class, 'member'])->name('operations.member');
        Route::post('/categories', [OperationsController::class, 'category'])->name('operations.category');
        Route::put('/categories/{category}', [OperationsController::class, 'category'])->name('operations.category.update');
        Route::post('/categories/{category}/entries', [OperationsController::class, 'entry'])->name('operations.entry');
        Route::patch('/entries/{entry}/status', [OperationsController::class, 'entryStatus'])->name('operations.entry.status');
        Route::post('/categories/{category}/rounds', [OperationsController::class, 'schedule'])->name('operations.schedule');
        Route::post('/categories/{category}/forms/draw', [OperationsController::class, 'drawForms'])->name('operations.forms.draw');
        Route::post('/performances/{performance}/command', [OperationsController::class, 'command'])->name('operations.command');
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
    });
});
