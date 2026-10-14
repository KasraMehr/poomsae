<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ImportTkdhubDivision;
use App\Actions\ScheduleRound;
use App\Actions\TkdhubConnection;
use App\Actions\TkdhubResults;
use App\Http\Controllers\Controller;
use App\Http\Requests\ScheduleRoundRequest;
use App\Http\Requests\TkdhubDivisionRequest;
use App\Http\Requests\TkdhubTransferRequest;
use App\Http\Resources\TkdhubResultsResource;
use App\Models\Category;
use App\Models\Tournament;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TkdhubController extends Controller
{
    public function store(TkdhubDivisionRequest $request, Tournament $tournament, ImportTkdhubDivision $import): JsonResponse
    {
        return $this->receipt($import->handle($request->user(), $tournament, $request->validated()));
    }

    public function results(Request $request, Tournament $tournament, string $division, TkdhubResults $results): TkdhubResultsResource
    {
        Gate::authorize('operate', $tournament);
        $category = $this->category($tournament, $division);

        return new TkdhubResultsResource($results->handle($request->user(), $tournament, $category));
    }

    public function schedule(ScheduleRoundRequest $request, Tournament $tournament, string $division, ScheduleRound $schedule): JsonResponse
    {
        $category = $this->category($tournament, $division);
        $schedule->handle($request->user(), $tournament, $category, $request->validated());

        return response()->json(['eventId' => $tournament->tkdhub_event_id, 'divisionId' => $division, 'scheduled' => true]);
    }

    public function pull(TkdhubTransferRequest $request, Tournament $tournament, TkdhubConnection $connection): JsonResponse
    {
        $data = $request->validated();

        return $this->receipt($connection->pull($request->user(), $tournament, $data['eventId'], $data['divisionId'], (int) ($data['judgeCount'] ?? 5)));
    }

    public function push(TkdhubTransferRequest $request, Tournament $tournament, string $division, TkdhubConnection $connection): JsonResponse
    {
        return response()->json($connection->push($request->user(), $tournament, $this->category($tournament, $division), $request->validated('reason')));
    }

    private function category(Tournament $tournament, string $division): Category
    {
        return $tournament->categories()->where('management_source', 'tkdhub')->where('tkdhub_division_id', $division)->firstOrFail();
    }

    /** @param array{category:Category,replayed:bool} $result */
    private function receipt(array $result): JsonResponse
    {
        $category = $result['category'];

        return response()->json([
            'eventId' => $category->management_snapshot['eventId'], 'divisionId' => $category->tkdhub_division_id,
            'tournamentId' => $category->tournament_id, 'categoryId' => $category->id,
            'version' => $category->management_version, 'replayed' => $result['replayed'],
            'resultsUrl' => route('api.tkdhub.results', ['tournament' => $category->tournament_id, 'division' => $category->tkdhub_division_id]),
            'scheduleUrl' => route('api.tkdhub.schedule', ['tournament' => $category->tournament_id, 'division' => $category->tkdhub_division_id]),
        ])->header('ETag', '"'.$category->management_version.'"');
    }
}
