<?php

namespace App\Http\Middleware;

use App\Services\StorefrontCache;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ShowFirstVisitLoadingPage
{
    public const COOKIE = 'shop_first_visit_ready';
    public const RECENT_COOKIE = 'shop_loading_recent';

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->shouldShow($request)) {
            return $next($request);
        }

        return redirect()
            ->route('loading.show', ['to' => $request->fullUrl()])
            ->cookie(self::COOKIE, '1', 60 * 24 * 365, null, null, $request->isSecure(), false, false, 'Lax')
            ->cookie(self::RECENT_COOKIE, '1', 5, null, null, $request->isSecure(), false, false, 'Lax');
    }

    private function shouldShow(Request $request): bool
    {
        if (! (bool) config('shop.first_visit_loading.enabled', true)) {
            return false;
        }

        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return false;
        }

        if ($request->expectsJson() || $request->ajax()) {
            return false;
        }

        if ($request->query->has('first_visit_ready')) {
            return false;
        }

        if ($request->is(
            'loading',
            'loading/*',
            'install',
            'install/*',
            'up',
            'admin',
            'admin/*',
            'livewire*',
            'build*',
            'css*',
            'js*',
            'storage*',
            'uploads*',
            'favicon.ico',
            'robots.txt',
        )) {
            return false;
        }

        if ($request->cookies->has(self::RECENT_COOKIE)) {
            return false;
        }

        $hasVisited = $request->cookies->has(self::COOKIE);
        $cacheCold = ! StorefrontCache::isWarm();

        if ($hasVisited && ! $cacheCold) {
            return false;
        }

        return ! $cacheCold || (bool) config('shop.first_visit_loading.show_on_cold_cache', true);
    }
}
