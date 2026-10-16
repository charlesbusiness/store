<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                ] : null,
            ],
            'flash' => [
                'status' => $request->session()->get('status'),
            ],
            'currency' => config('stockly.currency', '₦'),
            'moneyDecimals' => (int) config('stockly.money_decimals', 2),
            'ziggy' => fn () => $this->ziggyConfig($request),
        ]);
    }

    /**
     * Lazily provide Ziggy configuration only when needed.
     */
    private function ziggyConfig(Request $request): array
    {
        $ziggy = new Ziggy();
        $ziggyConfig = $ziggy->toArray();

        return [
            'routes' => $ziggyConfig['routes'] ?? [],
            'url' => $ziggyConfig['url'] ?? config('app.url'),
            'port' => $ziggyConfig['port'] ?? $request->getPort(),
            'defaults' => $ziggyConfig['defaults'] ?? [],
        ];
    }
}
