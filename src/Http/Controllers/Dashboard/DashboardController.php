<?php

namespace Ahs12\Setanjo\Http\Controllers\Dashboard;

use Ahs12\Setanjo\Setanjo;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DashboardController extends Controller
{
    /**
     * The single page that hosts the dashboard app.
     */
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        return view('setanjo::dashboard', [
            'config' => [
                'api' => Str::beforeLast(route('setanjo.api.settings.index'), '/settings'),
                'canManage' => Setanjo::canManage($request),
                'readonly' => (bool) config('setanjo.dashboard.readonly', false),
                'history' => (bool) config('setanjo.history.enabled', false),
                'definitions' => count($this->setanjo->definitions()->all()),
                'scopes' => $this->scopes->summary(),
                'user' => $user?->getAttribute('name') ?? $user?->getAuthIdentifier(),
            ],
        ]);
    }
}
