<?php

namespace App\Support;

use App\Models\FriendLink;
use App\Models\MediaAsset;
use App\Models\NavigationMenuItem;
use App\Models\Page;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;

class PageTemplate
{
    public const DEFAULT = 'default';
    public const ARTICLE = 'article';
    public const NOT_FOUND = 'not_found';
    public const MENU = 'menu';
    public const FRIEND_LINKS = 'friend_links';
    public const SEARCH = 'search';
    public const RESOURCES = 'resources';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::DEFAULT => '默认模板',
            self::ARTICLE => '文章模板',
            self::NOT_FOUND => '404 模板',
            self::MENU => '菜单模板',
            self::FRIEND_LINKS => '友情链接模板',
            self::SEARCH => '搜索模板',
            self::RESOURCES => '资源发布模板',
        ];
    }

    public static function normalize(?string $template): string
    {
        return array_key_exists((string) $template, self::options()) ? (string) $template : self::DEFAULT;
    }

    public static function label(?string $template): string
    {
        $template = self::normalize($template);

        return self::options()[$template];
    }

    public static function viewName(?string $template): string
    {
        $template = str_replace('_', '-', self::normalize($template));

        return "pages.templates.{$template}";
    }

    public static function defaultSlug(?string $template): ?string
    {
        return match (self::normalize($template)) {
            self::NOT_FOUND => '404',
            self::MENU => 'menu',
            self::FRIEND_LINKS => 'friend-links',
            self::SEARCH => 'search',
            self::RESOURCES => 'resources',
            default => null,
        };
    }

    public static function defaultBody(?string $template): string
    {
        return match (self::normalize($template)) {
            self::ARTICLE => "## 摘要\n\n在这里写文章导语。\n\n## 正文\n\n在这里写完整内容。",
            self::NOT_FOUND => "你访问的页面不存在，可能已经被移动、下架或输入了错误地址。\n\n可以从首页重新开始浏览。",
            self::MENU => "本页面会自动展示前台菜单和已发布自定义页面。这里可以补充导航说明。",
            self::FRIEND_LINKS => "本页面会自动展示后台维护的友情链接。这里可以写友链申请说明。",
            self::SEARCH => "本页面会展示综合搜索框和搜索结果。这里可以写搜索提示。",
            self::RESOURCES => "本页面会自动展示用途为“资源发布”或“PPT/展示资料”的公开资源。这里可以写资源说明。",
            default => '',
        };
    }

    /**
     * @return array<string, mixed>
     */
    public static function viewData(Page $page, Request $request): array
    {
        $template = self::normalize($page->template);

        return match ($template) {
            self::MENU => self::menuData($page),
            self::FRIEND_LINKS => self::friendLinkData($page),
            self::SEARCH => self::searchData($page, $request),
            self::RESOURCES => self::resourceData($page),
            default => [
                'page' => $page,
                'template' => $template,
            ],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private static function menuData(Page $page): array
    {
        return [
            'page' => $page,
            'template' => self::MENU,
            'menuItems' => NavigationMenuItem::query()
                ->active()
                ->whereNull('parent_id')
                ->with(['children' => fn ($query) => $query->active()->orderBy('sort_order')->orderBy('label')])
                ->orderBy('sort_order')
                ->orderBy('label')
                ->get(),
            'publishedPages' => Page::query()
                ->published()
                ->whereKeyNot($page->id)
                ->orderBy('sort_order')
                ->orderBy('title')
                ->get(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function friendLinkData(Page $page): array
    {
        return [
            'page' => $page,
            'template' => self::FRIEND_LINKS,
            'links' => FriendLink::query()
                ->active()
                ->orderBy('sort_order')
                ->orderBy('site_name')
                ->paginate(36, ['*'], 'links_page')
                ->withQueryString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function searchData(Page $page, Request $request): array
    {
        $keyword = trim($request->string('q')->toString());

        return [
            'page' => $page,
            'template' => self::SEARCH,
            'keyword' => $keyword,
            'products' => Product::query()
                ->publiclyVisible()
                ->with(['category', 'coverMedia', 'variants'])
                ->when($keyword !== '', fn ($query) => RegexSearch::where($query, ['title', 'summary', 'description'], $keyword))
                ->latest()
                ->paginate(8, ['*'], 'products_page')
                ->withQueryString(),
            'users' => User::query()
                ->where('role', 'customer')
                ->when($keyword !== '', fn ($query) => RegexSearch::where($query, ['name', 'public_id'], $keyword))
                ->latest()
                ->paginate(10, ['*'], 'users_page')
                ->withQueryString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function resourceData(Page $page): array
    {
        return [
            'page' => $page,
            'template' => self::RESOURCES,
            'resources' => MediaAsset::query()
                ->where('library', MediaAsset::LIBRARY_SITE)
                ->whereIn('usage', [MediaAsset::USAGE_RESOURCE, MediaAsset::USAGE_PRESENTATION])
                ->latest()
                ->paginate(24, ['*'], 'resources_page')
                ->withQueryString(),
        ];
    }
}
