<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CompetitionSetup;
use App\Actions\CompetitionView;
use App\Actions\ImportManagementSnapshot;
use App\Actions\SubmitScore;
use App\Http\Controllers\Controller;
use App\Http\Requests\CompetitionSetupRequest;
use App\Http\Requests\ManagementSnapshotRequest;
use App\Http\Requests\SubmitScoreRequest;
use App\Models\Category;
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

    public function import(ManagementSnapshotRequest $request, Tournament $tournament, Category $category, ImportManagementSnapshot $import): JsonResponse
    {
        return response()->json(['data' => $import->handle($request->user(), $tournament, $category, $request->validated())]);
    }

    public function category(CompetitionSetupRequest $request, Tournament $tournament, CompetitionSetup $setup): JsonResponse
    {
        $category = $setup->category($request->user(), $tournament, $request->validated());

        return response()->json(['data' => ['id' => $category->id, 'name' => $category->name, 'planned_round_count' => $category->planned_round_count,
            'form_pool' => $category->forms()->get(['poomsae_forms.id', 'name'])->map(fn ($form) => ['id' => $form->id, 'name' => $form->name]),
            'stages' => $category->rounds()->orderBy('sequence')->get(['id', 'name', 'sequence']),
        ]], 201);
    }
}
