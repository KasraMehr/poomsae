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
            Route::get('/tournaments/{tournament}/display-data', [CompetitionController::class, 'displayData'])->middleware('panel:display');
            Route::get('/tournaments/{tournament}/performances/{performance}/result', [CompetitionController::class, 'result']);
            Route::get('/tournaments/{tournament}/tkdhub/divisions/{division}/results', [TkdhubController::class, 'results'])->name('api.tkdhub.results');
        });
        Route::post('/tournaments/{tournament}/performances/{performance}/scores', [CompetitionController::class, 'score'])->middleware(['abilities:scores:write', 'panel:judge']);
        Route::put('/tournaments/{tournament}/categories/{category}/management-snapshot', [CompetitionController::class, 'import'])->scopeBindings()->middleware(['abilities:competition:manage', 'panel:admin']);
        Route::post('/tournaments/{tournament}/categories', [CompetitionController::class, 'category'])->middleware(['abilities:competition:manage', 'panel:admin']);
        Route::prefix('/tournaments/{tournament}/tkdhub')->middleware(['abilities:competition:manage', 'panel:admin'])->group(function () {
            Route::put('/divisions', [TkdhubController::class, 'store'])->name('api.tkdhub.import');
            Route::post('/pull', [TkdhubController::class, 'pull'])->name('api.tkdhub.pull');
            Route::post('/divisions/{division}/schedule', [TkdhubController::class, 'schedule'])->name('api.tkdhub.schedule');
            Route::post('/divisions/{division}/push', [TkdhubController::class, 'push'])->name('api.tkdhub.push');
        });
    });
});
