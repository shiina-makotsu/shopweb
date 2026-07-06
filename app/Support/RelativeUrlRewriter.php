<?php

namespace App\Support;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RelativeUrlRewriter
{
    public function rewriteResponse(Response $response, Request $request): Response
    {
        $this->rewriteLocationHeader($response, $request);

        if (! $this->isHtmlResponse($response)) {
            return $response;
        }

        $content = $response->getContent();

        if (! is_string($content) || $content === '') {
            return $response;
        }

        $response->setContent($this->rewriteLocalUrls($content, $request));

        return $response;
    }

    public function rewriteLocationHeader(Response $response, Request $request): void
    {
        $location = $response->headers->get('Location');

        if (! $location) {
            return;
        }

        $relative = $this->toRelativeIfLocal($location, $request);

        if ($relative !== $location) {
            $response->headers->set('Location', $relative);
        }
    }

    public function rewriteLocalUrls(string $content, Request $request): string
    {
        $masked = [];
        $content = preg_replace_callback(
            '~<[^>]*\sdata-absolute-url(?:=(["\']).*?\1|[^\s>]*)?[^>]*>~is',
            function (array $matches) use (&$masked): string {
                $key = '___SHOPWEB_ABSOLUTE_URL_'.count($masked).'___';
                $masked[$key] = $matches[0];

                return $key;
            },
            $content,
        ) ?? $content;

        $content = preg_replace_callback(
            '~(?<![A-Za-z0-9+.-])(?:https?:)?//[^\s"\'<>()]+~i',
            fn (array $matches): string => $this->toRelativeIfLocal($matches[0], $request),
            $content,
        ) ?? $content;

        if ($masked !== []) {
            $content = strtr($content, $masked);
        }

        return $content;
    }

    public function toRelativeIfLocal(string $url, Request $request): string
    {
        $parsedUrl = str_starts_with($url, '//') ? 'http:'.$url : $url;
        $host = parse_url($parsedUrl, PHP_URL_HOST);

        if (! is_string($host) || ! in_array($this->normalizeHost($host), $this->localHosts($request), true)) {
            return $url;
        }

        $path = parse_url($parsedUrl, PHP_URL_PATH) ?: '/';
        $query = parse_url($parsedUrl, PHP_URL_QUERY);
        $fragment = parse_url($parsedUrl, PHP_URL_FRAGMENT);

        $relative = '/'.ltrim($path, '/');

        if (is_string($query) && $query !== '') {
            $relative .= '?'.$query;
        }

        if (is_string($fragment) && $fragment !== '') {
            $relative .= '#'.$fragment;
        }

        return $relative;
    }

    /**
     * @return array<int, string>
     */
    private function localHosts(Request $request): array
    {
        $hosts = [
            $request->getHost(),
            $request->headers->get('host'),
            $request->headers->get('x-forwarded-host'),
            $request->server->get('HTTP_HOST'),
            $request->server->get('SERVER_NAME'),
            parse_url((string) config('app.url'), PHP_URL_HOST),
        ];

        return array_values(array_unique(array_filter(
            array_map(fn ($host): ?string => is_string($host) ? $this->normalizeHost($host) : null, $hosts),
        )));
    }

    private function normalizeHost(string $host): string
    {
        $host = strtolower(trim($host));

        if (str_starts_with($host, '[')) {
            return trim(strtok($host, ']') ?: $host, '[]');
        }

        if (substr_count($host, ':') === 1) {
            return explode(':', $host, 2)[0];
        }

        return trim($host, '[]');
    }

    private function isHtmlResponse(Response $response): bool
    {
        $contentType = strtolower((string) $response->headers->get('Content-Type'));

        return str_contains($contentType, 'text/html');
    }
}
