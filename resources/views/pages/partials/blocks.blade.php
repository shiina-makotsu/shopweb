@if(! blank($page->blocks))
    <div class="{{ $class ?? 'border-t border-slate-200 px-4 py-4' }}">
        {{ \App\Support\PageBlockRenderer::render($page->blocks) }}
    </div>
@endif
