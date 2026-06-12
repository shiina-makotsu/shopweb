<?php

namespace App\Http\Middleware;

use App\Support\RelativeUrlRewriter;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UseRelativeUrls
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        return app(RelativeUrlRewriter::class)->rewriteResponse($response, $request);
    }
}
