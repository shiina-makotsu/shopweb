<x-layouts.app title="AI" :wide="true">
    <section
        class="relative -mx-4 -my-4 min-h-[calc(100vh-160px)] overflow-hidden bg-[#f7f7f8] text-zinc-900"
        data-ai-image-workbench
        data-models-url="{{ \App\Support\Url::route('ai-image.models') }}"
        data-configs-url="{{ \App\Support\Url::route('ai-image.configs') }}"
        data-config-save-url="{{ \App\Support\Url::route('ai-image.configs.store') }}"
        data-state-url="{{ \App\Support\Url::route('ai-image.state') }}"
        data-task-save-url="{{ \App\Support\Url::route('ai-image.tasks.store') }}"
        data-task-delete-url-template="{{ \App\Support\Url::route('ai-image.tasks.destroy', ['task' => '__TASK__']) }}"
        data-task-restore-url-template="{{ \App\Support\Url::route('ai-image.tasks.restore', ['task' => '__TASK__']) }}"
        data-chat-save-url="{{ \App\Support\Url::route('ai-image.chats.store') }}"
        data-chat-delete-url-template="{{ \App\Support\Url::route('ai-image.chats.destroy', ['session' => '__SESSION__']) }}"
        data-chat-restore-url-template="{{ \App\Support\Url::route('ai-image.chats.restore', ['session' => '__SESSION__']) }}"
        data-reference-upload-url="{{ \App\Support\Url::route('ai-image.references.store') }}"
        data-generate-url="{{ \App\Support\Url::route('ai-image.generate') }}"
        data-stream-url="{{ \App\Support\Url::route('ai-image.stream') }}"
        data-chat-url="{{ \App\Support\Url::route('ai-image.chat') }}"
    >
        <form class="contents" data-ai-image-form>
            @csrf

            <header class="sticky top-0 z-30 border-b border-zinc-200 bg-white/95 px-5 py-3 backdrop-blur">
                <div class="flex items-center justify-between gap-4">
                    <a class="min-w-0 truncate text-xl font-semibold tracking-normal text-zinc-950" href="{{ \App\Support\Url::route('home') }}">{{ $settings?->site_name ?? $siteSettings?->site_name ?? config('app.name', 'ShopWeb') }}</a>
                    <div class="flex items-center gap-2">
                        <button class="rounded-full border border-zinc-200 bg-zinc-100 px-5 py-2 text-sm font-semibold text-zinc-950 shadow-inner" type="button" data-mode-button="gallery">画廊</button>
                        <button class="inline-flex rounded-full px-5 py-2 text-sm text-zinc-500" type="button" data-mode-button="chat">Chat</button>
                        <button class="inline-flex h-10 w-10 items-center justify-center rounded-full text-zinc-600 hover:bg-zinc-100" type="button" data-download-latest title="下载最近图片" aria-label="下载最近图片">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true" stroke="currentColor" stroke-width="2"><path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M5 21h14"/></svg>
                        </button>
                        <button class="inline-flex h-10 w-10 items-center justify-center rounded-full text-zinc-600 hover:bg-zinc-100" type="button" data-help-toggle title="鎻愮ず" aria-label="鎻愮ず">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.2 9a3 3 0 1 1 4.6 2.5c-.9.5-1.8 1.2-1.8 2.5"/><path d="M12 18h.01"/></svg>
                        </button>
                        <button class="inline-flex h-10 w-10 items-center justify-center rounded-full text-zinc-600 hover:bg-zinc-100" type="button" data-settings-toggle title="璁剧疆" aria-label="璁剧疆">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true" stroke="currentColor" stroke-width="2"><path d="M12 15.5A3.5 3.5 0 1 0 12 8a3.5 3.5 0 0 0 0 7.5Z"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1-1.5 1.7 1.7 0 0 0-1.9.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1 1.7 1.7 0 0 0-.3-1.9l-.1-.1A2 2 0 1 1 7 4.2l.1.1A1.7 1.7 0 0 0 9 4.6a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.9-.3l.1-.1A2 2 0 1 1 19.8 7l-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.5 1h.1a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1Z"/></svg>
                        </button>
                    </div>
                </div>
            </header>

            <div class="px-5 pb-44 pt-5" data-gallery-view>
                <div class="mb-4 grid gap-3 lg:grid-cols-[3rem_9rem_1fr]">
                    <button class="inline-flex h-12 w-12 items-center justify-center rounded-lg border border-zinc-200 bg-white text-zinc-500 shadow-sm hover:bg-zinc-50" type="button" data-star-filter aria-label="收藏筛选">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true" stroke="currentColor" stroke-width="2"><path d="m12 3 2.7 5.5 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.8 1-6.1-4.4-4.3 6.1-.9L12 3Z"/></svg>
                    </button>

                    <label class="sr-only" for="ai-status-filter">状态</label>
                    <select id="ai-status-filter" class="h-12 rounded-lg border border-zinc-200 bg-white px-4 text-sm font-medium text-zinc-800 shadow-sm" data-status-filter>
                        <option value="all">全部状态</option>
                        <option value="done">已完成</option>
                        <option value="running">生成中</option>
                        <option value="failed">失败</option>
                        <option value="trash">回收站</option>
                    </select>

                    <label class="relative block">
                        <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-zinc-400">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                        </span>
                        <input class="h-12 w-full rounded-lg border border-zinc-200 bg-white pl-12 pr-4 text-sm text-zinc-900 shadow-sm outline-none placeholder:text-zinc-400 focus:border-zinc-300" type="search" placeholder="搜索提示词、参数..." data-gallery-search>
                    </label>
                </div>

                <div class="grid gap-3 lg:grid-cols-3" data-ai-results>
                    <div class="col-span-full rounded-2xl border border-dashed border-zinc-300 bg-white px-6 py-16 text-center text-sm text-zinc-500">
                        从底部输入提示词开始生成，完成后的图片会出现在这里。
                    </div>
                </div>
            </div>

            <div class="hidden px-5 pb-44 pt-5" data-chat-view>
                <div class="mx-auto grid min-h-[calc(100vh-260px)] max-w-6xl gap-4 lg:grid-cols-[16rem_1fr]">
                    <aside class="rounded-3xl border border-zinc-200 bg-white p-3 shadow-sm lg:sticky lg:top-20 lg:self-start">
                        <div class="flex items-center justify-between gap-2 px-2 py-1">
                            <h2 class="text-sm font-semibold text-zinc-950">浼氳瘽</h2>
                            <button class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-zinc-950 text-white hover:bg-zinc-800" type="button" data-chat-new title="新增会话" aria-label="新增会话">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true" stroke="currentColor" stroke-width="2"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
                            </button>
                        </div>
                        <button class="mt-2 w-full rounded-2xl border border-zinc-200 px-3 py-2 text-xs font-medium text-zinc-600 hover:bg-zinc-50" type="button" data-chat-trash-toggle>回收站</button>
                        <div class="mt-3 space-y-2" data-chat-sessions></div>
                    </aside>

                    <section class="flex min-h-[calc(100vh-260px)] flex-col rounded-3xl border border-zinc-200 bg-white shadow-sm">
                        <div class="flex items-center justify-between gap-3 border-b border-zinc-100 px-5 py-4">
                            <div class="min-w-0">
                                <h2 class="truncate text-base font-semibold text-zinc-950" data-chat-title>新会话</h2>
                                <p class="mt-1 text-xs text-zinc-500" data-chat-meta>0 条消息</p>
                            </div>
                            <button class="inline-flex items-center gap-2 rounded-full border border-zinc-200 px-3 py-2 text-xs font-medium text-zinc-600 hover:bg-zinc-50" type="button" data-chat-delete>
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v5"/><path d="M14 11v5"/></svg>
                                <span data-chat-delete-label>删除会话</span>
                            </button>
                        </div>

                        <div class="flex flex-1 flex-col gap-3 overflow-y-auto px-5 py-5" data-chat-messages>
                            <div class="rounded-2xl border border-dashed border-zinc-300 bg-white px-6 py-16 text-center text-sm text-zinc-500">
                                暂无消息。
                            </div>
                        </div>
                    </section>
                </div>
            </div>

            <div class="pointer-events-none fixed inset-x-0 bottom-4 z-40 px-4">
                <div class="pointer-events-auto mx-auto max-w-6xl rounded-[28px] border border-zinc-200 bg-white/95 p-3 shadow-2xl shadow-zinc-950/10 backdrop-blur">
                    <div class="mb-2 hidden max-h-16 items-center gap-2 overflow-x-auto pb-1 flex" data-reference-preview></div>
                    <div class="mb-2 hidden max-h-16 items-center gap-2 overflow-x-auto pb-1" data-chat-files-preview></div>

                    <div class="relative">
                        <textarea
                            class="max-h-32 min-h-[42px] w-full resize-none rounded-2xl border border-zinc-200 bg-white px-4 py-2.5 pr-10 text-sm leading-5 text-zinc-900 outline-none placeholder:text-zinc-400 focus:border-zinc-300"
                            name="prompt"
                            placeholder="描述你想生成的图片..."
                            data-ai-prompt
                            required
                        ></textarea>
                        <button class="absolute right-2 top-2 hidden h-7 w-7 items-center justify-center rounded-full bg-zinc-100 text-zinc-500 hover:bg-zinc-200 hover:text-zinc-800" type="button" data-clear-prompt aria-label="清空提示词">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                        </button>
                    </div>

                    <div class="mt-2 flex flex-wrap items-end gap-2">
                        <input class="hidden" type="file" name="reference_images[]" accept="image/png,image/jpeg,image/gif,image/webp" multiple data-reference-input>
                        <input class="hidden" type="file" name="chat_files" multiple data-chat-files-input>

                        <div class="flex flex-wrap items-end gap-2" data-image-controls>
                            <button class="inline-flex h-10 w-10 items-center justify-center rounded-2xl bg-zinc-100 text-zinc-600 hover:bg-zinc-200" type="button" data-reference-button title="娣诲姞鍙傝€冨浘" aria-label="娣诲姞鍙傝€冨浘">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true" stroke="currentColor" stroke-width="2"><path d="M21.4 11.6 12 21a6 6 0 0 1-8.5-8.5l10-10a4 4 0 1 1 5.7 5.7l-10 10a2 2 0 0 1-2.8-2.8l9.3-9.3"/></svg>
                            </button>

                            <label class="min-w-28 flex-1 text-xs font-medium text-zinc-400 sm:flex-none">
                                尺寸
                                <select class="mt-1 h-9 w-full rounded-2xl border border-zinc-200 bg-white px-3 text-sm font-medium text-zinc-900" name="size_mode" data-size-mode>
                                    <option value="auto">auto</option>
                                    <option value="ratio">按比例</option>
                                    <option value="custom">自定义宽高</option>
                                </select>
                            </label>

                            <label class="hidden min-w-28 flex-1 text-xs font-medium text-zinc-400 sm:flex-none" data-ratio-panel>
                                比例
                                <select class="mt-1 h-9 w-full rounded-2xl border border-zinc-200 bg-white px-3 text-sm font-medium text-zinc-900" name="ratio">
                                    <option value="1:1">1:1</option>
                                    <option value="4:3">4:3</option>
                                    <option value="3:4">3:4</option>
                                    <option value="16:9">16:9</option>
                                    <option value="9:16">9:16</option>
                                    <option value="21:9">21:9</option>
                                </select>
                            </label>

                            <div class="hidden grid min-w-44 grid-cols-2 gap-2" data-custom-size-panel>
                                <label class="text-xs font-medium text-zinc-400">
                                    宽
                                    <input class="mt-1 h-9 w-full rounded-2xl border border-zinc-200 bg-white px-3 text-sm font-medium text-zinc-900" name="width" type="number" min="256" max="4096" step="64" value="1024">
                                </label>
                                <label class="text-xs font-medium text-zinc-400">
                                    高
                                    <input class="mt-1 h-9 w-full rounded-2xl border border-zinc-200 bg-white px-3 text-sm font-medium text-zinc-900" name="height" type="number" min="256" max="4096" step="64" value="1024">
                                </label>
                            </div>

                            <label class="min-w-28 flex-1 text-xs font-medium text-zinc-400 sm:flex-none">
                                质量
                                <select class="mt-1 h-9 w-full rounded-2xl border border-zinc-200 bg-white px-3 text-sm font-medium text-zinc-900" name="quality">
                                    <option value="auto">auto</option>
                                    <option value="low">low</option>
                                    <option value="medium">medium</option>
                                    <option value="high">high</option>
                                </select>
                            </label>

                            <label class="min-w-28 flex-1 text-xs font-medium text-zinc-400 sm:flex-none">
                                格式
                                <select class="mt-1 h-9 w-full rounded-2xl border border-zinc-200 bg-white px-3 text-sm font-medium text-zinc-900" name="output_format">
                                    <option value="png">PNG</option>
                                    <option value="jpeg">JPEG</option>
                                    <option value="webp">WebP</option>
                                    <option value="auto">自动</option>
                                </select>
                            </label>

                            <label class="min-w-28 flex-1 text-xs font-medium text-zinc-400 sm:flex-none">
                                是否透明
                                <select class="mt-1 h-9 w-full rounded-2xl border border-zinc-200 bg-white px-3 text-sm font-medium text-zinc-900" name="transparent">
                                    <option value="0">否</option>
                                    <option value="1">是</option>
                                </select>
                            </label>

                            <label class="min-w-28 flex-1 text-xs font-medium text-zinc-400 sm:flex-none">
                                数量
                                <input class="mt-1 h-9 w-full rounded-2xl border border-zinc-200 bg-white px-3 text-sm font-medium text-zinc-900" name="count" type="number" min="1" max="8" value="1">
                            </label>
                        </div>

                        <div class="hidden flex-1 flex-wrap items-end gap-2" data-chat-controls>
                            <button class="inline-flex h-10 w-10 items-center justify-center rounded-2xl bg-zinc-100 text-zinc-600 hover:bg-zinc-200" type="button" data-chat-files-button title="闄勫姞鏂囦欢" aria-label="闄勫姞鏂囦欢">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true" stroke="currentColor" stroke-width="2"><path d="M21.4 11.6 12 21a6 6 0 0 1-8.5-8.5l10-10a4 4 0 1 1 5.7 5.7l-10 10a2 2 0 0 1-2.8-2.8l9.3-9.3"/></svg>
                            </button>

                            <label class="min-w-32 flex-1 text-xs font-medium text-zinc-400 sm:flex-none">
                                推理模式
                                <select class="mt-1 h-9 w-full rounded-2xl border border-zinc-200 bg-white px-3 text-sm font-medium text-zinc-900" name="reasoning_mode" data-chat-reasoning>
                                    <option value="low">低</option>
                                    <option value="medium">中</option>
                                    <option value="high">高</option>
                                    <option value="ultra">超高</option>
                                </select>
                            </label>

                            <button class="inline-flex h-10 w-10 items-center justify-center rounded-2xl bg-zinc-100 text-zinc-400 transition hover:bg-zinc-200" type="button" data-chat-web-search aria-label="鑱旂綉鎼滅储" title="鑱旂綉鎼滅储" aria-pressed="false">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"/>
                                    <path d="M2 12h20"/>
                                    <path d="M12 2a15.3 15.3 0 0 1 0 20"/>
                                    <path d="M12 2a15.3 15.3 0 0 0 0 20"/>
                                </svg>
                            </button>

                            <label class="ml-auto min-w-48 flex-1 text-xs font-medium text-zinc-400 sm:max-w-64 sm:flex-none">
                                妯″瀷
                                <select class="mt-1 h-9 w-full rounded-2xl border border-zinc-200 bg-white px-3 text-sm font-medium text-zinc-900" name="chat_model" data-chat-model-select>
                                    <option value="">榛樿妯″瀷 gpt-5.5</option>
                                </select>
                            </label>
                        </div>

                        <button class="ml-auto inline-flex h-10 w-10 items-center justify-center rounded-2xl bg-blue-700 text-white hover:bg-blue-800 disabled:cursor-not-allowed disabled:bg-zinc-200" type="button" data-generate-button title="生成" aria-label="生成">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true" stroke="currentColor" stroke-width="2"><path d="M5 12h14"/><path d="m13 6 6 6-6 6"/></svg>
                        </button>
                    </div>

                    <p class="mt-2 text-xs text-zinc-500" data-ai-status>等待填写提示词。</p>
                </div>
            </div>

            <aside class="fixed inset-y-0 right-0 z-50 w-full max-w-md translate-x-full border-l border-zinc-200 bg-white shadow-2xl transition-transform duration-200" data-settings-panel>
                <div class="flex items-center justify-between border-b border-zinc-200 px-5 py-4">
                    <h2 class="text-base font-semibold">生成设置</h2>
                    <button class="inline-flex h-9 w-9 items-center justify-center rounded-full text-zinc-500 hover:bg-zinc-100" type="button" data-settings-close aria-label="鍏抽棴璁剧疆">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                    </button>
                </div>

                <div class="h-[calc(100vh-73px)] space-y-5 overflow-y-auto px-5 py-5">
                    <section class="space-y-3">
                        <div class="flex items-center justify-between gap-3">
                            <h3 class="text-sm font-semibold text-zinc-950">接口与模型</h3>
                            <button class="rounded-full border border-zinc-200 px-3 py-1.5 text-xs font-medium text-zinc-700 hover:bg-zinc-50" type="button" data-fetch-models>获取模型</button>
                        </div>

                        <label class="block text-sm">
                            <span class="font-medium text-zinc-700">当前配置</span>
                            <select class="mt-1 w-full rounded-lg border border-zinc-200 px-4 py-3 text-sm outline-none focus:border-zinc-400" name="config_mode" data-config-mode>
                                <option value="default">默认配置</option>
                                <option value="custom">自定义配置</option>
                            </select>
                        </label>

                        <label class="block text-sm" data-saved-config-field>
                            <span class="font-medium text-zinc-700">Saved config</span>
                            <select class="mt-1 w-full rounded-lg border border-zinc-200 px-4 py-3 text-sm outline-none focus:border-zinc-400" name="config_id" data-ai-config-select>
                                <option value="">No saved config</option>
                            </select>
                        </label>

                        <label class="block text-sm">
                            <span class="font-medium text-zinc-700">配置名称</span>
                            <input class="mt-1 w-full rounded-lg border border-zinc-200 px-4 py-3 text-sm outline-none focus:border-zinc-400" name="config_name" type="text" value="默认配置" data-config-name>
                        </label>

                        <label class="block text-sm" data-endpoint-field>
                            <span class="font-medium text-zinc-700">API URL</span>
                            <input class="mt-1 w-full rounded-lg border border-zinc-200 px-4 py-3 text-sm outline-none focus:border-zinc-400" name="endpoint" type="url" placeholder="https://api.openai.com/v1" data-ai-endpoint>
                        </label>

                        <label class="block text-sm" data-key-field>
                            <span class="font-medium text-zinc-700">API Key</span>
                            <input class="mt-1 w-full rounded-lg border border-zinc-200 px-4 py-3 text-sm outline-none focus:border-zinc-400" name="api_key" type="password" autocomplete="off" placeholder="sk-..." data-ai-key>
                        </label>

                        <label class="block text-sm" data-chat-endpoint-field>
                            <span class="font-medium text-zinc-700">Chat / Responses API URL</span>
                            <input class="mt-1 w-full rounded-lg border border-zinc-200 px-4 py-3 text-sm outline-none focus:border-zinc-400" name="chat_endpoint" type="url" placeholder="https://api.openai.com/v1" data-ai-chat-endpoint>
                        </label>

                        <label class="block text-sm" data-chat-key-field>
                            <span class="font-medium text-zinc-700">Chat / Responses API Key</span>
                            <input class="mt-1 w-full rounded-lg border border-zinc-200 px-4 py-3 text-sm outline-none focus:border-zinc-400" name="chat_api_key" type="password" autocomplete="off" placeholder="sk-..." data-ai-chat-key>
                        </label>

                        <div class="flex flex-wrap gap-2" data-custom-config-actions>
                            <button class="rounded-full border border-zinc-200 px-3 py-2 text-xs font-medium text-zinc-700 hover:bg-zinc-50" type="button" data-save-ai-config>Save config</button>
                            <button class="rounded-full border border-red-200 px-3 py-2 text-xs font-medium text-red-600 hover:bg-red-50" type="button" data-delete-ai-config>Delete config</button>
                        </div>

                        <label class="block text-sm">
                            <span class="font-medium text-zinc-700">图片模型</span>
                            <select class="mt-1 w-full rounded-lg border border-zinc-200 px-4 py-3 text-sm outline-none focus:border-zinc-400" name="model" data-ai-model-select>
                                <option value="">默认模型 gpt-image-2</option>
                            </select>
                        </label>

                        <label class="block text-sm">
                            <span class="font-medium text-zinc-700">手动模型</span>
                            <input class="mt-1 w-full rounded-lg border border-zinc-200 px-4 py-3 text-sm outline-none focus:border-zinc-400" name="manual_model" type="text" placeholder="例如 gpt-image-2" data-ai-manual-model>
                        </label>
                    </section>

                    <section class="space-y-3 border-t border-zinc-200 pt-5">
                        <h3 class="text-sm font-semibold text-zinc-950">高级参数</h3>

                        <label class="block text-sm">
                            <span class="font-medium text-zinc-700">反向词</span>
                            <textarea class="mt-1 min-h-24 w-full resize-y rounded-lg border border-zinc-200 px-4 py-3 text-sm leading-6 outline-none focus:border-zinc-400" name="negative_prompt" placeholder="不希望出现的元素，例如低清晰度、畸形文字、额外手指"></textarea>
                        </label>

                        <div class="grid grid-cols-2 gap-3">
                            <label class="block text-sm">
                                <span class="font-medium text-zinc-700">风格</span>
                                <select class="mt-1 w-full rounded-lg border border-zinc-200 px-4 py-3 text-sm" name="style">
                                    <option value="auto">auto</option>
                                    <option value="vivid">vivid</option>
                                    <option value="natural">natural</option>
                                </select>
                            </label>

                            <label class="block text-sm">
                                <span class="font-medium text-zinc-700">返回格式</span>
                                <select class="mt-1 w-full rounded-lg border border-zinc-200 px-4 py-3 text-sm" name="response_format">
                                    <option value="auto">auto</option>
                                    <option value="url">URL</option>
                                    <option value="b64_json">Base64</option>
                                </select>
                            </label>

                            <label class="block text-sm">
                                <span class="font-medium text-zinc-700">生图接口</span>
                                <select class="mt-1 w-full rounded-lg border border-zinc-200 px-4 py-3 text-sm" name="image_api_mode">
                                    <option value="auto">auto</option>
                                    <option value="images">Images API</option>
                                    <option value="images_root">Images API (/images)</option>
                                    <option value="responses">Responses API</option>
                                </select>
                            </label>

                            <label class="block text-sm">
                                <span class="font-medium text-zinc-700">Seed</span>
                                <input class="mt-1 w-full rounded-lg border border-zinc-200 px-4 py-3 text-sm" name="seed" type="number" min="0" max="4294967295" placeholder="可选">
                            </label>

                            <label class="block text-sm">
                                <span class="font-medium text-zinc-700">步数</span>
                                <input class="mt-1 w-full rounded-lg border border-zinc-200 px-4 py-3 text-sm" name="steps" type="number" min="1" max="150" placeholder="可选">
                            </label>
                        </div>

                        <label class="block text-sm">
                            <span class="font-medium text-zinc-700">引导强度</span>
                            <input class="mt-1 w-full rounded-lg border border-zinc-200 px-4 py-3 text-sm" name="guidance_scale" type="number" min="0" max="30" step="0.1" placeholder="可选">
                        </label>
                    </section>

                    <section class="space-y-3 border-t border-zinc-200 pt-5">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h3 class="text-sm font-semibold text-zinc-950">流式传输</h3>
                                <p class="mt-1 text-xs leading-5 text-zinc-500">开启后请求以流式传输，并非所有服务商和网关都支持。数量大于 1 时会拆分为并发单图。</p>
                            </div>
                            <label class="relative inline-flex cursor-pointer items-center">
                                <input class="peer sr-only" type="checkbox" name="stream" value="1" data-stream-toggle>
                                <span class="h-6 w-11 rounded-full bg-zinc-200 after:absolute after:left-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow after:transition peer-checked:bg-zinc-900 peer-checked:after:translate-x-5"></span>
                            </label>
                        </div>

                        <label class="block text-sm">
                            <span class="font-medium text-zinc-700">请求中间步骤图像数</span>
                            <input class="mt-1 w-full rounded-lg border border-zinc-200 px-4 py-3 text-sm" name="partial_images" type="number" min="0" max="3" value="2">
                            <span class="mt-1 block text-xs leading-5 text-zinc-500">对应 partial_images 参数 0-3，建议设为 2 或 3 以减少长时间无数据导致的断开风险。</span>
                        </label>

                        <label class="block text-sm">
                            <span class="font-medium text-zinc-700">请求超时秒数</span>
                            <input class="mt-1 w-full rounded-lg border border-zinc-200 px-4 py-3 text-sm" name="timeout_seconds" type="number" min="30" max="1200" value="600">
                        </label>
                    </section>
                </div>
            </aside>

            <div class="fixed inset-0 z-40 hidden bg-zinc-950/25 backdrop-blur-sm" data-settings-backdrop></div>
        </form>

        <section class="fixed inset-0 z-50 hidden bg-zinc-950/30 px-4 py-6 backdrop-blur-sm" data-reference-editor>
            <div class="mx-auto flex h-full max-w-4xl items-center justify-center">
                <div class="grid max-h-full w-full overflow-hidden rounded-lg bg-white shadow-2xl md:grid-cols-[minmax(0,1fr)_20rem]">
                    <div class="flex min-h-72 items-center justify-center bg-zinc-950 p-4">
                        <img class="max-h-[70vh] max-w-full rounded-lg object-contain" src="" alt="" data-reference-editor-image>
                    </div>
                    <div class="min-h-0 overflow-y-auto p-5">
                        <div class="flex items-center justify-between gap-3">
                            <h2 class="text-base font-semibold text-zinc-950">参考图编辑</h2>
                            <button class="inline-flex h-9 w-9 items-center justify-center rounded-full text-zinc-500 hover:bg-zinc-100" type="button" data-reference-editor-close aria-label="关闭参考图编辑">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                            </button>
                        </div>

                        <div class="mt-5 space-y-4">
                            <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-3">
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-medium text-zinc-900">遮罩图</p>
                                        <p class="mt-1 text-xs leading-5 text-zinc-500">上传黑白或透明遮罩，用于限制需要修改的区域。</p>
                                    </div>
                                    <button class="shrink-0 rounded-lg bg-zinc-900 px-3 py-2 text-xs font-medium text-white hover:bg-zinc-800" type="button" data-reference-mask-button>添加遮罩</button>
                                </div>
                                <input class="hidden" type="file" accept="image/png,image/jpeg,image/webp" data-reference-mask-input>
                                <div class="mt-3 flex items-center justify-between gap-3 text-xs text-zinc-500">
                                    <span class="min-w-0 truncate" data-reference-mask-name>未选择遮罩图</span>
                                    <button class="hidden rounded-md px-2 py-1 text-red-600 hover:bg-red-50" type="button" data-reference-mask-clear>移除</button>
                                </div>
                            </div>

                            <label class="block text-sm">
                                <span class="font-medium text-zinc-700">修改要求</span>
                                <textarea class="mt-2 min-h-32 w-full resize-y rounded-lg border border-zinc-200 px-4 py-3 text-sm leading-6 outline-none focus:border-zinc-400" placeholder="例如：只修改衣服颜色，保持人物姿势和背景不变。" data-reference-edit-note></textarea>
                            </label>

                            <div class="flex justify-end gap-2 pt-2">
                                <button class="rounded-lg border border-zinc-200 px-4 py-2 text-sm font-medium text-zinc-700 hover:bg-zinc-50" type="button" data-reference-editor-close>取消</button>
                                <button class="rounded-lg bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-800" type="button" data-reference-editor-save>保存</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="fixed inset-0 z-50 hidden bg-white" data-detail-panel>
            <div class="grid h-full lg:grid-cols-[minmax(0,1.15fr)_minmax(360px,0.85fr)]">
                <div class="relative flex min-h-0 items-center justify-center bg-zinc-950 p-4">
                    <span class="absolute left-4 top-4 rounded-lg bg-zinc-950/70 px-2 py-1 text-xs font-semibold text-white" data-detail-time></span>
                    <img class="max-h-full max-w-full rounded-lg object-contain" src="" alt="" data-detail-image>
                    <div class="hidden max-w-xl rounded-2xl border border-red-400/30 bg-red-500/10 px-5 py-4 text-sm leading-6 text-red-100" data-detail-error></div>
                </div>
                <div class="min-h-0 overflow-y-auto border-l border-zinc-200 bg-white">
                    <div class="sticky top-0 z-10 flex items-center justify-between border-b border-zinc-200 bg-white px-5 py-4">
                        <h2 class="text-base font-semibold">图像任务详情</h2>
                        <div class="flex items-center gap-2">
                            <a class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-zinc-900 text-white hover:bg-zinc-800" href="#" download data-detail-download title="下载" aria-label="下载图片">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true" stroke="currentColor" stroke-width="2"><path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M5 21h14"/></svg>
                            </a>
                            <button class="inline-flex h-9 w-9 items-center justify-center rounded-full text-zinc-500 hover:bg-zinc-100" type="button" data-detail-close aria-label="关闭详情">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                            </button>
                        </div>
                    </div>

                    <div class="space-y-6 px-5 py-5">
                        <section>
                            <h3 class="text-sm font-semibold text-zinc-950">生成提示词</h3>
                            <p class="mt-2 whitespace-pre-wrap rounded-lg bg-zinc-50 px-4 py-3 text-sm leading-6 text-zinc-700" data-detail-prompt></p>
                        </section>

                        <section data-detail-references-section>
                            <h3 class="text-sm font-semibold text-zinc-950">参考图</h3>
                            <div class="mt-2 grid grid-cols-3 gap-2" data-detail-references></div>
                        </section>

                        <section>
                            <h3 class="text-sm font-semibold text-zinc-950">参数配置</h3>
                            <dl class="mt-2 divide-y divide-zinc-100 rounded-lg border border-zinc-200 text-sm" data-detail-meta></dl>
                        </section>
                    </div>
                </div>
            </div>
        </section>

        <div class="fixed bottom-36 left-1/2 z-50 hidden w-[min(92vw,420px)] -translate-x-1/2 rounded-lg border border-zinc-200 bg-white px-4 py-3 text-sm text-zinc-700 shadow-xl" data-help-panel>
            接口设置在右上角齿轮里。开启流式后，生成中会显示中间预览；失败时仍可点击任务查看已返回的中间图。
        </div>

        <script>
            (() => {
                const root = document.querySelector('[data-ai-image-workbench]');
                if (!root) return;

                const form = root.querySelector('[data-ai-image-form]');
                const status = root.querySelector('[data-ai-status]');
                const endpointInput = root.querySelector('[data-ai-endpoint]');
                const apiKeyInput = root.querySelector('[data-ai-key]');
                const chatEndpointInput = root.querySelector('[data-ai-chat-endpoint]');
                const chatApiKeyInput = root.querySelector('[data-ai-chat-key]');
                const endpointField = root.querySelector('[data-endpoint-field]');
                const keyField = root.querySelector('[data-key-field]');
                const chatEndpointField = root.querySelector('[data-chat-endpoint-field]');
                const chatKeyField = root.querySelector('[data-chat-key-field]');
                const savedConfigField = root.querySelector('[data-saved-config-field]');
                const savedConfigSelect = root.querySelector('[data-ai-config-select]');
                const customConfigActions = root.querySelector('[data-custom-config-actions]');
                const configMode = root.querySelector('[data-config-mode]');
                const configName = root.querySelector('[data-config-name]');
                const modelSelect = root.querySelector('[data-ai-model-select]');
                const manualModel = root.querySelector('[data-ai-manual-model]');
                const promptInput = root.querySelector('[data-ai-prompt]');
                const clearPromptButton = root.querySelector('[data-clear-prompt]');
                const referenceInput = root.querySelector('[data-reference-input]');
                const referencePreview = root.querySelector('[data-reference-preview]');
                const referenceEditor = root.querySelector('[data-reference-editor]');
                const referenceEditorImage = root.querySelector('[data-reference-editor-image]');
                const referenceEditNote = root.querySelector('[data-reference-edit-note]');
                const referenceMaskInput = root.querySelector('[data-reference-mask-input]');
                const referenceMaskButton = root.querySelector('[data-reference-mask-button]');
                const referenceMaskName = root.querySelector('[data-reference-mask-name]');
                const referenceMaskClear = root.querySelector('[data-reference-mask-clear]');
                const results = root.querySelector('[data-ai-results]');
                const generateButton = root.querySelector('[data-generate-button]');
                const settingsPanel = root.querySelector('[data-settings-panel]');
                const settingsBackdrop = root.querySelector('[data-settings-backdrop]');
                const sizeMode = root.querySelector('[data-size-mode]');
                const ratioPanel = root.querySelector('[data-ratio-panel]');
                const customSizePanel = root.querySelector('[data-custom-size-panel]');
                const detailPanel = root.querySelector('[data-detail-panel]');
                const helpPanel = root.querySelector('[data-help-panel]');
                const statusFilter = root.querySelector('[data-status-filter]');
                const gallerySearch = root.querySelector('[data-gallery-search]');
                const galleryView = root.querySelector('[data-gallery-view]');
                const chatView = root.querySelector('[data-chat-view]');
                const chatMessages = root.querySelector('[data-chat-messages]');
                const chatSessions = root.querySelector('[data-chat-sessions]');
                const chatTitle = root.querySelector('[data-chat-title]');
                const chatMeta = root.querySelector('[data-chat-meta]');
                const imageControls = root.querySelector('[data-image-controls]');
                const chatControls = root.querySelector('[data-chat-controls]');
                const chatFilesInput = root.querySelector('[data-chat-files-input]');
                const chatFilesPreview = root.querySelector('[data-chat-files-preview]');
                const chatModelSelect = root.querySelector('[data-chat-model-select]');
                const chatReasoning = root.querySelector('[data-chat-reasoning]');
                const chatWebSearch = root.querySelector('[data-chat-web-search]');
                const csrf = form.querySelector('input[name="_token"]')?.value ?? document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

                const defaultImageModel = 'gpt-image-2';
                const defaultChatModel = 'gpt-5.5';
                const tasks = new Map();
                const trashedTasks = new Map();
                const chatStore = new Map();
                const trashedChatStore = new Map();
                const chatFileItems = [];
                const taskStorageKey = 'shopweb.ai-image.tasks.v1';
                let referenceItems = [];
                let activeReferenceIndex = null;
                let modelFetchTimer = null;
                let activeChatId = null;
                let currentMode = 'gallery';
                let webSearchEnabled = false;
                let savedConfigs = [];
                let showingChatTrash = false;

                const urlFromTemplate = (template, placeholder, value) => String(template || '').replace(placeholder, encodeURIComponent(value));
                const formData = () => new FormData(form);
                const formValue = (name, fallback = '') => String(formData().get(name) ?? fallback);
                const formNumber = (name, fallback = 0) => Number(formValue(name, String(fallback)) || fallback);
                const isChecked = (name) => Boolean(formData().get(name));
                const generationCount = () => Math.max(1, Math.min(8, formNumber('count', 1) || 1));

                const setStatus = (message, tone = 'zinc') => {
                    status.textContent = message;
                    status.className = `mt-3 text-xs ${tone === 'red' ? 'text-red-600' : tone === 'green' ? 'text-emerald-600' : 'text-zinc-500'}`;
                };

                const activeModel = () => manualModel.value.trim() || modelSelect.value || (configMode.value === 'default' ? defaultImageModel : '');
                const selectedChatModel = () => chatModelSelect.value || manualModel.value.trim() || (configMode.value === 'default' ? defaultChatModel : '');
                const modelOptionExists = (select, model) => Array.from(select.options).some((option) => option.value === model);
                const firstConcreteModel = (select) => Array.from(select.options).find((option) => option.value)?.value || '';

                const setMode = (mode) => {
                    currentMode = mode === 'chat' ? 'chat' : 'gallery';
                    galleryView.classList.toggle('hidden', currentMode !== 'gallery');
                    chatView.classList.toggle('hidden', currentMode !== 'chat');
                    imageControls?.classList.toggle('hidden', currentMode === 'chat');
                    imageControls?.classList.toggle('flex', currentMode !== 'chat');
                    chatControls?.classList.toggle('hidden', currentMode !== 'chat');
                    chatControls?.classList.toggle('flex', currentMode === 'chat');
                    referencePreview.classList.toggle('hidden', currentMode === 'chat' || referenceItems.length === 0);
                    chatFilesPreview.classList.toggle('hidden', currentMode !== 'chat' || chatFileItems.length === 0);
                    chatFilesPreview.classList.toggle('flex', currentMode === 'chat' && chatFileItems.length > 0);
                    promptInput.placeholder = currentMode === 'chat' ? '向 AI 发送消息...' : '描述你想生成的图片...';
                    generateButton.title = currentMode === 'chat' ? '发送' : '生成';
                    generateButton.setAttribute('aria-label', currentMode === 'chat' ? '发送' : '生成');

                    root.querySelectorAll('[data-mode-button]').forEach((button) => {
                        const active = button.dataset.modeButton === currentMode;
                        button.classList.toggle('border', active);
                        button.classList.toggle('border-zinc-200', active);
                        button.classList.toggle('bg-zinc-100', active);
                        button.classList.toggle('font-semibold', active);
                        button.classList.toggle('text-zinc-950', active);
                        button.classList.toggle('shadow-inner', active);
                        button.classList.toggle('text-zinc-500', !active);
                    });

                    setStatus(currentMode === 'chat' ? '等待输入聊天消息。' : '等待填写提示词。');
                };

                root.querySelectorAll('[data-mode-button]').forEach((button) => {
                    button.addEventListener('click', () => setMode(button.dataset.modeButton));
                });

                const openSettings = () => {
                    settingsPanel.classList.remove('translate-x-full');
                    settingsBackdrop.classList.remove('hidden');
                };

                const closeSettings = () => {
                    settingsPanel.classList.add('translate-x-full');
                    settingsBackdrop.classList.add('hidden');
                };

                root.querySelector('[data-settings-toggle]')?.addEventListener('click', openSettings);
                root.querySelector('[data-settings-close]')?.addEventListener('click', closeSettings);
                settingsBackdrop.addEventListener('click', closeSettings);

                root.querySelector('[data-help-toggle]')?.addEventListener('click', () => {
                    helpPanel.classList.toggle('hidden');
                    window.setTimeout(() => helpPanel.classList.add('hidden'), 5000);
                });

                const updateConfigMode = () => {
                    const custom = configMode.value === 'custom';
                    endpointInput.disabled = !custom;
                    apiKeyInput.disabled = !custom;
                    chatEndpointInput.disabled = !custom;
                    chatApiKeyInput.disabled = !custom;
                    endpointField?.classList.toggle('hidden', !custom);
                    keyField?.classList.toggle('hidden', !custom);
                    chatEndpointField?.classList.toggle('hidden', !custom);
                    chatKeyField?.classList.toggle('hidden', !custom);
                    savedConfigField?.classList.toggle('hidden', !custom);
                    customConfigActions?.classList.toggle('hidden', !custom);

                    if (!custom && !configName.value.trim()) {
                        configName.value = '榛樿閰嶇疆';
                    }
                };

                configMode.addEventListener('change', () => {
                    if (configMode.value === 'default') {
                        configName.value = configName.value.trim() || '榛樿閰嶇疆';
                    }
                    updateConfigMode();
                });
                updateConfigMode();

                const renderSavedConfigs = () => {
                    if (!savedConfigSelect) return;

                    const current = savedConfigSelect.value;
                    savedConfigSelect.innerHTML = '<option value="">No saved config</option>';
                    savedConfigs.forEach((config) => {
                        const option = document.createElement('option');
                        option.value = String(config.id);
                        option.textContent = `${config.name}${config.is_default ? ' (default)' : ''}`;
                        savedConfigSelect.appendChild(option);
                    });

                    if (current && savedConfigs.some((config) => String(config.id) === current)) {
                        savedConfigSelect.value = current;
                    }
                };

                const applySavedConfig = (config) => {
                    if (!config) return;

                    configMode.value = 'custom';
                    configName.value = config.name || 'Custom config';
                    endpointInput.value = config.image_endpoint || '';
                    apiKeyInput.value = '';
                    apiKeyInput.placeholder = config.has_image_key ? 'Saved key; fill to replace' : 'sk-...';
                    chatEndpointInput.value = config.chat_endpoint || '';
                    chatApiKeyInput.value = '';
                    chatApiKeyInput.placeholder = config.has_chat_key ? 'Saved key; fill to replace' : 'sk-...';
                    manualModel.value = config.image_model || '';
                    if (config.chat_model && !Array.from(chatModelSelect.options).some((option) => option.value === config.chat_model)) {
                        const option = document.createElement('option');
                        option.value = config.chat_model;
                        option.textContent = config.chat_model;
                        chatModelSelect.appendChild(option);
                    }
                    chatModelSelect.value = config.chat_model || '';
                    savedConfigSelect.value = String(config.id);
                    updateConfigMode();
                    syncChatModelOptions();
                };

                const loadSavedConfigs = async () => {
                    try {
                        const response = await fetch(root.dataset.configsUrl, {
                            headers: { 'Accept': 'application/json' },
                        });
                        const data = await response.json();
                        if (!response.ok) throw new Error(data.message || 'Config list failed.');
                        savedConfigs = Array.isArray(data.configs) ? data.configs : [];
                        renderSavedConfigs();
                    } catch (error) {
                        setStatus(error.message || 'Config list failed.', 'red');
                    }
                };

                const saveCurrentConfig = async () => {
                    const payload = new FormData();
                    payload.set('name', configName.value.trim() || 'Custom config');
                    if (savedConfigSelect.value) payload.set('config_id', savedConfigSelect.value);
                    payload.set('image_endpoint', endpointInput.value.trim());
                    payload.set('image_api_key', apiKeyInput.value);
                    payload.set('chat_endpoint', chatEndpointInput.value.trim());
                    payload.set('chat_api_key', chatApiKeyInput.value);
                    payload.set('image_model', activeModel());
                    payload.set('chat_model', selectedChatModel());
                    payload.set('is_default', '0');

                    const response = await fetch(root.dataset.configSaveUrl, {
                        method: 'POST',
                        body: payload,
                        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                    });
                    const data = await response.json();
                    if (!response.ok) throw new Error(data.message || 'Config save failed.');

                    const index = savedConfigs.findIndex((config) => config.id === data.config.id);
                    if (index >= 0) {
                        savedConfigs.splice(index, 1, data.config);
                    } else {
                        savedConfigs.unshift(data.config);
                    }

                    renderSavedConfigs();
                    applySavedConfig(data.config);
                    setStatus('Config saved.', 'green');
                };

                const deleteCurrentConfig = async () => {
                    const id = savedConfigSelect.value;
                    if (!id) return;

                    const response = await fetch(`${root.dataset.configsUrl}/${encodeURIComponent(id)}`, {
                        method: 'DELETE',
                        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                    });
                    const data = await response.json().catch(() => ({}));
                    if (!response.ok) throw new Error(data.message || 'Config delete failed.');

                    savedConfigs = savedConfigs.filter((config) => String(config.id) !== String(id));
                    savedConfigSelect.value = '';
                    renderSavedConfigs();
                    setStatus('Config deleted.', 'green');
                };

                savedConfigSelect?.addEventListener('change', () => {
                    const config = savedConfigs.find((item) => String(item.id) === savedConfigSelect.value);
                    if (config) applySavedConfig(config);
                });
                root.querySelector('[data-save-ai-config]')?.addEventListener('click', () => {
                    saveCurrentConfig().catch((error) => setStatus(error.message || 'Config save failed.', 'red'));
                });
                root.querySelector('[data-delete-ai-config]')?.addEventListener('click', () => {
                    deleteCurrentConfig().catch((error) => setStatus(error.message || 'Config delete failed.', 'red'));
                });

                const serverFetch = async (url, options = {}) => {
                    const response = await fetch(url, {
                        ...options,
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                            ...(options.headers || {}),
                        },
                    });
                    const data = await response.json().catch(() => ({}));

                    if (!response.ok) {
                        throw new Error(data.message || '请求失败。');
                    }

                    return data;
                };

                const stableReferencePayload = (references = []) => references
                    .filter((reference) => reference.asset?.id || (reference.url && !String(reference.url).startsWith('blob:')))
                    .map((reference) => ({
                        name: reference.name,
                        url: reference.asset?.url || reference.url,
                        asset: reference.asset || null,
                        editNote: reference.editNote || '',
                        hasMask: Boolean(reference.hasMask),
                    }));

                const taskPayload = (task) => ({
                    id: task.id,
                    status: task.status,
                    stream: Boolean(task.stream),
                    prompt: task.prompt || '',
                    submittedPrompt: task.submittedPrompt || task.prompt || '',
                    config: task.config || {},
                    images: serializeImages(task.images),
                    partials: serializeImages(task.partials),
                    error: task.error || '',
                    createdAt: task.createdAt instanceof Date ? task.createdAt.toISOString() : task.createdAt,
                    elapsedMs: Math.round(Number(task.elapsedMs || 0)),
                    actualWidth: task.actualWidth || null,
                    actualHeight: task.actualHeight || null,
                    meta: task.meta || {},
                    references: stableReferencePayload(task.references),
                });

                const saveTaskToServer = (task) => {
                    if (!task || !root.dataset.taskSaveUrl) return;

                    serverFetch(root.dataset.taskSaveUrl, {
                        method: 'POST',
                        body: JSON.stringify(taskPayload(task)),
                        headers: { 'Content-Type': 'application/json' },
                    }).catch(() => {});
                };

                const deleteTaskFromServer = async (taskId) => {
                    if (!taskId || !root.dataset.taskDeleteUrlTemplate) return null;

                    return serverFetch(urlFromTemplate(root.dataset.taskDeleteUrlTemplate, '__TASK__', taskId), {
                        method: 'DELETE',
                    });
                };

                const restoreTaskFromServer = async (taskId) => {
                    if (!taskId || !root.dataset.taskRestoreUrlTemplate) return null;

                    const data = await serverFetch(urlFromTemplate(root.dataset.taskRestoreUrlTemplate, '__TASK__', taskId), {
                        method: 'POST',
                    });

                    return data.task || null;
                };

                const chatPayload = (session) => ({
                    id: session.id,
                    title: session.title || '新会话',
                    createdAt: session.createdAt instanceof Date ? session.createdAt.toISOString() : session.createdAt,
                    updatedAt: session.updatedAt instanceof Date ? session.updatedAt.toISOString() : session.updatedAt,
                    messages: (session.messages || []).slice(-200).map((message) => ({
                        role: message.role,
                        content: message.content || '',
                        files: (message.files || []).map((file) => ({
                            ...file,
                            url: file.url && !String(file.url).startsWith('blob:') ? file.url : '',
                        })),
                        model: message.model || '',
                        reasoning: message.reasoning || '',
                        reasoningLabel: message.reasoningLabel || '',
                        error: Boolean(message.error),
                    })),
                });

                const saveChatToServer = (session) => {
                    if (!session || !root.dataset.chatSaveUrl) return;

                    serverFetch(root.dataset.chatSaveUrl, {
                        method: 'POST',
                        body: JSON.stringify(chatPayload(session)),
                        headers: { 'Content-Type': 'application/json' },
                    }).catch(() => {});
                };

                const deleteChatFromServer = async (sessionId) => {
                    if (!sessionId || !root.dataset.chatDeleteUrlTemplate) return null;

                    return serverFetch(urlFromTemplate(root.dataset.chatDeleteUrlTemplate, '__SESSION__', sessionId), {
                        method: 'DELETE',
                    });
                };

                const restoreChatFromServer = async (sessionId) => {
                    if (!sessionId || !root.dataset.chatRestoreUrlTemplate) return null;

                    const data = await serverFetch(urlFromTemplate(root.dataset.chatRestoreUrlTemplate, '__SESSION__', sessionId), {
                        method: 'POST',
                    });

                    return data.chat || null;
                };

                const autoGrowPrompt = () => {
                    promptInput.style.height = 'auto';
                    promptInput.style.height = `${Math.min(promptInput.scrollHeight, 128)}px`;
                    clearPromptButton?.classList.toggle('hidden', !promptInput.value.trim());
                    clearPromptButton?.classList.toggle('flex', Boolean(promptInput.value.trim()));
                };

                const updateWebSearchButton = () => {
                    if (!chatWebSearch) return;

                    chatWebSearch.setAttribute('aria-pressed', webSearchEnabled ? 'true' : 'false');
                    chatWebSearch.classList.toggle('bg-blue-100', webSearchEnabled);
                    chatWebSearch.classList.toggle('text-blue-700', webSearchEnabled);
                    chatWebSearch.classList.toggle('ring-1', webSearchEnabled);
                    chatWebSearch.classList.toggle('ring-blue-200', webSearchEnabled);
                    chatWebSearch.classList.toggle('bg-zinc-100', !webSearchEnabled);
                    chatWebSearch.classList.toggle('text-zinc-400', !webSearchEnabled);
                };

                promptInput.addEventListener('input', autoGrowPrompt);
                clearPromptButton?.addEventListener('click', () => {
                    promptInput.value = '';
                    autoGrowPrompt();
                    promptInput.focus();
                });
                promptInput.addEventListener('keydown', (event) => {
                    if (currentMode !== 'chat' || event.key !== 'Enter' || event.shiftKey || event.isComposing) return;

                    event.preventDefault();
                    submitWorkbench();
                });
                autoGrowPrompt();
                chatWebSearch?.addEventListener('click', () => {
                    webSearchEnabled = !webSearchEnabled;
                    updateWebSearchButton();
                });
                updateWebSearchButton();

                const syncChatModelOptions = () => {
                    const current = chatModelSelect.value;
                    chatModelSelect.innerHTML = `<option value="">榛樿妯″瀷 ${defaultChatModel}</option>`;

                    Array.from(modelSelect.options).forEach((option) => {
                        if (!option.value) return;

                        const clone = document.createElement('option');
                        clone.value = option.value;
                        clone.textContent = option.textContent;
                        chatModelSelect.appendChild(clone);
                    });

                    const manual = manualModel.value.trim();
                    if (manual && !Array.from(chatModelSelect.options).some((option) => option.value === manual)) {
                        const option = document.createElement('option');
                        option.value = manual;
                        option.textContent = `手动：${manual}`;
                        chatModelSelect.appendChild(option);
                    }

                    if (current && Array.from(chatModelSelect.options).some((option) => option.value === current)) {
                        chatModelSelect.value = current;
                    }
                };

                modelSelect.addEventListener('change', syncChatModelOptions);
                manualModel.addEventListener('input', syncChatModelOptions);

                const currentChat = () => (showingChatTrash ? trashedChatStore : chatStore).get(activeChatId);
                const taskById = (taskId) => tasks.get(taskId) || trashedTasks.get(taskId);

                const createChatSession = (title = '新会话', sync = false) => {
                    const id = `chat-${Date.now()}-${Math.random().toString(16).slice(2)}`;
                    const session = {
                        id,
                        title,
                        messages: [],
                        createdAt: new Date(),
                        updatedAt: new Date(),
                    };

                    chatStore.set(id, session);
                    activeChatId = id;
                    renderChatSessions();
                    renderChats();
                    if (sync) saveChatToServer(session);

                    return session;
                };

                const deleteActiveChat = () => {
                    const store = showingChatTrash ? trashedChatStore : chatStore;
                    const session = currentChat();

                    if (showingChatTrash) {
                        if (!activeChatId) return;

                        const restoringId = activeChatId;

                        restoreChatFromServer(restoringId)
                            .then((restored) => {
                                if (restored) {
                                    trashedChatStore.delete(restoringId);
                                    applyStoredChats([...chatStore.values(), restored]);
                                    showingChatTrash = false;
                                    activeChatId = restored.id;
                                    renderChatSessions();
                                    renderChats();
                                    setStatus('会话已恢复。', 'green');
                                }
                            })
                            .catch((error) => setStatus(error.message || '会话恢复失败。', 'red'));
                        return;
                    }

                    if (activeChatId) {
                        const deletingId = activeChatId;
                        const deletedSession = store.get(deletingId);

                        if (deletedSession) {
                            deletedSession.deletedAt = new Date();
                            trashedChatStore.set(deletingId, deletedSession);
                        }

                        store.delete(deletingId);
                        deleteChatFromServer(deletingId)
                            .catch((error) => {
                                if (deletedSession) {
                                    trashedChatStore.delete(deletingId);
                                    chatStore.set(deletingId, deletedSession);
                                    activeChatId = deletingId;
                                    renderChatSessions();
                                    renderChats();
                                }
                                setStatus(error.message || '会话删除失败。', 'red');
                            });
                    }

                    activeChatId = store.keys().next().value ?? null;

                    if (!activeChatId) {
                        createChatSession();
                        setStatus('会话已删除。', 'green');
                        return;
                    }

                    renderChatSessions();
                    renderChats();
                    setStatus('会话已删除。', 'green');
                };

                const chatReasoningLabel = () => chatReasoning.options[chatReasoning.selectedIndex]?.textContent || '低';
                const chatTitleFromMessage = (message) => {
                    const title = String(message || '').replace(/\s+/g, ' ').trim();
                    if (!title) return '新会话';

                    return title.length > 24 ? `${title.slice(0, 24)}...` : title;
                };

                const renderChatSessions = () => {
                    chatSessions.innerHTML = '';
                    const store = showingChatTrash ? trashedChatStore : chatStore;
                    const deleteLabel = root.querySelector('[data-chat-delete-label]');
                    if (deleteLabel) deleteLabel.textContent = showingChatTrash ? '恢复会话' : '删除会话';

                    Array.from(store.values()).forEach((session) => {
                        const active = session.id === activeChatId;
                        const button = document.createElement('button');
                        button.className = `block w-full rounded-2xl px-3 py-3 text-left text-sm transition ${active ? 'bg-zinc-950 text-white' : 'text-zinc-700 hover:bg-zinc-100'}`;
                        button.type = 'button';
                        button.dataset.chatSession = session.id;
                        button.innerHTML = `
                            <span class="block truncate font-medium">${escapeHtml(session.title)}</span>
                            <span class="mt-1 block text-xs ${active ? 'text-white/55' : 'text-zinc-400'}">${session.messages.length} 条消息</span>
                        `;
                        chatSessions.appendChild(button);
                    });
                };

                const renderChatFiles = () => {
                    chatFilesPreview.innerHTML = '';
                    chatFilesPreview.classList.toggle('hidden', currentMode !== 'chat' || chatFileItems.length === 0);
                    chatFilesPreview.classList.toggle('flex', currentMode === 'chat' && chatFileItems.length > 0);

                    chatFileItems.forEach((item, index) => {
                        const chip = document.createElement('div');
                        chip.className = 'group flex h-14 max-w-64 shrink-0 items-center gap-2 rounded-2xl border border-zinc-200 bg-white px-2 shadow-sm';
                        chip.innerHTML = `
                            ${item.url ? `<img class="h-10 w-10 rounded-xl object-cover" src="${escapeAttribute(item.url)}" alt="">` : '<span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-zinc-100 text-xs font-semibold text-zinc-500">FILE</span>'}
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-xs font-medium text-zinc-700">${escapeHtml(item.file.name)}</span>
                                <span class="block text-[11px] text-zinc-400">${formatBytes(item.file.size)}</span>
                            </span>
                            <button class="hidden h-6 w-6 items-center justify-center rounded-full bg-zinc-100 text-zinc-500 group-hover:flex" type="button" data-remove-chat-file="${index}" aria-label="绉婚櫎闄勪欢">脳</button>
                        `;
                        chatFilesPreview.appendChild(chip);
                    });
                };

                const clearChatFiles = ({ revoke = true } = {}) => {
                    if (revoke) {
                        chatFileItems.forEach((item) => {
                            if (item.url) URL.revokeObjectURL(item.url);
                        });
                    }

                    chatFileItems.length = 0;
                    chatFilesInput.value = '';
                    renderChatFiles();
                };

                root.querySelector('[data-chat-new]')?.addEventListener('click', () => {
                    showingChatTrash = false;
                    createChatSession('新会话', true);
                    setStatus('已新增会话。', 'green');
                });
                root.querySelector('[data-chat-delete]')?.addEventListener('click', deleteActiveChat);
                root.querySelector('[data-chat-trash-toggle]')?.addEventListener('click', () => {
                    showingChatTrash = !showingChatTrash;
                    activeChatId = (showingChatTrash ? trashedChatStore : chatStore).keys().next().value ?? null;
                    renderChatSessions();
                    renderChats();
                    setStatus(showingChatTrash ? '正在查看会话回收站。' : '已返回当前会话。');
                });
                chatSessions.addEventListener('click', (event) => {
                    const button = event.target.closest('[data-chat-session]');
                    if (!button) return;

                    activeChatId = button.dataset.chatSession;
                    renderChatSessions();
                    renderChats();
                });
                root.querySelector('[data-chat-files-button]')?.addEventListener('click', () => chatFilesInput.click());
                chatFilesInput.addEventListener('change', () => {
                    Array.from(chatFilesInput.files ?? []).forEach((file) => {
                        const url = file.type.startsWith('image/') ? URL.createObjectURL(file) : null;
                        chatFileItems.push({ file, url });
                    });

                    chatFilesInput.value = '';
                    renderChatFiles();
                });
                chatFilesPreview.addEventListener('click', (event) => {
                    const button = event.target.closest('[data-remove-chat-file]');
                    if (!button) return;

                    const item = chatFileItems[Number(button.dataset.removeChatFile)];
                    if (item?.url) URL.revokeObjectURL(item.url);
                    chatFileItems.splice(Number(button.dataset.removeChatFile), 1);
                    renderChatFiles();
                });

                const updateSizeMode = () => {
                    const mode = sizeMode.value;
                    ratioPanel.classList.toggle('hidden', mode !== 'ratio');
                    customSizePanel.classList.toggle('hidden', mode !== 'custom');
                };

                sizeMode.addEventListener('change', updateSizeMode);
                updateSizeMode();

                root.querySelector('[data-reference-button]')?.addEventListener('click', () => referenceInput.click());

                referenceInput.addEventListener('change', () => {
                    const remainingSlots = Math.max(0, 6 - referenceItems.length);
                    const incomingItems = Array.from(referenceInput.files ?? [])
                        .slice(0, remainingSlots)
                        .map((file) => ({
                            file,
                            asset: null,
                            editNote: '',
                            maskFile: null,
                            previewUrl: URL.createObjectURL(file),
                        }));

                    referenceItems = [...referenceItems, ...incomingItems].slice(0, 6);
                    referenceInput.value = '';
                    renderReferencePreview();
                    incomingItems.forEach((item) => uploadReferenceAsset(item));
                });

                const renderReferencePreview = () => {
                    referencePreview.innerHTML = '';
                    referencePreview.classList.toggle('hidden', currentMode === 'chat' || referenceItems.length === 0);

                    referenceItems.forEach((reference, index) => {
                        const item = document.createElement('div');
                        item.className = 'group relative h-14 w-14 shrink-0 overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm';
                        const url = reference.previewUrl;
                        item.innerHTML = `
                            <img class="aspect-square w-full object-cover" src="${escapeAttribute(url)}" alt="">
                            <span class="absolute right-1 top-1 hidden rounded-full bg-zinc-950/70 px-2 py-1 text-[10px] text-white group-hover:block">绉婚櫎</span>
                        `;
                        item.addEventListener('click', () => {
                            removeReference(index);
                            referenceInput.value = '';
                            renderReferencePreview();
                        });
                        item.querySelector('img')?.addEventListener('load', () => {}, { once: true });
                        item.innerHTML = `
                            <button class="block h-full w-full" type="button" data-edit-reference="${index}" aria-label="缂栬緫鍙傝€冨浘">
                                <img class="h-full w-full object-cover transition duration-150 group-hover:brightness-50" src="${escapeAttribute(reference.previewUrl)}" alt="">
                                <span class="absolute inset-0 hidden items-center justify-center text-white group-hover:flex">
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                                </span>
                                <span class="absolute bottom-1 left-1 rounded-full bg-zinc-950/75 px-1.5 py-0.5 text-[10px] font-semibold text-white">${index + 1}</span>
                                ${reference.maskFile ? '<span class="absolute bottom-1 right-1 rounded-full bg-emerald-600 px-1.5 py-0.5 text-[10px] font-semibold text-white">閬僵</span>' : ''}
                                ${reference.editNote ? '<span class="absolute inset-x-0 bottom-0 h-1 bg-blue-500"></span>' : ''}
                            </button>
                            <button class="absolute right-1 top-1 hidden h-5 w-5 items-center justify-center rounded-full bg-zinc-950/75 text-xs font-bold leading-none text-white group-hover:flex" type="button" data-remove-reference="${index}" aria-label="鍒犻櫎鍙傝€冨浘">脳</button>
                        `;
                        item.addEventListener('click', (event) => {
                            event.stopImmediatePropagation();

                            if (event.target.closest('[data-remove-reference]')) {
                                removeReference(index);
                                return;
                            }

                            openReferenceEditor(index);
                        }, { capture: true });
                        referencePreview.appendChild(item);
                    });
                };

                const removeReference = (index) => {
                    const item = referenceItems[index];
                    if (!item) return;

                    if (item.ownedPreviewUrl !== false) URL.revokeObjectURL(item.previewUrl);
                    if (item.maskPreviewUrl) URL.revokeObjectURL(item.maskPreviewUrl);
                    referenceItems.splice(index, 1);
                    referenceInput.value = '';

                    if (activeReferenceIndex === index) {
                        closeReferenceEditor();
                    } else if (activeReferenceIndex !== null && activeReferenceIndex > index) {
                        activeReferenceIndex -= 1;
                    }

                    renderReferencePreview();
                };

                const uploadReferenceAsset = async (item) => {
                    if (!item?.file || item.asset?.id) return;

                    const payload = new FormData();
                    payload.set('reference_image', item.file);

                    try {
                        const response = await fetch(root.dataset.referenceUploadUrl, {
                            method: 'POST',
                            body: payload,
                            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                        });
                        const data = await response.json();
                        if (!response.ok) throw new Error(data.message || 'Reference upload failed.');
                        item.asset = data.asset;
                        item.previewUrl = data.asset.url || item.previewUrl;
                        item.ownedPreviewUrl = false;
                        renderReferencePreview();
                    } catch (error) {
                        item.assetError = error.message || 'Reference upload failed.';
                    }
                };

                const resetReferences = () => {
                    referenceItems.forEach((item) => {
                        if (item.ownedPreviewUrl !== false) URL.revokeObjectURL(item.previewUrl);
                        if (item.maskPreviewUrl) URL.revokeObjectURL(item.maskPreviewUrl);
                    });
                    referenceItems = [];
                    referenceInput.value = '';
                    renderReferencePreview();
                };

                const cloneTaskReferences = async (task) => {
                    const references = [];

                    for (const [index, reference] of (task.references ?? []).entries()) {
                        if (reference.asset?.id) {
                            references.push({
                                file: null,
                                asset: reference.asset,
                                editNote: reference.editNote || '',
                                maskFile: null,
                                previewUrl: reference.asset.url || reference.url,
                                ownedPreviewUrl: false,
                            });
                            continue;
                        }

                        if (reference.file instanceof File) {
                            references.push({
                                file: reference.file,
                                asset: reference.asset || null,
                                editNote: reference.editNote || '',
                                maskFile: reference.maskFile || null,
                                previewUrl: URL.createObjectURL(reference.file),
                                ownedPreviewUrl: true,
                            });
                            continue;
                        }

                        if (!reference.url) continue;

                        try {
                            const response = await fetch(reference.url);
                            const blob = await response.blob();
                            const file = new File([blob], reference.name || `reference-${index + 1}.png`, { type: blob.type || 'image/png' });
                            references.push({
                                file,
                                editNote: reference.editNote || '',
                                maskFile: null,
                                previewUrl: URL.createObjectURL(file),
                                ownedPreviewUrl: true,
                            });
                        } catch (error) {
                            references.push({
                                file: null,
                                editNote: reference.editNote || '',
                                maskFile: null,
                                previewUrl: reference.url,
                                ownedPreviewUrl: false,
                            });
                        }
                    }

                    return references;
                };

                const imageToReference = async (image, task) => {
                    const src = image?.url || image?.data_url || '';
                    if (!src) return null;

                    const response = await fetch(src);
                    const blob = await response.blob();
                    const extension = (blob.type.split('/')[1] || task.config.format || 'png').replace('jpeg', 'jpg');
                    const file = new File([blob], `ai-output-${task.id}.${extension}`, { type: blob.type || 'image/png' });

                    return {
                        file,
                        editNote: '',
                        maskFile: null,
                        previewUrl: URL.createObjectURL(file),
                    };
                };

                const openReferenceEditor = (index) => {
                    const item = referenceItems[index];
                    if (!item) return;

                    activeReferenceIndex = index;
                    referenceEditorImage.src = item.previewUrl;
                    referenceEditNote.value = item.editNote ?? '';
                    referenceMaskName.textContent = item.maskFile ? item.maskFile.name : '未选择遮罩图';
                    referenceMaskClear.classList.toggle('hidden', !item.maskFile);
                    referenceMaskInput.value = '';
                    referenceEditor.classList.remove('hidden');
                };

                const closeReferenceEditor = () => {
                    activeReferenceIndex = null;
                    referenceEditor.classList.add('hidden');
                    referenceEditorImage.src = '';
                    referenceMaskInput.value = '';
                };

                root.querySelectorAll('[data-reference-editor-close]').forEach((button) => {
                    button.addEventListener('click', closeReferenceEditor);
                });

                referenceEditor.addEventListener('click', (event) => {
                    if (event.target === referenceEditor) closeReferenceEditor();
                });

                referenceMaskButton.addEventListener('click', () => referenceMaskInput.click());

                referenceMaskInput.addEventListener('change', () => {
                    const item = referenceItems[activeReferenceIndex];
                    const file = referenceMaskInput.files?.[0];
                    if (!item || !file) return;

                    if (item.maskPreviewUrl) URL.revokeObjectURL(item.maskPreviewUrl);
                    item.maskFile = file;
                    item.maskPreviewUrl = URL.createObjectURL(file);
                    referenceMaskName.textContent = file.name;
                    referenceMaskClear.classList.remove('hidden');
                    renderReferencePreview();
                });

                referenceMaskClear.addEventListener('click', () => {
                    const item = referenceItems[activeReferenceIndex];
                    if (!item) return;

                    if (item.maskPreviewUrl) URL.revokeObjectURL(item.maskPreviewUrl);
                    item.maskFile = null;
                    item.maskPreviewUrl = null;
                    referenceMaskInput.value = '';
                    referenceMaskName.textContent = '未选择遮罩图';
                    referenceMaskClear.classList.add('hidden');
                    renderReferencePreview();
                });

                root.querySelector('[data-reference-editor-save]')?.addEventListener('click', () => {
                    const item = referenceItems[activeReferenceIndex];
                    if (!item) return;

                    item.editNote = referenceEditNote.value.trim();
                    renderReferencePreview();
                    closeReferenceEditor();
                    setStatus('参考图设置已保存。', 'green');
                });

                const fetchModels = async () => {
                    const modelEndpoint = currentMode === 'chat'
                        ? (chatEndpointInput.value.trim() || endpointInput.value.trim())
                        : endpointInput.value.trim();
                    if (configMode.value === 'custom' && !modelEndpoint && !savedConfigSelect?.value) {
                        setStatus('请先在右上角设置里填写 API URL。', 'red');
                        openSettings();
                        return;
                    }

                    const payload = new FormData();
                    payload.append('feature', currentMode === 'chat' ? 'chat' : 'image');
                    payload.append('config_mode', configMode.value);
                    payload.append('config_id', savedConfigSelect?.value || '');
                    payload.append('config_name', configName.value);
                    if (configMode.value === 'custom') {
                        payload.append('endpoint', endpointInput.value);
                        payload.append('api_key', apiKeyInput.value);
                        payload.append('chat_endpoint', chatEndpointInput.value);
                        payload.append('chat_api_key', chatApiKeyInput.value);
                    }
                    setStatus(currentMode === 'chat' ? '姝ｅ湪鑾峰彇鍙敤鑱婂ぉ妯″瀷...' : '姝ｅ湪鑾峰彇鍙敤鍥剧墖妯″瀷...');

                    try {
                        const response = await fetch(root.dataset.modelsUrl, {
                            method: 'POST',
                            body: payload,
                            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                        });
                        const data = await response.json();

                        if (!response.ok) throw new Error(data.message || '模型列表获取失败。');

                        const targetSelect = currentMode === 'chat' ? chatModelSelect : modelSelect;
                        targetSelect.innerHTML = currentMode === 'chat' ? `<option value="">榛樿妯″瀷 ${defaultChatModel}</option>` : `<option value="">榛樿妯″瀷 ${defaultImageModel}</option>`;
                        if (!data.models?.length && currentMode !== 'chat') {
                            targetSelect.insertAdjacentHTML('beforeend', '<option value="">没有识别到模型，请手动填写</option>');
                        } else {
                            data.models?.forEach((model) => {
                                const option = document.createElement('option');
                                option.value = model.id;
                                option.textContent = model.name || model.id;
                                targetSelect.appendChild(option);
                            });
                        }

                        if (currentMode !== 'chat') {
                            if (!modelOptionExists(targetSelect, defaultImageModel)) {
                                targetSelect.value = firstConcreteModel(targetSelect);
                            }
                        } else if (!modelOptionExists(targetSelect, defaultChatModel)) {
                            targetSelect.value = firstConcreteModel(targetSelect);
                        }

                        if (currentMode !== 'chat') syncChatModelOptions();

                        setStatus(`已获取 ${data.models?.length ?? 0} 个模型。`, 'green');
                    } catch (error) {
                        setStatus(error.message || '模型列表获取失败。', 'red');
                    }
                };

                const scheduleModelFetch = () => {
                    window.clearTimeout(modelFetchTimer);
                    const endpoint = currentMode === 'chat'
                        ? (chatEndpointInput.value.trim() || endpointInput.value.trim())
                        : endpointInput.value.trim();
                    if (configMode.value === 'custom' && !endpoint && !savedConfigSelect?.value) return;
                    modelFetchTimer = window.setTimeout(fetchModels, 700);
                };

                root.querySelector('[data-fetch-models]')?.addEventListener('click', fetchModels);
                configMode.addEventListener('change', scheduleModelFetch);
                endpointInput.addEventListener('change', scheduleModelFetch);
                endpointInput.addEventListener('blur', scheduleModelFetch);
                apiKeyInput.addEventListener('change', scheduleModelFetch);
                apiKeyInput.addEventListener('blur', scheduleModelFetch);
                chatEndpointInput.addEventListener('change', scheduleModelFetch);
                chatEndpointInput.addEventListener('blur', scheduleModelFetch);
                chatApiKeyInput.addEventListener('change', scheduleModelFetch);
                chatApiKeyInput.addEventListener('blur', scheduleModelFetch);

                const referenceEditInstructions = () => referenceItems
                    .map((reference, index) => {
                        const parts = [];
                        if (reference.editNote) parts.push(reference.editNote);
                        if (reference.maskFile) parts.push('已提供遮罩图，请只修改遮罩区域。');

                        return parts.length ? `参考图 ${index + 1}：${parts.join(' ')}` : '';
                    })
                    .filter(Boolean)
                    .join('\n');

                const composedPrompt = () => {
                    const prompt = promptInput.value.trim();
                    const instructions = referenceEditInstructions();

                    return instructions ? `${prompt}\n\n参考图修改要求：\n${instructions}` : prompt;
                };

                const buildPayload = (countOverride = null) => {
                    const model = activeModel();
                    if (!model) {
                        openSettings();
                        throw new Error('请先在右上角设置里选择或填写图片模型。');
                    }

                    const payload = new FormData(form);
                    payload.set('model', model);
                    payload.set('prompt', composedPrompt());
                    payload.set('config_mode', configMode.value);
                    payload.set('config_id', savedConfigSelect?.value || '');
                    payload.set('config_name', configName.value.trim() || (configMode.value === 'default' ? '默认配置' : '自定义配置'));
                    payload.delete('manual_model');
                    payload.delete('reference_images[]');
                    payload.delete('reference_asset_ids[]');
                    payload.delete('mask_image');

                    if (configMode.value !== 'custom') {
                        payload.delete('endpoint');
                        payload.delete('api_key');
                        payload.delete('chat_endpoint');
                        payload.delete('chat_api_key');
                    }

                    referenceItems.forEach((reference) => {
                        if (reference.asset?.id) {
                            payload.append('reference_asset_ids[]', reference.asset.id);
                        } else if (reference.file) {
                            payload.append('reference_images[]', reference.file);
                        }
                    });

                    const maskReference = referenceItems.find((reference) => reference.maskFile);
                    if (maskReference?.maskFile) {
                        payload.set('mask_image', maskReference.maskFile);
                    }

                    if (countOverride !== null) {
                        payload.set('count', String(countOverride));
                    }

                    return payload;
                };

                const buildChatPayload = () => {
                    const model = selectedChatModel();
                    if (!model) {
                        openSettings();
                        throw new Error('请先在右下角或右上角设置里选择聊天模型。');
                    }

                    const payload = new FormData();
                    payload.set('config_mode', configMode.value);
                    payload.set('config_id', savedConfigSelect?.value || '');
                    payload.set('config_name', configName.value.trim() || (configMode.value === 'default' ? '默认配置' : '自定义配置'));
                    payload.set('model', model);
                    payload.set('prompt', promptInput.value.trim());
                    payload.set('reasoning_mode', chatReasoning.value || 'low');
                    payload.set('web_search', webSearchEnabled ? '1' : '0');
                    payload.set('timeout_seconds', formValue('timeout_seconds', '600'));

                    if (configMode.value === 'custom') {
                        payload.set('endpoint', endpointInput.value);
                        payload.set('api_key', apiKeyInput.value);
                        payload.set('chat_endpoint', chatEndpointInput.value);
                        payload.set('chat_api_key', chatApiKeyInput.value);
                    }

                    chatFileItems.forEach((item) => payload.append('chat_files[]', item.file));

                    return payload;
                };

                const requestTimeoutMs = () => Math.max(30, Math.min(1200, formNumber('timeout_seconds', 600) || 600)) * 1000;
                const runWithRequestTimeout = async (operation) => {
                    const controller = new AbortController();
                    const timeoutId = window.setTimeout(() => controller.abort(), requestTimeoutMs());

                    try {
                        return await operation(controller.signal);
                    } catch (error) {
                        if (error?.name === 'AbortError') {
                            throw new Error(`请求超过 ${Math.round(requestTimeoutMs() / 1000)} 秒未完成，已自动取消。`);
                        }

                        throw error;
                    } finally {
                        window.clearTimeout(timeoutId);
                    }
                };

                const submitWorkbench = async () => {
                    if (!promptInput.value.trim()) {
                        setStatus(currentMode === 'chat' ? '请先输入聊天消息。' : '请先填写提示词。', 'red');
                        return;
                    }

                    try {
                        if (currentMode === 'chat') {
                            generateButton.disabled = true;
                            const chatPayload = buildChatPayload();
                            await appendChatMessage(promptInput.value.trim(), chatPayload);
                            promptInput.value = '';
                            autoGrowPrompt();
                            return;
                        }

                        const count = generationCount();
                        const stream = isChecked('stream');
                        generateButton.disabled = true;

                        if (stream) {
                            await generateStreamed(count);
                        } else {
                            await generateNormal();
                        }
                    } catch (error) {
                        setStatus(error.message || '图片生成失败。', 'red');
                    } finally {
                        generateButton.disabled = false;
                    }
                };

                form.addEventListener('submit', (event) => {
                    event.preventDefault();
                    submitWorkbench();
                });
                generateButton.addEventListener('click', submitWorkbench);

                const generateNormal = async () => {
                    const count = generationCount();
                    const placeholders = Array.from({ length: count }, () => createTask({ status: 'running' }));
                    renderTasks();
                    placeholders.forEach((task) => startTimer(task.id));
                    setStatus('正在生成图片，请稍候...');

                    try {
                        const response = await runWithRequestTimeout((signal) => fetch(root.dataset.generateUrl, {
                            method: 'POST',
                            body: buildPayload(),
                            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                            signal,
                        }));
                        const data = await response.json();

                        if (!response.ok) throw new Error(data.message || '图片生成失败。');

                        const images = data.images ?? [];
                        placeholders.forEach((task, index) => {
                            finishTask(task.id, images[index] ? [images[index]] : [], data.meta ?? {});
                        });
                        setStatus(`生成完成，共 ${data.images?.length ?? 0} 张。`, 'green');
                    } catch (error) {
                        placeholders.forEach((task) => failTask(task.id, error.message || '图片生成失败。'));
                        throw error;
                    }
                };

                const generateStreamed = async (count) => {
                    setStatus(count > 1 ? '正在并发流式生成多张图片...' : '正在流式生成图片...');

                    await Promise.all(Array.from({ length: count }, async () => {
                        const task = createTask({ status: 'running', stream: true });
                        renderTasks();
                        startTimer(task.id);

                        try {
                            await runWithRequestTimeout(async (signal) => {
                                const response = await fetch(root.dataset.streamUrl, {
                                    method: 'POST',
                                    body: buildPayload(1),
                                    headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'text/event-stream' },
                                    signal,
                                });

                                if (!response.ok || !response.body) {
                                    const errorData = await response.json().catch(() => ({}));
                                    throw new Error(errorData.message || '流式生成失败。');
                                }

                                await readStream(response.body, task.id);
                            });
                        } catch (error) {
                            failTask(task.id, error.message || '流式生成失败。');
                            throw error;
                        }
                    }));

                    setStatus(`流式生成完成，共 ${count} 张。`, 'green');
                };

                const readStream = async (body, taskId) => {
                    const reader = body.getReader();
                    const decoder = new TextDecoder();
                    let buffer = '';

                    while (true) {
                        const { value, done } = await reader.read();
                        if (done) break;

                        buffer += decoder.decode(value, { stream: true });
                        const chunks = buffer.split(/\r?\n\r?\n/);
                        buffer = chunks.pop() ?? '';
                        chunks.forEach((chunk) => handleStreamChunk(chunk, taskId));
                    }

                    if (buffer.trim()) {
                        handleStreamChunk(buffer, taskId);
                    }
                };

                const handleStreamChunk = (chunk, taskId) => {
                    const event = chunk.split(/\r?\n/).find((line) => line.startsWith('event:'))?.slice(6).trim() || 'message';
                    const data = chunk.split(/\r?\n/).filter((line) => line.startsWith('data:')).map((line) => line.slice(5).trim()).join('\n');
                    if (!data) return;

                    const payload = JSON.parse(data);

                    if (event === 'partial') {
                        appendPartial(taskId, payload.images ?? []);
                    } else if (event === 'done') {
                        finishTask(taskId, payload.images ?? [], payload.meta ?? {});
                    } else if (event === 'error') {
                        failTask(taskId, payload.message || '流式生成失败。');
                    }
                };

                const appendChatMessage = async (message, payload) => {
                    const session = currentChat() || createChatSession();
                    const model = selectedChatModel();
                    const reasoning = chatReasoning.value;
                    const reasoningLabel = chatReasoningLabel();
                    const files = chatFileItems.map((item) => ({
                        name: item.file.name,
                        size: item.file.size,
                        type: item.file.type || 'file',
                        url: item.url,
                    }));

                    session.messages.push({
                        role: 'user',
                        content: message,
                        files,
                        model,
                        reasoning,
                        reasoningLabel,
                        createdAt: new Date(),
                    });
                    session.title = chatTitleFromMessage(session.messages.find((item) => item.role === 'user')?.content);
                    session.updatedAt = new Date();
                    renderChatSessions();
                    renderChats();
                    saveChatToServer(session);
                    setStatus('正在请求 AI 回复...');

                    try {
                        const response = await runWithRequestTimeout((signal) => fetch(root.dataset.chatUrl, {
                            method: 'POST',
                            body: payload,
                            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                            signal,
                        }));
                        const data = await response.json().catch(() => ({}));

                        if (!response.ok) throw new Error(data.message || 'AI 聊天请求失败。');

                        session.messages.push({
                            role: 'assistant',
                            content: data.message || '',
                            files: [],
                            model: data.meta?.model || model,
                            reasoning,
                            reasoningLabel,
                            createdAt: new Date(),
                        });
                        session.updatedAt = new Date();
                        clearChatFiles({ revoke: false });
                        renderChatSessions();
                        renderChats();
                        saveChatToServer(session);
                        setStatus('Chat 消息已发送。', 'green');
                    } catch (error) {
                        session.messages.push({
                            role: 'assistant',
                            content: error.message || 'AI 聊天请求失败。',
                            files: [],
                            model,
                            reasoning,
                            reasoningLabel,
                            error: true,
                            createdAt: new Date(),
                        });
                        session.updatedAt = new Date();
                        renderChatSessions();
                        renderChats();
                        saveChatToServer(session);
                        setStatus(error.message || 'AI 聊天请求失败。', 'red');
                    }
                };

                const renderChats = () => {
                    const session = currentChat();
                    chatMessages.innerHTML = '';
                    chatTitle.textContent = session?.title || '新会话';
                    chatMeta.textContent = `${session?.messages.length ?? 0} 条消息`;

                    if (!session || !session.messages.length) {
                        chatMessages.innerHTML = '<div class="rounded-2xl border border-dashed border-zinc-300 bg-white px-6 py-16 text-center text-sm text-zinc-500">暂无消息。</div>';
                        return;
                    }

                    session.messages.forEach((chat) => {
                        const mine = chat.role === 'user';
                        const row = document.createElement('div');
                        row.className = `flex ${mine ? 'justify-end' : 'justify-start'}`;
                        const attachments = (chat.files ?? []).map((file) => `
                            <div class="mt-2 flex items-center gap-2 rounded-2xl ${mine ? 'bg-white/10' : 'bg-zinc-100'} px-2 py-2">
                                ${file.url ? `<img class="h-10 w-10 rounded-xl object-cover" src="${escapeAttribute(file.url)}" alt="">` : '<span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-white/20 text-[10px] font-semibold">FILE</span>'}
                                <span class="min-w-0">
                                    <span class="block truncate text-xs font-medium">${escapeHtml(file.name)}</span>
                                    <span class="block text-[11px] opacity-70">${formatBytes(file.size)}</span>
                                </span>
                            </div>
                        `).join('');
                        row.innerHTML = `
                            <div class="max-w-[78%] rounded-3xl px-4 py-3 text-sm leading-6 shadow-sm ${mine ? 'bg-zinc-950 text-white' : 'border border-zinc-200 bg-white text-zinc-800'}">
                                <div class="whitespace-pre-wrap">${escapeHtml(chat.content)}</div>
                                ${attachments}
                                <div class="mt-2 flex flex-wrap gap-2 text-[11px] ${mine ? 'text-white/55' : 'text-zinc-400'}">
                                    <span>${formatDate(chat.createdAt)}</span>
                                    <span>${escapeHtml(chat.model)}</span>
                                    <span>推理：${escapeHtml(chat.reasoningLabel)}</span>
                                </div>
                            </div>
                        `;
                        chatMessages.appendChild(row);
                    });

                    chatMessages.scrollTop = chatMessages.scrollHeight;
                };

                const createTask = ({ status: taskStatus, stream = false }) => {
                    const id = `task-${Date.now()}-${Math.random().toString(16).slice(2)}`;
                    const config = captureConfig();
                    const task = {
                        id,
                        status: taskStatus,
                        stream,
                        prompt: promptInput.value.trim(),
                        submittedPrompt: composedPrompt(),
                        references: referenceItems.map((reference) => ({
                            file: reference.file,
                            asset: reference.asset || null,
                            maskFile: reference.maskFile,
                            name: reference.file?.name || reference.asset?.name || 'reference.png',
                            url: reference.asset?.url || (reference.file ? URL.createObjectURL(reference.file) : reference.previewUrl),
                            editNote: reference.editNote,
                            hasMask: Boolean(reference.maskFile),
                        })),
                        config,
                        images: [],
                        partials: [],
                        error: '',
                        createdAt: new Date(),
                        elapsedMs: 0,
                        timer: null,
                    };

                    tasks.set(id, task);
                    saveTaskToServer(task);
                    return task;
                };

                const captureConfig = () => ({
                    source: configMode.value === 'default'
                        ? (configName.value.trim() || '榛樿閰嶇疆')
                        : (endpointInput.value ? new URL(endpointInput.value).host : '未设置'),
                    configMode: configMode.value,
                    configName: configName.value.trim() || (configMode.value === 'default' ? '默认配置' : '自定义配置'),
                    model: activeModel(),
                    sizeMode: formValue('size_mode', 'auto'),
                    ratio: formValue('ratio', '1:1'),
                    requestedSize: requestedSizeLabel(),
                    quality: formValue('quality', 'auto'),
                    format: formValue('output_format', 'png'),
                    count: generationCount(),
                    width: formNumber('width', 0),
                    height: formNumber('height', 0),
                    transparent: formValue('transparent', '0') === '1',
                    partialImages: formNumber('partial_images', 0),
                    timeout: formNumber('timeout_seconds', 600),
                });

                const requestedSizeLabel = () => {
                    const mode = formValue('size_mode', 'auto');
                    if (mode === 'auto') return 'auto';
                    if (mode === 'custom') return `${formValue('width', '1024')}x${formValue('height', '1024')}`;
                    return formValue('ratio', '1:1');
                };

                const startTimer = (taskId) => {
                    const task = taskById(taskId);
                    if (!task) return;

                    const startedAt = performance.now();
                    task.timer = window.setInterval(() => {
                        const current = tasks.get(taskId);
                        if (!current) return;
                        current.elapsedMs = performance.now() - startedAt;
                        updateTaskBadge(taskId);
                    }, 250);
                };

                const appendPartial = (taskId, images) => {
                    const task = tasks.get(taskId);
                    if (!task) return;

                    task.partials.push(...images);
                    renderTasks();
                    saveTaskToServer(task);
                };

                const finishTask = (taskId, images, meta) => {
                    const task = tasks.get(taskId);
                    if (!task) return;

                    window.clearInterval(task.timer);
                    task.status = 'done';
                    task.images = images;
                    task.meta = meta;
                    task.elapsedMs = task.elapsedMs || 1;
                    renderTasks();
                    persistTasks();
                    saveTaskToServer(task);
                    enrichImageDimensions(taskId);
                };

                const failTask = (taskId, message) => {
                    const task = tasks.get(taskId);
                    if (!task) return;

                    window.clearInterval(task.timer);
                    task.status = 'failed';
                    task.elapsedMs = task.elapsedMs || 1;
                    task.error = message;
                    renderTasks();
                    persistTasks();
                    saveTaskToServer(task);
                };

                const persistTasks = () => {
                    try {
                        const saved = Array.from(tasks.values())
                            .filter((task) => task.status !== 'running')
                            .sort((a, b) => taskTimestamp(b) - taskTimestamp(a))
                            .slice(0, 80)
                            .map((task) => taskPayload(task));

                        window.localStorage.setItem(taskStorageKey, JSON.stringify(saved));
                    } catch (error) {
                        // Ignore storage limits; the visible in-memory task still remains.
                    }
                };

                const hydrateTasks = () => {
                    try {
                        const saved = JSON.parse(window.localStorage.getItem(taskStorageKey) || '[]');
                        if (!Array.isArray(saved)) return;

                        applyStoredTasks(saved);
                    } catch (error) {
                        window.localStorage.removeItem(taskStorageKey);
                    }
                };

                const applyStoredTasks = (items, targetStore = tasks) => {
                    items.forEach((task) => {
                            if (!task?.id) return;
                            const config = {
                                source: task.config?.source ?? '历史任务',
                                configMode: task.config?.configMode ?? 'default',
                                configName: task.config?.configName ?? '历史任务',
                                model: task.config?.model ?? '默认',
                                sizeMode: task.config?.sizeMode ?? 'auto',
                                ratio: task.config?.ratio ?? 'auto',
                                requestedSize: task.config?.requestedSize ?? 'auto',
                                quality: task.config?.quality ?? 'auto',
                                format: task.config?.format ?? 'png',
                                count: Number(task.config?.count || 1),
                                width: Number(task.config?.width || 0),
                                height: Number(task.config?.height || 0),
                                transparent: Boolean(task.config?.transparent),
                                partialImages: Number(task.config?.partialImages || 0),
                                timeout: Number(task.config?.timeout || 600),
                            };

                            targetStore.set(task.id, {
                                ...task,
                                prompt: task.prompt ?? '',
                                submittedPrompt: task.submittedPrompt ?? task.prompt ?? '',
                                config,
                                status: task.status === 'running' ? 'failed' : task.status,
                                images: Array.isArray(task.images) ? task.images : [],
                                partials: Array.isArray(task.partials) ? task.partials : [],
                                references: Array.isArray(task.references) ? task.references : [],
                                error: task.error || (task.status === 'running' ? '页面刷新后任务已中断。' : ''),
                                createdAt: task.createdAt ? new Date(task.createdAt) : new Date(),
                                elapsedMs: Number(task.elapsedMs || 0),
                                timer: null,
                            });
                        });
                };

                const applyStoredChats = (items, targetStore = chatStore) => {
                    if (!Array.isArray(items)) return;

                    targetStore.clear();
                    items.forEach((session) => {
                        if (!session?.id) return;

                        targetStore.set(session.id, {
                            id: session.id,
                            title: session.title || '新会话',
                            messages: Array.isArray(session.messages) ? session.messages.map((message) => ({
                                ...message,
                                files: Array.isArray(message.files) ? message.files : [],
                                createdAt: message.createdAt ? new Date(message.createdAt) : new Date(),
                            })) : [],
                            createdAt: session.createdAt ? new Date(session.createdAt) : new Date(),
                            updatedAt: session.updatedAt ? new Date(session.updatedAt) : new Date(),
                        });
                    });

                    if (targetStore === chatStore) {
                        activeChatId = chatStore.keys().next().value ?? null;
                    }
                };

                const loadServerState = async () => {
                    if (!root.dataset.stateUrl) return;

                    try {
                        const localTasks = Array.from(tasks.values());
                        const localChats = Array.from(chatStore.values()).filter((session) => session.messages?.length);
                        const data = await serverFetch(root.dataset.stateUrl);
                        const serverTasks = Array.isArray(data.tasks) ? data.tasks : [];
                        const serverTrashedTasks = Array.isArray(data.trashed_tasks) ? data.trashed_tasks : [];
                        const serverChats = Array.isArray(data.chats) ? data.chats : [];
                        const serverTrashedChats = Array.isArray(data.trashed_chats) ? data.trashed_chats : [];

                        if (serverTasks.length) {
                            tasks.clear();
                            applyStoredTasks(serverTasks);
                        } else {
                            localTasks.forEach((task) => saveTaskToServer(task));
                        }

                        if (serverChats.length) {
                            applyStoredChats(serverChats);
                        } else {
                            localChats.forEach((session) => saveChatToServer(session));
                        }

                        trashedTasks.clear();
                        applyStoredTasks(serverTrashedTasks, trashedTasks);
                        applyStoredChats(serverTrashedChats, trashedChatStore);

                        if (!activeChatId) createChatSession();
                        renderTasks();
                        renderChatSessions();
                        renderChats();
                    } catch (error) {
                        // Local cache remains available when the account state endpoint is unreachable.
                    }
                };

                const serializeImages = (images) => (images ?? [])
                    .map((image) => {
                        if (image?.url) return { url: image.url, revised_prompt: image.revised_prompt || '' };
                        if (image?.data_url && image.data_url.length < 1024 * 1024) {
                            return { data_url: image.data_url, revised_prompt: image.revised_prompt || '' };
                        }

                        return null;
                    })
                    .filter(Boolean);

                const taskTimestamp = (task) => {
                    const time = task?.createdAt instanceof Date ? task.createdAt.getTime() : Date.parse(task?.createdAt ?? '');

                    return Number.isFinite(time) ? time : 0;
                };

                const renderTasks = () => {
                    const query = gallerySearch.value.trim().toLowerCase();
                    const selectedStatus = statusFilter.value;
                    const sourceTasks = selectedStatus === 'trash' ? Array.from(trashedTasks.values()) : Array.from(tasks.values());
                    const filtered = sourceTasks
                        .sort((a, b) => taskTimestamp(b) - taskTimestamp(a))
                        .filter((task) => {
                            const matchesStatus = selectedStatus === 'trash' || selectedStatus === 'all' || task.status === selectedStatus;
                            const haystack = `${task.prompt ?? ''} ${task.config?.model ?? ''} ${task.config?.requestedSize ?? ''} ${task.config?.quality ?? ''}`.toLowerCase();

                            return matchesStatus && (!query || haystack.includes(query));
                        });

                    results.innerHTML = '';

                    if (!filtered.length) {
                        results.innerHTML = '<div class="col-span-full rounded-2xl border border-dashed border-zinc-300 bg-white px-6 py-16 text-center text-sm text-zinc-500">暂无匹配任务。</div>';
                        return;
                    }

                    filtered.forEach((task) => {
                        const image = task.images[0] ?? task.partials.at(-1) ?? null;
                        const src = image?.url || image?.data_url || '';
                        const placeholder = task.status === 'failed'
                            ? '<div class="flex h-full min-h-44 flex-col items-center justify-center gap-2 px-4 text-center text-sm text-red-600"><span class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-red-100 text-lg font-bold">!</span><span class="font-semibold">失败</span></div>'
                            : '<div class="flex h-full min-h-44 items-center justify-center px-4 text-center text-sm text-zinc-400">生成中</div>';
                        const article = document.createElement('article');
                        article.className = 'grid min-h-44 overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm transition hover:shadow-md sm:grid-cols-[42%_1fr]';
                        article.dataset.taskId = task.id;
                        article.innerHTML = `
                            <button class="relative min-h-44 bg-zinc-100 text-left" type="button" data-open-task="${escapeAttribute(task.id)}">
                                ${src ? `<img class="h-full w-full object-cover" src="${escapeAttribute(src)}" alt="">` : placeholder}
                                <span class="absolute left-2 top-2 rounded-lg bg-zinc-950/65 px-2 py-1 text-xs font-semibold text-white" data-task-badge>${task.status === 'running' ? formatSeconds(task.elapsedMs) : task.status === 'failed' ? formatSeconds(task.elapsedMs) : taskDimensionLabel(task)}</span>
                                ${task.stream ? '<span class="absolute right-2 top-2 rounded-lg bg-blue-600/85 px-2 py-1 text-xs font-semibold text-white">流式</span>' : ''}
                                ${task.status === 'done' ? `<span class="absolute left-2 top-10 rounded-lg bg-zinc-950/55 px-2 py-1 text-xs font-semibold text-white">${escapeHtml(taskRatioLabel(task))}</span>` : ''}
                            </button>
                            <div class="flex min-w-0 flex-col p-3">
                                <p class="line-clamp-3 text-sm leading-6 text-zinc-700">${escapeHtml(task.prompt)}</p>
                                <div class="mt-3 inline-flex w-fit rounded-xl bg-zinc-100 px-2.5 py-1 text-xs text-zinc-500">&lt;/&gt; ${escapeHtml(task.config.model || '默认')}</div>
                                ${task.error ? `<p class="mt-2 line-clamp-2 text-xs leading-5 text-red-600">${escapeHtml(task.error)}</p>` : ''}
                                <div class="mt-auto flex justify-end gap-1.5 pt-3 text-zinc-400">
                                    <button class="rounded-full p-1.5 hover:bg-zinc-100 hover:text-zinc-700" type="button" title="鏀惰棌" aria-label="鏀惰棌"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m12 3 2.7 5.5 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.8 1-6.1-4.4-4.3 6.1-.9L12 3Z"/></svg></button>
                                    ${selectedStatus === 'trash'
                                        ? `<button class="rounded-full p-1.5 hover:bg-zinc-100 hover:text-emerald-700" type="button" data-restore-task="${escapeAttribute(task.id)}" title="恢复" aria-label="恢复"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 1 0 3-6.7"/><path d="M3 3v6h6"/></svg></button>`
                                        : `<button class="rounded-full p-1.5 hover:bg-zinc-100 hover:text-zinc-700" type="button" data-reuse-task="${escapeAttribute(task.id)}" title="复用提示词和参考图" aria-label="复用提示词和参考图"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 14 4 9l5-5"/><path d="M4 9h10a6 6 0 0 1 0 12h-2"/></svg></button>
                                    ${task.status === 'failed' ? '' : `<button class="rounded-full p-1.5 hover:bg-zinc-100 hover:text-zinc-700" type="button" data-edit-output="${escapeAttribute(task.id)}" title="编辑输出" aria-label="编辑输出"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg></button>`}
                                    <button class="rounded-full p-1.5 hover:bg-zinc-100 hover:text-red-600" type="button" data-delete-task="${escapeAttribute(task.id)}" title="删除" aria-label="删除"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="m19 6-1 14H6L5 6"/></svg></button>`}
                                </div>
                            </div>
                        `;
                        results.appendChild(article);
                    });
                };

                const updateTaskBadge = (taskId) => {
                    const badge = root.querySelector(`[data-task-id="${CSS.escape(taskId)}"] [data-task-badge]`);
                    const task = tasks.get(taskId);
                    if (badge && task) badge.textContent = formatSeconds(task.elapsedMs);
                };

                const enrichImageDimensions = (taskId) => {
                    const task = tasks.get(taskId);
                    const image = task?.images[0];
                    const src = image?.url || image?.data_url;
                    if (!task || !src) return;

                    const probe = new Image();
                    probe.onload = () => {
                        task.actualWidth = probe.naturalWidth;
                        task.actualHeight = probe.naturalHeight;
                        renderTasks();
                        persistTasks();
                        saveTaskToServer(task);
                    };
                    probe.src = src;
                };

                const taskDimensionLabel = (task) => {
                    if (task.actualWidth && task.actualHeight) return `${task.actualWidth}脳${task.actualHeight}`;
                    if (task.config.requestedSize && task.config.requestedSize !== 'auto') return task.config.requestedSize.replace('x', '脳');
                    return task.status === 'failed' ? '失败' : '完成';
                };

                const taskRatioLabel = (task) => {
                    if (task.actualWidth && task.actualHeight) return ratioFromSize(task.actualWidth, task.actualHeight);
                    if (task.config.sizeMode === 'ratio') return task.config.ratio;
                    if (task.config.sizeMode === 'custom') return ratioFromSize(task.config.width, task.config.height);
                    return 'auto';
                };

                const ratioFromSize = (width, height) => {
                    const gcd = (a, b) => b ? gcd(b, a % b) : a;
                    const factor = gcd(width, height) || 1;
                    return `${Math.round(width / factor)}:${Math.round(height / factor)}`;
                };

                const openTask = (taskId) => {
                    const task = tasks.get(taskId);
                    if (!task) return;

                    const image = task.images[0] ?? task.partials.at(-1);
                    const src = image?.url || image?.data_url || '';
                    const detailImage = detailPanel.querySelector('[data-detail-image]');
                    const detailError = detailPanel.querySelector('[data-detail-error]');
                    const detailTime = detailPanel.querySelector('[data-detail-time]');
                    const downloadLink = detailPanel.querySelector('[data-detail-download]');
                    detailTime.textContent = formatSeconds(task.elapsedMs);
                    detailImage.classList.toggle('hidden', task.status === 'failed');
                    detailError.classList.toggle('hidden', task.status !== 'failed');
                    detailError.textContent = task.error || '图片生成失败。';
                    detailImage.src = src || '';
                    detailImage.alt = task.status === 'failed' ? '失败任务' : '生成图片';
                    downloadLink.href = src || '#';
                    downloadLink.download = `ai-image-${task.id}.png`;
                    downloadLink.classList.toggle('pointer-events-none', !src);
                    downloadLink.classList.toggle('opacity-40', !src);
                    detailPanel.querySelector('[data-detail-prompt]').textContent = task.prompt;

                    const referencesSection = detailPanel.querySelector('[data-detail-references-section]');
                    const references = detailPanel.querySelector('[data-detail-references]');
                    references.innerHTML = '';
                    referencesSection.classList.toggle('hidden', task.references.length === 0 || task.status === 'failed');
                    task.references.forEach((reference) => {
                        references.insertAdjacentHTML('beforeend', `<img class="aspect-square rounded-lg object-cover" src="${escapeAttribute(reference.url)}" alt="${escapeAttribute(reference.name)}">`);
                    });

                    const meta = detailPanel.querySelector('[data-detail-meta]');
                    const actualSize = task.actualWidth && task.actualHeight ? `${task.actualWidth}x${task.actualHeight}` : '未知';
                    const actualRatio = task.actualWidth && task.actualHeight ? ratioFromSize(task.actualWidth, task.actualHeight) : '未知';
                    const rows = [
                        ['来源', task.config.source],
                        ['尺寸', `${task.config.requestedSize}${task.config.requestedSize === 'auto' ? ` | ${actualSize}` : ''}`],
                        ['宽高比', `${task.config.sizeMode === 'auto' ? 'auto | ' : ''}${actualRatio}`],
                        ['质量', `${task.config.quality}${task.config.quality === 'auto' ? ' | 服务商自动' : ''}`],
                        ['格式', task.config.format],
                        ['数量', String(task.config.count)],
                        ['创建时间', formatDate(task.createdAt)],
                        ['生成用时', formatSeconds(task.elapsedMs)],
                    ];
                    meta.innerHTML = rows.map(([label, value]) => `<div class="grid grid-cols-[6rem_1fr] gap-3 px-4 py-3"><dt class="text-zinc-500">${escapeHtml(label)}</dt><dd class="min-w-0 break-words text-zinc-900">${escapeHtml(value)}</dd></div>`).join('');

                    detailPanel.classList.remove('hidden');
                };

                root.addEventListener('click', (event) => {
                    const openButton = event.target.closest('[data-open-task]');
                    const reuseButton = event.target.closest('[data-reuse-task]');
                    const editOutputButton = event.target.closest('[data-edit-output]');
                    const deleteButton = event.target.closest('[data-delete-task]');
                    const restoreButton = event.target.closest('[data-restore-task]');

                    if (openButton) {
                        openTask(openButton.dataset.openTask);
                    } else if (reuseButton) {
                        const task = taskById(reuseButton.dataset.reuseTask);
                        if (task) {
                            resetReferences();
                            promptInput.value = task.prompt;
                            cloneTaskReferences(task)
                                .then((references) => {
                                    referenceItems = references.slice(0, 6);
                                    renderReferencePreview();
                                    autoGrowPrompt();
                                    setStatus('已复用提示词和参考图。', 'green');
                                })
                                .catch(() => setStatus('参考图复用失败。', 'red'));
                        }
                    } else if (editOutputButton) {
                        const task = taskById(editOutputButton.dataset.editOutput);
                        const image = task?.images[0] ?? task?.partials.at(-1);
                        if (!task || !image) return;

                        imageToReference(image, task)
                            .then((reference) => {
                                if (!reference) return;
                                resetReferences();
                                referenceItems = [reference];
                                renderReferencePreview();
                                uploadReferenceAsset(reference);
                                setStatus('已将输出图添加为参考图。', 'green');
                            })
                            .catch(() => setStatus('输出图无法添加为参考图。', 'red'));
                    } else if (deleteButton) {
                        const deletingId = deleteButton.dataset.deleteTask;
                        const deletedTask = tasks.get(deletingId);

                        if (!deletedTask) return;

                        deletedTask.trashed = true;
                        deletedTask.deletedAt = new Date();
                        tasks.delete(deletingId);
                        trashedTasks.set(deletingId, deletedTask);
                        persistTasks();
                        renderTasks();
                        setStatus('任务已移入回收站。', 'green');
                        deleteTaskFromServer(deletingId)
                            .catch((error) => {
                                trashedTasks.delete(deletingId);
                                tasks.set(deletingId, deletedTask);
                                persistTasks();
                                renderTasks();
                                setStatus(error.message || '任务删除失败。', 'red');
                            });
                    } else if (restoreButton) {
                        restoreTaskFromServer(restoreButton.dataset.restoreTask)
                            .then((task) => {
                                if (!task) return;
                                trashedTasks.delete(restoreButton.dataset.restoreTask);
                                applyStoredTasks([task], tasks);
                                persistTasks();
                                renderTasks();
                                setStatus('任务已恢复。', 'green');
                            })
                            .catch((error) => setStatus(error.message || '任务恢复失败。', 'red'));
                    }
                });

                detailPanel.querySelector('[data-detail-close]')?.addEventListener('click', () => detailPanel.classList.add('hidden'));
                gallerySearch.addEventListener('input', renderTasks);
                statusFilter.addEventListener('change', renderTasks);

                root.querySelector('[data-download-latest]')?.addEventListener('click', () => {
                    const task = Array.from(tasks.values()).findLast((item) => item.images.length || item.partials.length);
                    const image = task?.images[0] ?? task?.partials.at(-1);
                    const src = image?.url || image?.data_url;
                    if (!src) {
                        setStatus('当前没有可下载的图片。', 'red');
                        return;
                    }

                    const link = document.createElement('a');
                    link.href = src;
                    link.download = `ai-image-${task.id}.png`;
                    link.click();
                });

                const formatSeconds = (milliseconds) => {
                    const totalSeconds = Math.max(0, Math.floor(milliseconds / 1000));

                    if (totalSeconds >= 60) {
                        const minutes = Math.floor(totalSeconds / 60);
                        const seconds = String(totalSeconds % 60).padStart(2, '0');

                        return `${minutes}:${seconds}`;
                    }

                    return `${totalSeconds}秒`;
                };
                const formatDate = (date) => new Intl.DateTimeFormat('zh-CN', { dateStyle: 'medium', timeStyle: 'short' }).format(date);
                const formatBytes = (bytes) => {
                    const size = Number(bytes || 0);
                    if (size >= 1024 * 1024) return `${(size / 1024 / 1024).toFixed(size >= 10 * 1024 * 1024 ? 0 : 1)} MB`;
                    if (size >= 1024) return `${(size / 1024).toFixed(size >= 10 * 1024 ? 0 : 1)} KB`;

                    return `${size} B`;
                };

                const escapeHtml = (value) => String(value)
                    .replaceAll('&', '&amp;')
                    .replaceAll('<', '&lt;')
                    .replaceAll('>', '&gt;')
                    .replaceAll('"', '&quot;')
                    .replaceAll("'", '&#039;');

                const escapeAttribute = (value) => escapeHtml(value).replaceAll('`', '&#096;');

                syncChatModelOptions();
                loadSavedConfigs();
                hydrateTasks();
                if (!activeChatId) createChatSession();
                setMode('gallery');
                renderTasks();
                loadServerState();
            })();
        </script>
    </section>
</x-layouts.app>
