<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TokenController extends Controller
{
    public function store(LoginRequest $request): JsonResponse
    {
        $user = $request->authenticatedUser();
        $abilities = ['tournaments:read', 'scores:write'];
        if ($user->is_admin || $user->tournaments()->wherePivot('role', 'manager')->exists()) {
            $abilities[] = 'competition:manage';
        }
        $token = $user->createToken($request->input('device_name', 'android'), $abilities, now()->addHours(12));

        return response()->json(['token' => $token->plainTextToken, 'token_type' => 'Bearer', 'expires_at' => $token->accessToken->expires_at, 'user' => $user->only('id', 'name', 'email')], 201);
    }

    public function destroy(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }
}
