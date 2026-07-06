<?php

namespace App\Http\Controllers;

use App\Http\Middleware\ShowFirstVisitLoadingPage;
use App\Models\SiteSetting;
use App\Services\CachePrewarmService;
use App\Support\LoadingPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class LoadingController extends Controller
{
    public function show(Request $request): Response
    {
        $settings = SiteSetting::query()->first();
        $html = LoadingPage::render(
            $settings?->loadingPageConfig() ?? LoadingPage::defaults(),
            $this->targetUrl($request),
            route('loading.prepare'),
            $settings?->site_name ?: config('app.name', 'ShopWeb'),
        );

        return response($html->toHtml())
            ->header('Content-Type', 'text/html; charset=UTF-8')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate')
            ->cookie(ShowFirstVisitLoadingPage::COOKIE, '1', 60 * 24 * 365, null, null, $request->isSecure(), false, false, 'Lax')
            ->cookie(ShowFirstVisitLoadingPage::RECENT_COOKIE, '1', 5, null, null, $request->isSecure(), false, false, 'Lax');
    }

    public function prepare(Request $request, CachePrewarmService $prewarm): JsonResponse
    {
        $result = $prewarm->warm();

        return response()
            ->json(['ok' => true, 'warmed' => $result['warmed']])
            ->cookie(ShowFirstVisitLoadingPage::COOKIE, '1', 60 * 24 * 365, null, null, $request->isSecure(), false, false, 'Lax')
            ->cookie(ShowFirstVisitLoadingPage::RECENT_COOKIE, '1', 5, null, null, $request->isSecure(), false, false, 'Lax');
    }

    private function targetUrl(Request $request): string
    {
        $target = (string) $request->query('to', route('home'));

        if (! str_starts_with($target, $request->getSchemeAndHttpHost())) {
            return route('home');
        }

        return $target;
    }
}
