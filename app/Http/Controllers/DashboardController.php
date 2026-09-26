<?php

namespace App\Http\Controllers;

use App\Models\Tournament;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $query = Tournament::query()->visibleTo($request->user());

        return Inertia::render('Dashboard', [
            'stats' => [
                'total' => (clone $query)->count(),
                'running' => (clone $query)->where('status', 'running')->count(),
                'draft' => (clone $query)->where('status', 'draft')->count(),
            ],
            'tournaments' => $query->withCount('categories', 'courts')->latest()->limit(6)->get(),
        ]);
    }
}
