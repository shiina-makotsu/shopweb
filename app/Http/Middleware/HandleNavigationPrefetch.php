<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HandleNavigationPrefetch
{
    public function handle(Request $request, Closure $next): Response
    {
        $purpose = $request->header('X-ShopWeb-Purpose');

        if (! in_array($purpose, ['storefront-prefetch', 'admin-prefetch'], true)) {
            return $next($request);
        }

        $session = $request->session();
        $previousIntendedUrl = $session->get('url.intended');
        $response = $next($request);

        if ($previousIntendedUrl === null) {
            $session->forget('url.intended');
        } else {
            $session->put('url.intended', $previousIntendedUrl);
        }

        if ($response->isRedirection()) {
            return response()->noContent()->header('X-ShopWeb-Prefetch', 'redirect-skipped');
        }

        return $response;
    }
}
