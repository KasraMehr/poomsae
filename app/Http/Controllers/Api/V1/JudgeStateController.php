<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\JudgeCurrentState;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JudgeStateController extends Controller
{
    public function currentState(Request $request, JudgeCurrentState $state): JsonResponse
    {
        return response()->json($state->snapshot($request->user()));
    }
}
