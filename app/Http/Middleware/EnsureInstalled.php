<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->isInstalled() || $this->shouldBypass($request)) {
            return $next($request);
        }

        return redirect()->route('install.show');
    }

    private function isInstalled(): bool
    {
        if (app()->environment('testing')) {
            return true;
        }

        return file_exists(storage_path('app/install.lock'));
    }

    private function shouldBypass(Request $request): bool
    {
        if ($request->is('install') || $request->is('install/*') || $request->is('up')) {
            return true;
        }

        return $request->is('build/*')
            || $request->is('uploads/*')
            || $request->is('favicon.ico')
            || $request->is('robots.txt');
    }
}
