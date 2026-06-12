@php
    $selectedTemplate = old('template', $selectedThreadTemplate ?? \App\Support\ForumThreadTemplate::GENERAL);
    $templateTitles = $threadTemplateTitles ?? [];
    $templateBodies = $threadTemplateBodies ?? [];
    $path = fn (string $name, mixed $parameters = []): string => \App\Support\Url::route($name, $parameters);
@endphp

<x-layouts.app title="发布新帖">
    <section class="rounded-sm border border-slate-300 bg-white">
        <div class="border-b border-slate-200 bg-slate-100 px-4 py-2 text-sm text-slate-600">
            <a class="hover:text-blue-800" href="{{ $path('forum.index') }}">论坛</a>
            @if($section)
                <span class="mx-1">/</span>
                <a class="hover:text-blue-800" href="{{ $path('forum.sections.show', $section) }}">{{ $section->name }}</a>
            @endif
        </div>
        <div class="border-b border-slate-200 px-4 py-4">
            <h1 class="text-xl font-semibold">发布新帖</h1>
            <p class="mt-1 text-sm text-slate-600">{{ $section ? '发到 '.$section->name : '请选择帖子所属版块。' }}</p>
        </div>

        @if($sections->isEmpty())
            <div class="px-4 py-8 text-sm text-slate-600">当前没有你可以发帖的版块。</div>
        @else
            <form method="post" action="{{ $section ? $path('forum.threads.store', $section) : $path('forum.threads.store-global') }}" enctype="multipart/form-data" class="space-y-4 px-4 py-4 text-sm">
                @csrf
                @unless($section)
                    <label class="block">
                        <span class="text-xs font-medium text-slate-600">版块</span>
                        <select class="mt-1 w-full rounded-sm border border-slate-300 bg-white px-3 py-2" name="forum_section_id" required>
                            @foreach($sections as $candidate)
                                <option value="{{ $candidate->id }}" @selected(old('forum_section_id') == $candidate->id)>{{ $candidate->name }}</option>
                            @endforeach
                        </select>
                    </label>
                @endunless

                <label class="block">
                    <span class="text-xs font-medium text-slate-600">发帖模板</span>
                    <select class="mt-1 w-full rounded-sm border border-slate-300 bg-white px-3 py-2" name="template" data-thread-template>
                        @foreach($threadTemplates as $key => $label)
                            <option value="{{ $key }}" @selected($selectedTemplate === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <span class="mt-1 block text-xs text-slate-500">选择交友、相亲、合租、招租、找租或资源发布模板后，会自动生成可编辑的正文结构。</span>
                </label>

                <label class="block">
                    <span class="text-xs font-medium text-slate-600">标题</span>
                    <input class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2" name="title" maxlength="120" value="{{ old('title', $templateTitles[$selectedTemplate] ?? '') }}" data-thread-title required>
                </label>

                <label class="block">
                    <span class="text-xs font-medium text-slate-600">正文</span>
                    <textarea class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-3 leading-6" name="body" maxlength="12000" rows="24" data-thread-body data-auto-grow-textarea data-min-height="544" style="min-height: 34rem; overflow-y: hidden; resize: vertical;" required>{{ old('body', $templateBodies[$selectedTemplate] ?? '') }}</textarea>
                </label>

                <label class="block">
                    <span class="text-xs font-medium text-slate-600">图片/视频/文件附件</span>
                    <input class="mt-1 block w-full rounded-sm border border-slate-300 px-3 py-2 text-xs" type="file" name="attachments[]" multiple>
                    <span class="mt-1 block text-xs text-slate-500">支持图片、视频、PDF、压缩包和常见文档；合租/招租模板可上传房源照片、视频或位置截图。</span>
                </label>

                <div class="flex flex-wrap gap-2">
                    <button class="rounded-sm border border-blue-700 bg-blue-700 px-4 py-2 font-medium text-white hover:bg-blue-800" type="submit">发布</button>
                    <a class="rounded-sm border border-slate-300 px-4 py-2 font-medium hover:bg-slate-50" href="{{ $section ? $path('forum.sections.show', $section) : $path('forum.index') }}">返回</a>
                </div>
            </form>
        @endif
    </section>

    <script>
        (() => {
            const templateSelect = document.querySelector('[data-thread-template]');
            const titleInput = document.querySelector('[data-thread-title]');
            const bodyInput = document.querySelector('[data-thread-body]');
            const titles = @json($templateTitles);
            const bodies = @json($templateBodies);

            if (!templateSelect || !titleInput || !bodyInput) {
                return;
            }

            const knownTitles = Object.values(titles);
            const knownBodies = Object.values(bodies);
            const minBodyHeight = Number.parseInt(bodyInput.dataset.minHeight || '544', 10);
            const resizeBody = () => {
                const nextHeight = Math.max(bodyInput.scrollHeight + 2, minBodyHeight);

                if (nextHeight > bodyInput.offsetHeight) {
                    bodyInput.style.height = `${nextHeight}px`;
                }
            };

            bodyInput.addEventListener('input', resizeBody);
            window.addEventListener('resize', resizeBody);
            requestAnimationFrame(resizeBody);

            templateSelect.addEventListener('change', () => {
                const key = templateSelect.value;

                if (titleInput.value.trim() === '' || knownTitles.includes(titleInput.value)) {
                    titleInput.value = titles[key] || '';
                }

                if (bodyInput.value.trim() === '' || knownBodies.includes(bodyInput.value)) {
                    bodyInput.value = bodies[key] || '';
                    requestAnimationFrame(resizeBody);
                }
            });
        })();
    </script>
</x-layouts.app>
