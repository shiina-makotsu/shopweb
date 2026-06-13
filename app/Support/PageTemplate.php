<?php

namespace App\Support;

use App\Models\FriendLink;
use App\Models\MediaAsset;
use App\Models\NavigationMenuItem;
use App\Models\Page;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class PageTemplate
{
    public const DEFAULT = 'default';
    public const ARTICLE = 'article';
    public const NOT_FOUND = 'not_found';
    public const MENU = 'menu';
    public const FRIEND_LINKS = 'friend_links';
    public const SEARCH = 'search';
    public const RESOURCES = 'resources';
    public const ABOUT = 'about';

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
            self::ABOUT => '关于我们模板',
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
            self::ABOUT => 'about',
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
            self::ABOUT => self::aboutBody(),
            default => '',
        };
    }

    public static function defaultExcerpt(?string $template): ?string
    {
        return match (self::normalize($template)) {
            default => null,
        };
    }

    public static function defaultTitle(?string $template): ?string
    {
        return match (self::normalize($template)) {
            self::ABOUT => '关于我们',
            self::NOT_FOUND => '页面不存在',
            self::MENU => '导航菜单',
            self::FRIEND_LINKS => '友情链接',
            self::SEARCH => '综合搜索',
            self::RESOURCES => '资源发布',
            default => null,
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
                ->when(
                    Schema::hasColumn('navigation_menu_items', 'placement'),
                    fn ($query) => $query->placement(NavigationMenuItem::PLACEMENT_TOP_NAV),
                )
                ->whereNull('parent_id')
                ->with(['children' => fn ($query) => $query
                    ->active()
                    ->when(
                        Schema::hasColumn('navigation_menu_items', 'placement'),
                        fn ($query) => $query->placement(NavigationMenuItem::PLACEMENT_TOP_NAV),
                    )
                    ->orderBy('sort_order')
                    ->orderBy('label')])
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

    private static function aboutBody(): string
    {
        return <<<'MARKDOWN'
> 在这里放一句与你的网站理念相关的诗词、引文或简短标语。
>
> 可以在第二行补充一句你自己的解释。

## 我们是谁

在这里介绍你的网站定位、服务对象和主要功能。

可以说明网站如何处理交易、沟通、内容发布、用户关系或社区规则。

如果你的网站有特定价值观，也可以在这里写清楚，例如尊重、平等、透明、安全、互助等。

## 名称来源

在这里介绍网站名称、品牌名称或域名的来源。

- 名称中的第一个元素代表什么。
- 名称中的第二个元素代表什么。
- 这个名称整体想传达什么感觉或愿景。

## 联系方式

在这里填写你的联系方式。可以保留站内链接，也可以替换为邮箱、社交账号或其他渠道。

- 客服会话：[/support](/support)
- 客服工单：[/support/demands](/support/demands)
- 订单查询：[/orders](/orders)

如需商务合作、内容反馈、页面纠错或权益处理，请在这里说明处理方式。

## 转载许可

在这里填写本页面内容的转载许可。示例：

除另有说明外，本页面原创文字采用 [Creative Commons Attribution 4.0 International（CC BY 4.0）](https://creativecommons.org/licenses/by/4.0/deed.zh-hans) 许可协议发布。你可以转载、分享、改编本页面文字，但应保留来源说明、作者或网站名称，并附上许可协议链接；如有修改，也请说明修改情况。

## 网站声明

在这里填写网站声明、责任边界、用户内容说明、隐私与合规提示。

请根据你的网站实际运营地区、业务类型和平台规则自行调整。
MARKDOWN;
    }
}
