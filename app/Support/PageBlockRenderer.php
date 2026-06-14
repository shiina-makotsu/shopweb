<?php

namespace App\Support;

use App\Models\FriendLink;
use App\Models\MediaAsset;
use App\Models\NavigationMenuItem;
use App\Models\Page;
use App\Models\Product;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Schema;
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
            'hero' => self::hero($data),
            'cards' => self::cards($data),
            'menu' => self::menu($data),
            'friend_links' => self::friendLinks($data),
            'search' => self::search($data),
            'products' => self::products($data),
            'articles' => self::articles($data),
            'resources' => self::resources($data),
            'divider' => self::divider($data),
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

    private static function hero(array $data): string
    {
        $title = trim((string) ($data['title'] ?? ''));

        if ($title === '') {
            return '';
        }

        $subtitle = trim((string) ($data['subtitle'] ?? ''));
        $image = self::safeUrl($data['image_url'] ?? null);
        $buttonLabel = trim((string) ($data['button_label'] ?? ''));
        $buttonUrl = self::safeUrl($data['button_url'] ?? null);
        $style = $image ? ' style="background-image: linear-gradient(90deg, rgba(15, 23, 42, .82), rgba(15, 23, 42, .28)), url(\''.e($image).'\')"' : '';

        return '<section class="overflow-hidden rounded-sm border border-slate-200 bg-slate-950 bg-cover bg-center px-5 py-10 text-white"'.$style.'>'
            .'<h2 class="text-2xl font-semibold">'.e($title).'</h2>'
            .($subtitle !== '' ? '<div class="mt-3 max-w-2xl text-sm leading-7 text-white/80">'.Markdown::render($subtitle)->toHtml().'</div>' : '')
            .($buttonLabel !== '' && $buttonUrl ? '<a class="mt-5 inline-flex rounded-sm border border-white/80 bg-white px-4 py-2 text-sm font-medium text-slate-950 hover:bg-white/90" href="'.e($buttonUrl).'">'.e($buttonLabel).'</a>' : '')
            .'</section>';
    }

    private static function cards(array $data): string
    {
        $items = collect(preg_split('/\r?\n/', (string) ($data['items'] ?? '')) ?: [])
            ->map(fn (string $line): array => array_pad(array_map('trim', explode('|', $line, 3)), 3, ''))
            ->filter(fn (array $item): bool => $item[0] !== '')
            ->values();

        if ($items->isEmpty()) {
            return '';
        }

        return '<div class="grid gap-4 md:grid-cols-3">'.$items->map(function (array $item): string {
            $content = '<h3 class="font-semibold text-slate-950">'.e($item[0]).'</h3>'
                .($item[1] !== '' ? '<p class="mt-2 text-sm leading-6 text-slate-600">'.e($item[1]).'</p>' : '');
            $url = self::safeUrl($item[2]);

            return $url
                ? '<a class="block rounded-sm border border-slate-200 bg-white px-4 py-4 hover:border-blue-200 hover:bg-blue-50" href="'.e($url).'">'.$content.'</a>'
                : '<div class="rounded-sm border border-slate-200 bg-white px-4 py-4">'.$content.'</div>';
        })->implode('').'</div>';
    }

    private static function menu(array $data): string
    {
        $placement = (string) ($data['placement'] ?? NavigationMenuItem::PLACEMENT_TOP_NAV);
        $items = NavigationMenuItem::query()
            ->active()
            ->when(Schema::hasColumn('navigation_menu_items', 'placement'), fn ($query) => $query->placement($placement))
            ->whereNull('parent_id')
            ->with('children')
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get();

        if ($items->isEmpty()) {
            return '';
        }

        return '<nav class="grid gap-2 rounded-sm border border-slate-200 bg-white p-3 md:grid-cols-2">'
            .$items->map(fn (NavigationMenuItem $item): string => '<a class="rounded-sm border border-slate-100 px-3 py-2 text-sm font-medium text-slate-800 hover:bg-blue-50" href="'.e($item->resolvedUrl()).'"'.self::titleAttribute($item->tooltip_text).'>'.e($item->label).'</a>')->implode('')
            .'</nav>';
    }

    private static function friendLinks(array $data): string
    {
        $limit = self::limit($data['limit'] ?? 6);
        $links = FriendLink::query()->active()->orderBy('sort_order')->orderBy('site_name')->limit($limit)->get();

        if ($links->isEmpty()) {
            return '';
        }

        return '<div class="grid gap-4 md:grid-cols-3">'.$links->map(fn (FriendLink $link): string => '<a class="rounded-sm border border-slate-200 bg-white px-4 py-4 hover:border-blue-200 hover:bg-blue-50" href="'.e($link->url).'" target="_blank" rel="noopener"><h3 class="font-semibold text-slate-950">'.e($link->site_name).'</h3>'.($link->description ? '<p class="mt-2 text-sm text-slate-600">'.e($link->description).'</p>' : '').'</a>')->implode('').'</div>';
    }

    private static function search(array $data): string
    {
        $placeholder = trim((string) ($data['placeholder'] ?? '搜索商品、文章或用户')) ?: '搜索商品、文章或用户';

        return '<form class="flex gap-2 rounded-sm border border-slate-200 bg-white p-3" method="get" action="'.e(route('search.index')).'">'
            .'<input class="min-w-0 flex-1 rounded-sm border border-slate-300 px-3 py-2 text-sm" type="search" name="q" placeholder="'.e($placeholder).'">'
            .'<button class="rounded-sm border border-blue-700 bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800" type="submit">搜索</button>'
            .'</form>';
    }

    private static function products(array $data): string
    {
        $limit = self::limit($data['limit'] ?? 6);
        $title = trim((string) ($data['title'] ?? '推荐商品')) ?: '推荐商品';
        $products = Product::query()->publiclyVisible()->with(['coverMedia', 'variants'])->orderBy('sort_order')->latest()->limit($limit)->get();

        if ($products->isEmpty()) {
            return '';
        }

        return self::sectionGrid($title, $products->map(fn (Product $product): string => '<a class="rounded-sm border border-slate-200 bg-white px-4 py-4 hover:border-blue-200 hover:bg-blue-50" href="'.e(route('products.show', $product)).'"><h3 class="font-semibold text-slate-950">'.e($product->title).'</h3><p class="mt-2 text-sm text-slate-600">'.e($product->priceRangeLabel()).'</p></a>')->implode(''));
    }

    private static function articles(array $data): string
    {
        $limit = self::limit($data['limit'] ?? 6);
        $title = trim((string) ($data['title'] ?? '最新文章')) ?: '最新文章';
        $query = Page::query()->published()->where('template', PageTemplate::ARTICLE);

        if (($data['sort'] ?? null) === 'views') {
            $query->orderByDesc('views_count')->latest();
        } else {
            $query->latest();
        }

        $articles = $query->limit($limit)->get();

        if ($articles->isEmpty()) {
            return '';
        }

        return self::sectionGrid($title, $articles->map(fn (Page $page): string => '<a class="rounded-sm border border-slate-200 bg-white px-4 py-4 hover:border-blue-200 hover:bg-blue-50" href="'.e(route('pages.show', $page)).'"><h3 class="font-semibold text-slate-950">'.e($page->title).'</h3><p class="mt-2 text-xs text-slate-500">'.number_format((int) $page->views_count).' 次阅读</p></a>')->implode(''));
    }

    private static function resources(array $data): string
    {
        $limit = self::limit($data['limit'] ?? 6);
        $title = trim((string) ($data['title'] ?? '资源发布')) ?: '资源发布';
        $resources = MediaAsset::query()
            ->where('library', MediaAsset::LIBRARY_SITE)
            ->whereIn('usage', [MediaAsset::USAGE_RESOURCE, MediaAsset::USAGE_PRESENTATION])
            ->latest()
            ->limit($limit)
            ->get();

        if ($resources->isEmpty()) {
            return '';
        }

        return self::sectionGrid($title, $resources->map(fn (MediaAsset $asset): string => '<a class="rounded-sm border border-slate-200 bg-white px-4 py-4 hover:border-blue-200 hover:bg-blue-50" href="'.e($asset->url()).'"><h3 class="font-semibold text-slate-950">'.e($asset->name ?: basename($asset->path)).'</h3><p class="mt-2 text-xs text-slate-500">'.e($asset->mime_type ?: '资源').'</p></a>')->implode(''));
    }

    private static function divider(array $data): string
    {
        $label = trim((string) ($data['label'] ?? ''));

        if ($label === '') {
            return '<hr class="border-slate-200">';
        }

        return '<div class="flex items-center gap-3 text-xs font-medium text-slate-500"><span class="h-px flex-1 bg-slate-200"></span><span>'.e($label).'</span><span class="h-px flex-1 bg-slate-200"></span></div>';
    }

    private static function sectionGrid(string $title, string $items): string
    {
        return '<section class="rounded-sm border border-slate-200 bg-slate-50 p-4">'
            .'<div class="mb-3 flex items-center justify-between gap-3"><h2 class="font-semibold text-slate-950">'.e($title).'</h2></div>'
            .'<div class="grid gap-4 md:grid-cols-3">'.$items.'</div>'
            .'</section>';
    }

    private static function limit(mixed $value): int
    {
        return max(1, min(24, (int) $value));
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

    private static function titleAttribute(?string $text): string
    {
        $text = trim((string) $text);

        return $text === '' ? '' : ' title="'.e($text).'"';
    }
}
