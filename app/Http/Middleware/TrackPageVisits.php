<?php

namespace App\Http\Middleware;

use App\Models\AnalyticsEvent;
use App\Services\AnalyticsTracker;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class TrackPageVisits
{
    private const SHOULD_TRACK_ATTRIBUTE = 'shopweb.analytics.should_track_auto_page_view';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->shouldTrack($request, $response)) {
            $request->attributes->set(self::SHOULD_TRACK_ATTRIBUTE, true);
        }

        return $response;
    }

    public function terminate(Request $request, Response $response): void
    {
        if (! $request->attributes->get(self::SHOULD_TRACK_ATTRIBUTE)) {
            return;
        }

        if (! Cache::add($this->dedupeKey($request), true, now()->addSeconds(15))) {
            return;
        }

        app(AnalyticsTracker::class)->track($request, AnalyticsEvent::PAGE_VIEW, [
            'source' => $request->is('admin') || $request->is('admin/*') ? 'admin_auto' : 'frontend_auto',
        ]);
    }

    private function shouldTrack(Request $request, Response $response): bool
    {
        if ($request->attributes->get(AnalyticsTracker::REQUEST_PAGE_VIEW_TRACKED)) {
            return false;
        }

        if ($request->is('admin', 'admin/*') && ! (bool) config('shop.analytics.track_admin_page_views', false)) {
            return false;
        }

        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return false;
        }

        if ($response->getStatusCode() >= 400) {
            return false;
        }

        if ($request->expectsJson() || $request->ajax()) {
            return false;
        }

        if ($request->is(
            '_debugbar*',
            'build*',
            'css*',
            'js*',
            'storage*',
            'uploads*',
            'livewire*',
            'admin/exports*',
            'admin/backups*',
            'support/messages/*/attachment',
            'messages/attachments/*',
            'orders/*/digital-delivery/*',
        )) {
            return false;
        }

        return true;
    }

    private function dedupeKey(Request $request): string
    {
        return 'shop:analytics:auto-page-view:'.sha1(implode('|', [
            $request->hasSession()
                ? (string) $request->session()->getId()
                : (string) ($request->cookies->get(config('session.cookie')) ?: $request->userAgent()),
            (string) optional($request->user())->getAuthIdentifier(),
            $request->path(),
            (string) $request->ip(),
        ]));
    }
}
