<?php

namespace App\Support;

use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class PageBlockRenderer
{
    public static function render(?array $blocks): HtmlString
    {
        if (blank($blocks)) {
            return new HtmlString('');
        }

        $html = collect($blocks)
            ->map(fn (mixed $block): string => is_array($block) ? self::renderBlock($block) : '')
            ->filter()
            ->implode("\n");

        if ($html === '') {
            return new HtmlString('');
        }

        return new HtmlString('<div class="page-blocks space-y-4">'.$html.'</div>');
    }

    private static function renderBlock(array $block): string
    {
        $type = (string) ($block['type'] ?? '');
        $data = is_array($block['data'] ?? null) ? $block['data'] : [];

        return match ($type) {
            'heading' => self::heading($data),
            'paragraph' => self::markdownPanel($data['content'] ?? ''),
            'quote' => self::quote($data),
            'image' => self::image($data),
            'button' => self::button($data),
            'notice' => self::notice($data),
            'columns' => self::columns($data),
            default => '',
        };
    }

    private static function heading(array $data): string
    {
        $text = trim((string) ($data['text'] ?? ''));

        if ($text === '') {
            return '';
        }

        $level = in_array((string) ($data['level'] ?? 'h2'), ['h2', 'h3', 'h4'], true) ? (string) $data['level'] : 'h2';
        $class = match ($level) {
            'h3' => 'text-lg',
            'h4' => 'text-base',
            default => 'text-xl',
        };

        return sprintf(
            '<%1$s class="%2$s font-semibold leading-tight text-slate-950">%3$s</%1$s>',
            $level,
            $class,
            e($text),
        );
    }

    private static function markdownPanel(?string $content): string
    {
        if (blank($content)) {
            return '';
        }

        return '<div class="content-body text-sm leading-7 text-slate-700">'.Markdown::render($content)->toHtml().'</div>';
    }

    private static function quote(array $data): string
    {
        $content = trim((string) ($data['content'] ?? ''));

        if ($content === '') {
            return '';
        }

        $author = trim((string) ($data['author'] ?? ''));

        return '<blockquote class="border-l-4 border-blue-300 bg-blue-50 px-4 py-3 text-sm leading-7 text-slate-700">'
            .Markdown::render($content)->toHtml()
            .($author !== '' ? '<footer class="mt-2 text-xs font-medium text-blue-800">'.e($author).'</footer>' : '')
            .'</blockquote>';
    }

    private static function image(array $data): string
    {
        $url = self::safeUrl($data['url'] ?? null);

        if ($url === null) {
            return '';
        }

        $alt = trim((string) ($data['alt'] ?? ''));
        $caption = trim((string) ($data['caption'] ?? ''));

        return '<figure class="overflow-hidden rounded-sm border border-slate-200 bg-slate-50">'
            .'<img class="w-full object-cover" src="'.e($url).'" alt="'.e($alt).'">'
            .($caption !== '' ? '<figcaption class="border-t border-slate-200 px-3 py-2 text-xs text-slate-600">'.e($caption).'</figcaption>' : '')
            .'</figure>';
    }

    private static function button(array $data): string
    {
        $label = trim((string) ($data['label'] ?? ''));
        $url = self::safeUrl($data['url'] ?? null);

        if ($label === '' || $url === null) {
            return '';
        }

        $style = (string) ($data['style'] ?? 'primary');
        $class = match ($style) {
            'secondary' => 'border-slate-300 bg-white text-slate-800 hover:bg-slate-50',
            default => 'border-blue-700 bg-blue-700 text-white hover:bg-blue-800',
        };

        return '<p><a class="inline-flex rounded-sm border px-4 py-2 text-sm font-medium '.$class.'" href="'.e($url).'">'.e($label).'</a></p>';
    }

    private static function notice(array $data): string
    {
        $content = trim((string) ($data['content'] ?? ''));

        if ($content === '') {
            return '';
        }

        $type = (string) ($data['type'] ?? 'info');
        $class = match ($type) {
            'success' => 'border-emerald-300 bg-emerald-50 text-emerald-900',
            'warning' => 'border-amber-300 bg-amber-50 text-amber-900',
            'danger' => 'border-red-300 bg-red-50 text-red-900',
            default => 'border-blue-300 bg-blue-50 text-blue-950',
        };

        return '<div class="rounded-sm border px-4 py-3 text-sm leading-7 '.$class.'">'.Markdown::render($content)->toHtml().'</div>';
    }

    private static function columns(array $data): string
    {
        $left = trim((string) ($data['left'] ?? ''));
        $right = trim((string) ($data['right'] ?? ''));

        if ($left === '' && $right === '') {
            return '';
        }

        return '<div class="grid gap-4 md:grid-cols-2">'
            .'<div class="content-body rounded-sm border border-slate-200 bg-white px-4 py-4 text-sm leading-7">'.Markdown::render($left)->toHtml().'</div>'
            .'<div class="content-body rounded-sm border border-slate-200 bg-white px-4 py-4 text-sm leading-7">'.Markdown::render($right)->toHtml().'</div>'
            .'</div>';
    }

    private static function safeUrl(mixed $value): ?string
    {
        $url = trim((string) $value);

        if ($url === '') {
            return null;
        }

        if (Str::startsWith($url, '/') && ! Str::startsWith($url, '//')) {
            return $url;
        }

        if (Str::startsWith($url, ['http://', 'https://'])) {
            return $url;
        }

        return null;
    }
}
