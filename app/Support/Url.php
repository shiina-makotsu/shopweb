<?php

namespace App\Support;

class Url
{
    public static function relative(string|\Stringable|null $url): string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return '';
        }

        if (str_starts_with($url, '#') || str_starts_with($url, '/')) {
            return $url;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);

        if (! in_array($scheme, ['http', 'https'], true)) {
            return $url;
        }

        $path = parse_url($url, PHP_URL_PATH) ?: '/';
        $query = parse_url($url, PHP_URL_QUERY);
        $fragment = parse_url($url, PHP_URL_FRAGMENT);

        $relative = '/'.ltrim($path, '/');

        if ($query !== null && $query !== '') {
            $relative .= '?'.$query;
        }

        if ($fragment !== null && $fragment !== '') {
            $relative .= '#'.$fragment;
        }

        return $relative;
    }

    public static function route(string|\BackedEnum $name, mixed $parameters = []): string
    {
        return route($name, $parameters, false);
    }
}
