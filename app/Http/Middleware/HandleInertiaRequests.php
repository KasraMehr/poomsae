<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => ['user' => $request->user()?->only('id', 'name', 'email', 'is_admin')],
            'flash' => ['success' => fn () => $request->session()->get('success')],
            'urls' => [
                'dashboard' => route('dashboard'), 'login' => route('login'),
                'logout' => route('logout'), 'tournaments' => route('tournaments.index'),
            ],
        ];
    }
}
