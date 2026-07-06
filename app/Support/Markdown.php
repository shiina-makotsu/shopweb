<?php

namespace App\Support;

use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class Markdown
{
    public static function render(?string $markdown): HtmlString
    {
        [$prepared, $icons] = FontAwesome::extractShortcodes($markdown);

        $html = Str::markdown($prepared, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        return new HtmlString(strtr($html, $icons));
    }
}
