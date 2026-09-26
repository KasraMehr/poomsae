<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CompetitionView;
use App\Actions\SubmitScore;
use App\Http\Controllers\Controller;
use App\Http\Requests\SubmitScoreRequest;
use App\Models\Performance;
use App\Models\Tournament;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CompetitionController extends Controller
{
    public function show(Request $request, Tournament $tournament, CompetitionView $view): JsonResponse
    {
        Gate::authorize('view', $tournament);

        return response()->json([
            'data' => $view->snapshot($tournament, $request->user()),
            'realtime' => [
                'protocol' => 'mercure-sse',
                'hub_url' => config('broadcasting.connections.mercure.public_url'),
                'auth_endpoint' => url('/api/v1/broadcasting/auth'),
                'channel' => 'private-tournaments.'.$tournament->id,
                'presence_channel' => 'presence-tournaments.'.$tournament->id.'.judges',
                'event' => 'competition.updated',
            ],
        ]);
    }

    public function score(SubmitScoreRequest $request, Tournament $tournament, Performance $performance, SubmitScore $submit): JsonResponse
    {
        $result = $submit->handle($request->user(), $tournament, $performance, $request->validated());

        return response()->json(['data' => $result]);
    }
}
