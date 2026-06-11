<x-layouts.app :title="$page->seo_title ?: $page->title" :description="$page->seo_description ?: $page->excerpt">
    @include($templateView ?? 'pages.templates.default')
</x-layouts.app>
