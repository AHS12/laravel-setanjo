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
                'brand' => $this->brand(),
                'home' => config('setanjo.dashboard.home', '/'),
                'featureMiddleware' => config('setanjo.features.middleware_alias', 'setanjo.feature'),
            ],
            'accent' => $this->brand()['color'],
        ]);
    }

    /**
     * @return array{title: string, logo: string|null, color: string|null}
     */
    protected function brand(): array
    {
        $brand = (array) config('setanjo.dashboard.brand', []);
        $color = $brand['color'] ?? null;
        $logo = $brand['logo'] ?? null;

        return [
            'title' => (string) ($brand['title'] ?? 'Setanjo'),
            // Only plain URLs and paths for the logo, only hex for the colour (it is written into CSS).
            'logo' => is_string($logo) && preg_match('#^(https?://|/)#i', $logo) ? $logo : null,
            'color' => is_string($color) && preg_match('/^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i', $color) ? $color : null,
        ];
    }
}
