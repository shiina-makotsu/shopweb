<?php

namespace App\Http\Controllers;

use App\Models\NavigationMenuItem;
use App\Models\AnalyticsEvent;
use App\Services\AnalyticsTracker;
use App\Services\StorefrontCache;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(Request $request, AnalyticsTracker $analytics, StorefrontCache $cache): View
    {
        if ($request->filled('invite')) {
            $request->session()->put('referral_code', strtoupper(trim((string) $request->query('invite'))));
        }

        $analytics->track($request, AnalyticsEvent::PAGE_VIEW, ['source' => 'home']);
        $featuredProducts = $cache->homeProducts('featured');
        $discountProducts = $cache->homeProducts('discount');
        $latestProducts = $cache->homeProducts('latest');
        $conceptProducts = $cache->homeProducts('concept');

        $analytics->trackProductImpressions($request, $featuredProducts, 'home_featured');
        $analytics->trackProductImpressions($request, $latestProducts, 'home_default');
        $analytics->trackProductImpressions($request, $discountProducts, 'home_discount');
        $analytics->trackProductImpressions($request, $conceptProducts, 'home_concept');

        return view('home', [
            'settings' => $cache->settings(),
            'featuredProducts' => $featuredProducts,
            'discountProducts' => $discountProducts,
            'latestProducts' => $latestProducts,
            'conceptProducts' => $conceptProducts,
            'flashSales' => $cache->flashSales(),
            'homeInfoMenuItems' => $cache->menuItems(NavigationMenuItem::PLACEMENT_HOME_INFO),
        ]);
    }
}
