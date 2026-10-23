<?php

use App\Http\Controllers\Api\V1\CompetitionController;
use App\Http\Controllers\Api\V1\TkdhubController;
use App\Http\Controllers\Api\V1\TokenController;
use App\Http\Controllers\Api\V1\TournamentController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/auth/token', [TokenController::class, 'store'])->middleware('throttle:login');
    Route::middleware(['auth:sanctum', 'active', 'throttle:api'])->group(function () {
        Route::post('/broadcasting/auth', fn (Request $request) => Broadcast::auth($request))->middleware('abilities:tournaments:read');
        Route::delete('/auth/token', [TokenController::class, 'destroy']);
        Route::get('/me', fn (Request $request) => ['data' => $request->user()->only('id', 'name', 'email')]);
        Route::middleware('abilities:tournaments:read')->group(function () {
            Route::get('/tournaments', [TournamentController::class, 'index']);
            Route::get('/tournaments/{tournament}', [TournamentController::class, 'show']);
            Route::get('/tournaments/{tournament}/competition', [CompetitionController::class, 'show']);
            Route::get('/tournaments/{tournament}/display-data', [CompetitionController::class, 'displayData']);
            Route::get('/tournaments/{tournament}/performances/{performance}/result', [CompetitionController::class, 'result']);
            Route::get('/tournaments/{tournament}/tkdhub/divisions/{division}/results', [TkdhubController::class, 'results'])->name('api.tkdhub.results');
        });
        Route::post('/tournaments/{tournament}/performances/{performance}/scores', [CompetitionController::class, 'score'])->middleware('abilities:scores:write');
        Route::middleware('ability:competition:operate,competition:manage')->group(function () {
            Route::post('/tournaments/{tournament}/performances/{performance}/command', [CompetitionController::class, 'command']);
            Route::post('/tournaments/{tournament}/performances/{performance}/scores/batch', [CompetitionController::class, 'batchScores']);
            Route::post('/tournaments/{tournament}/performances/{performance}/proxy-scores', [CompetitionController::class, 'proxyScore']);
            Route::post('/tournaments/{tournament}/performances/{performance}/proxy-scores/{scoreSheet}/confirm', [CompetitionController::class, 'confirmProxyScore']);
            Route::post('/tournaments/{tournament}/performances/{performance}/referee-penalty', [CompetitionController::class, 'refereePenalty']);
            Route::post('/tournaments/{tournament}/performances/{performance}/timing-penalty', [CompetitionController::class, 'timingPenalty']);
            Route::post('/tournaments/{tournament}/performances/{performance}/boundary-penalty', [CompetitionController::class, 'boundaryPenalty']);
        });
        Route::put('/tournaments/{tournament}/categories/{category}/management-snapshot', [CompetitionController::class, 'import'])->scopeBindings()->middleware('abilities:competition:manage');
        Route::post('/tournaments/{tournament}/categories', [CompetitionController::class, 'category'])->middleware('abilities:competition:manage');
        Route::prefix('/tournaments/{tournament}/tkdhub')->middleware('abilities:competition:manage')->group(function () {
            Route::put('/divisions', [TkdhubController::class, 'store'])->name('api.tkdhub.import');
            Route::post('/pull', [TkdhubController::class, 'pull'])->name('api.tkdhub.pull');
            Route::post('/divisions/{division}/schedule', [TkdhubController::class, 'schedule'])->name('api.tkdhub.schedule');
            Route::post('/divisions/{division}/push', [TkdhubController::class, 'push'])->name('api.tkdhub.push');
        });
    });
});
