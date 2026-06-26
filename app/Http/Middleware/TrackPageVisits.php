<?php

namespace App\Http\Middleware;

use App\Models\AnalyticsEvent;
use App\Services\AnalyticsTracker;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackPageVisits
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->shouldTrack($request, $response)) {
            app(AnalyticsTracker::class)->track($request, AnalyticsEvent::PAGE_VIEW, [
                'source' => $request->is('admin') || $request->is('admin/*') ? 'admin_auto' : 'frontend_auto',
            ]);
        }

        return $response;
    }

    private function shouldTrack(Request $request, Response $response): bool
    {
        if ($request->attributes->get(AnalyticsTracker::REQUEST_PAGE_VIEW_TRACKED)) {
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
}
