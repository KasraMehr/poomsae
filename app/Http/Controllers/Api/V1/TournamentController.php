<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TournamentResource;
use App\Models\Tournament;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class TournamentController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return TournamentResource::collection(Tournament::query()->visibleTo($request->user())->latest()->paginate(25));
    }

    public function show(Tournament $tournament): TournamentResource
    {
        Gate::authorize('view', $tournament);

        return new TournamentResource($tournament);
    }
}
