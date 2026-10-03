<?php

use App\Http\Controllers\Api\V1\CompetitionController;
use App\Http\Controllers\Api\V1\TokenController;
use App\Http\Controllers\Api\V1\TournamentController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/auth/token', [TokenController::class, 'store'])->middleware('throttle:login');
    Route::middleware(['auth:sanctum', 'active', 'throttle:api'])->group(function () {
        Route::post('/broadcasting/auth', fn (Request $request) => Broadcast::auth($request));
        Route::delete('/auth/token', [TokenController::class, 'destroy']);
        Route::get('/me', fn (Request $request) => ['data' => $request->user()->only('id', 'name', 'email')]);
        Route::middleware('abilities:tournaments:read')->group(function () {
            Route::get('/tournaments', [TournamentController::class, 'index']);
            Route::get('/tournaments/{tournament}', [TournamentController::class, 'show']);
            Route::get('/tournaments/{tournament}/competition', [CompetitionController::class, 'show']);
        });
        Route::post('/tournaments/{tournament}/performances/{performance}/scores', [CompetitionController::class, 'score'])->middleware('abilities:scores:write');
        Route::put('/tournaments/{tournament}/categories/{category}/management-snapshot', [CompetitionController::class, 'import'])->scopeBindings()->middleware('abilities:competition:manage');
        Route::post('/tournaments/{tournament}/categories', [CompetitionController::class, 'category'])->middleware('abilities:competition:manage');
    });
});
