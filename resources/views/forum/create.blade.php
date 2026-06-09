<x-layouts.app title="发布新帖">
    <section class="rounded-sm border border-slate-300 bg-white">
        <div class="border-b border-slate-200 bg-slate-100 px-4 py-2 text-sm text-slate-600">
            <a class="hover:text-blue-800" href="{{ route('forum.index') }}">论坛</a>
            @if($section)
                <span class="mx-1">/</span>
                <a class="hover:text-blue-800" href="{{ route('forum.sections.show', $section) }}">{{ $section->name }}</a>
            @endif
        </div>
        <div class="border-b border-slate-200 px-4 py-4">
            <h1 class="text-xl font-semibold">发布新帖</h1>
            <p class="mt-1 text-sm text-slate-600">{{ $section ? '发到 '.$section->name : '请选择帖子所属版块。' }}</p>
        </div>

        @if($sections->isEmpty())
            <div class="px-4 py-8 text-sm text-slate-600">当前没有你可以发帖的版块。</div>
        @else
            <form method="post" action="{{ $section ? route('forum.threads.store', $section) : route('forum.threads.store-global') }}" enctype="multipart/form-data" class="space-y-4 px-4 py-4 text-sm">
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
                    <span class="text-xs font-medium text-slate-600">标题</span>
                    <input class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2" name="title" maxlength="120" value="{{ old('title') }}" required>
                </label>
                <label class="block">
                    <span class="text-xs font-medium text-slate-600">正文</span>
                    <textarea class="mt-1 min-h-40 w-full rounded-sm border border-slate-300 px-3 py-2" name="body" maxlength="12000" required>{{ old('body') }}</textarea>
                </label>
                <label class="block">
                    <span class="text-xs font-medium text-slate-600">图片/文件附件</span>
                    <input class="mt-1 block w-full rounded-sm border border-slate-300 px-3 py-2 text-xs" type="file" name="attachments[]" multiple>
                </label>
                <div class="flex flex-wrap gap-2">
                    <button class="rounded-sm border border-blue-700 bg-blue-700 px-4 py-2 font-medium text-white hover:bg-blue-800" type="submit">发布</button>
                    <a class="rounded-sm border border-slate-300 px-4 py-2 font-medium hover:bg-slate-50" href="{{ $section ? route('forum.sections.show', $section) : route('forum.index') }}">返回</a>
                </div>
            </form>
        @endif
    </section>
</x-layouts.app>
