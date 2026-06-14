<?php

namespace App\Support;

use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class ArticleContent
{
    /**
     * @return array{html: HtmlString, toc: array<int, array{level: int, title: string, id: string}>}
     */
    public static function render(?string $markdown): array
    {
        $html = Markdown::render($markdown)->toHtml();
        $toc = [];

        $html = preg_replace_callback('/<h([1-6])>(.*?)<\/h\1>/s', function (array $matches) use (&$toc): string {
            $level = (int) $matches[1];
            $rawTitle = trim(strip_tags(html_entity_decode($matches[2], ENT_QUOTES | ENT_HTML5, 'UTF-8')));

            if ($rawTitle === '') {
                return $matches[0];
            }

            $idBase = Str::slug($rawTitle) ?: trim((string) preg_replace('/[^\pL\pN\-_]+/u', '-', $rawTitle), '-');
            $idBase = $idBase !== '' ? $idBase : 'section';
            $id = $idBase;
            $suffix = 2;

            while (collect($toc)->contains(fn (array $item): bool => $item['id'] === $id)) {
                $id = $idBase.'-'.$suffix;
                $suffix++;
            }

            $toc[] = [
                'level' => $level,
                'title' => $rawTitle,
                'id' => $id,
            ];

            return sprintf(
                '<h%d id="%s"><a class="scroll-mt-24 no-underline" href="#%s">%s</a></h%d>',
                $level,
                e($id),
                e($id),
                $matches[2],
                $level,
            );
        }, $html) ?? $html;

        return [
            'html' => new HtmlString($html),
            'toc' => $toc,
        ];
    }
}
