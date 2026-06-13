<x-layouts.app title="AI 生图" :wide="true">
    <section
        class="relative -mx-4 -my-4 min-h-[calc(100vh-160px)] overflow-hidden bg-[#f7f7f8] text-zinc-900"
        data-ai-image-workbench
        data-models-url="{{ \App\Support\Url::route('ai-image.models') }}"
        data-generate-url="{{ \App\Support\Url::route('ai-image.generate') }}"
        data-stream-url="{{ \App\Support\Url::route('ai-image.stream') }}"
    >
        <form class="contents" data-ai-image-form>
            @csrf

            <header class="sticky top-0 z-30 border-b border-zinc-200 bg-white/95 px-5 py-3 backdrop-blur">
                <div class="flex items-center justify-between gap-4">
                    <a class="min-w-0 truncate text-xl font-semibold tracking-normal text-zinc-950" href="{{ \App\Support\Url::route('home') }}">{{ $settings?->site_name ?? $siteSettings?->site_name ?? config('app.name', 'ShopWeb') }}</a>
                    <div class="flex items-center gap-2">
                        <button class="rounded-full border border-zinc-200 bg-zinc-100 px-5 py-2 text-sm font-semibold text-zinc-950 shadow-inner" type="button">画廊</button>
                        <button class="hidden rounded-full px-5 py-2 text-sm text-zinc-500 md:inline-flex" type="button">Agent</button>
                        <button class="inline-flex h-10 w-10 items-center justify-center rounded-full text-zinc-600 hover:bg-zinc-100" type="button" data-download-latest title="下载最近图片" aria-label="下载最近图片">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true" stroke="currentColor" stroke-width="2"><path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M5 21h14"/></svg>
                        </button>
                        <button class="inline-flex h-10 w-10 items-center justify-center rounded-full text-zinc-600 hover:bg-zinc-100" type="button" data-help-toggle title="提示" aria-label="提示">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.2 9a3 3 0 1 1 4.6 2.5c-.9.5-1.8 1.2-1.8 2.5"/><path d="M12 18h.01"/></svg>
                        </button>
                        <button class="inline-flex h-10 w-10 items-center justify-center rounded-full text-zinc-600 hover:bg-zinc-100" type="button" data-settings-toggle title="设置" aria-label="设置">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true" stroke="currentColor" stroke-width="2"><path d="M12 15.5A3.5 3.5 0 1 0 12 8a3.5 3.5 0 0 0 0 7.5Z"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1-1.5 1.7 1.7 0 0 0-1.9.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1 1.7 1.7 0 0 0-.3-1.9l-.1-.1A2 2 0 1 1 7 4.2l.1.1A1.7 1.7 0 0 0 9 4.6a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.9-.3l.1-.1A2 2 0 1 1 19.8 7l-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.5 1h.1a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1Z"/></svg>
                        </button>
                    </div>
                </div>
            </header>

            <div class="px-5 pb-44 pt-5">
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
                    </select>

                    <label class="relative block">
                        <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-zinc-400">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                        </span>
                        <input class="h-12 w-full rounded-lg border border-zinc-200 bg-white pl-12 pr-4 text-sm text-zinc-900 shadow-sm outline-none placeholder:text-zinc-400 focus:border-zinc-300" type="search" placeholder="搜索提示词、参数..." data-gallery-search>
                    </label>
                </div>

                <div class="grid gap-4 md:grid-cols-2 2xl:grid-cols-3" data-ai-results>
                    <div class="col-span-full rounded-lg border border-dashed border-zinc-300 bg-white px-6 py-16 text-center text-sm text-zinc-500">
                        从底部输入提示词开始生成，完成后的图片会出现在这里。
                    </div>
                </div>
            </div>

            <div class="pointer-events-none fixed inset-x-0 bottom-5 z-40 px-4">
                <div class="pointer-events-auto mx-auto max-w-5xl rounded-lg border border-zinc-200 bg-white/95 p-4 shadow-2xl shadow-zinc-950/10 backdrop-blur">
                    <div class="mb-3 hidden max-h-20 items-center gap-3 overflow-x-auto pb-1 flex" data-reference-preview></div>

                    <textarea
                        class="max-h-44 min-h-14 w-full resize-none rounded-lg border border-zinc-200 bg-white px-5 py-4 text-sm leading-6 text-zinc-900 outline-none placeholder:text-zinc-400 focus:border-zinc-300"
                        name="prompt"
                        placeholder="描述你想生成的图片，可输入 @ 来指定参考图..."
                        data-ai-prompt
                        required
                    ></textarea>

                    <div class="mt-3 flex flex-wrap items-end gap-3">
                        <input class="hidden" type="file" name="reference_images[]" accept="image/png,image/jpeg,image/gif,image/webp" multiple data-reference-input>

                        <button class="inline-flex h-12 w-12 items-center justify-center rounded-lg bg-zinc-100 text-zinc-600 hover:bg-zinc-200" type="button" data-reference-button title="添加参考图" aria-label="添加参考图">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true" stroke="currentColor" stroke-width="2"><path d="M21.4 11.6 12 21a6 6 0 0 1-8.5-8.5l10-10a4 4 0 1 1 5.7 5.7l-10 10a2 2 0 0 1-2.8-2.8l9.3-9.3"/></svg>
                        </button>

                        <label class="min-w-28 flex-1 text-xs font-medium text-zinc-400 sm:flex-none">
                            尺寸
                            <select class="mt-1 h-10 w-full rounded-lg border border-zinc-200 bg-white px-4 text-sm font-medium text-zinc-900" name="size_mode" data-size-mode>
                                <option value="auto">auto</option>
                                <option value="ratio">按比例</option>
                                <option value="custom">自定义宽高</option>
                            </select>
                        </label>

                        <label class="hidden min-w-28 flex-1 text-xs font-medium text-zinc-400 sm:flex-none" data-ratio-panel>
                            比例
                            <select class="mt-1 h-10 w-full rounded-lg border border-zinc-200 bg-white px-4 text-sm font-medium text-zinc-900" name="ratio">
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
                                <input class="mt-1 h-10 w-full rounded-lg border border-zinc-200 bg-white px-3 text-sm font-medium text-zinc-900" name="width" type="number" min="256" max="4096" step="64" value="1024">
                            </label>
                            <label class="text-xs font-medium text-zinc-400">
                                高
                                <input class="mt-1 h-10 w-full rounded-lg border border-zinc-200 bg-white px-3 text-sm font-medium text-zinc-900" name="height" type="number" min="256" max="4096" step="64" value="1024">
                            </label>
                        </div>

                        <label class="min-w-28 flex-1 text-xs font-medium text-zinc-400 sm:flex-none">
                            质量
                            <select class="mt-1 h-10 w-full rounded-lg border border-zinc-200 bg-white px-4 text-sm font-medium text-zinc-900" name="quality">
                                <option value="auto">auto</option>
                                <option value="low">low</option>
                                <option value="medium">medium</option>
                                <option value="high">high</option>
                            </select>
                        </label>

                        <label class="min-w-28 flex-1 text-xs font-medium text-zinc-400 sm:flex-none">
                            格式
                            <select class="mt-1 h-10 w-full rounded-lg border border-zinc-200 bg-white px-4 text-sm font-medium text-zinc-900" name="output_format">
                                <option value="png">PNG</option>
                                <option value="jpeg">JPEG</option>
                                <option value="webp">WebP</option>
                                <option value="auto">自动</option>
                            </select>
                        </label>

                        <label class="min-w-28 flex-1 text-xs font-medium text-zinc-400 sm:flex-none">
                            是否透明
                            <select class="mt-1 h-10 w-full rounded-lg border border-zinc-200 bg-white px-4 text-sm font-medium text-zinc-900" name="transparent">
                                <option value="0">否</option>
                                <option value="1">是</option>
                            </select>
                        </label>

                        <label class="min-w-28 flex-1 text-xs font-medium text-zinc-400 sm:flex-none">
                            数量
                            <input class="mt-1 h-10 w-full rounded-lg border border-zinc-200 bg-white px-4 text-sm font-medium text-zinc-900" name="count" type="number" min="1" max="8" value="1">
                        </label>

                        <button class="ml-auto inline-flex h-12 w-12 items-center justify-center rounded-lg bg-blue-700 text-white hover:bg-blue-800 disabled:cursor-not-allowed disabled:bg-zinc-200" type="submit" data-generate-button title="生成" aria-label="生成">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true" stroke="currentColor" stroke-width="2"><path d="M5 12h14"/><path d="m13 6 6 6-6 6"/></svg>
                        </button>
                    </div>

                    <p class="mt-3 text-xs text-zinc-500" data-ai-status>等待填写提示词。</p>
                </div>
            </div>

            <aside class="fixed inset-y-0 right-0 z-50 w-full max-w-md translate-x-full border-l border-zinc-200 bg-white shadow-2xl transition-transform duration-200" data-settings-panel>
                <div class="flex items-center justify-between border-b border-zinc-200 px-5 py-4">
                    <h2 class="text-base font-semibold">生成设置</h2>
                    <button class="inline-flex h-9 w-9 items-center justify-center rounded-full text-zinc-500 hover:bg-zinc-100" type="button" data-settings-close aria-label="关闭设置">
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
                            <span class="font-medium text-zinc-700">API URL</span>
                            <input class="mt-1 w-full rounded-lg border border-zinc-200 px-4 py-3 text-sm outline-none focus:border-zinc-400" name="endpoint" type="url" placeholder="https://api.openai.com/v1" data-ai-endpoint required>
                        </label>

                        <label class="block text-sm">
                            <span class="font-medium text-zinc-700">API Key</span>
                            <input class="mt-1 w-full rounded-lg border border-zinc-200 px-4 py-3 text-sm outline-none focus:border-zinc-400" name="api_key" type="password" autocomplete="off" placeholder="sk-..." data-ai-key>
                        </label>

                        <label class="block text-sm">
                            <span class="font-medium text-zinc-700">图片模型</span>
                            <select class="mt-1 w-full rounded-lg border border-zinc-200 px-4 py-3 text-sm outline-none focus:border-zinc-400" name="model" data-ai-model-select required>
                                <option value="">先获取模型或手动填写</option>
                            </select>
                        </label>

                        <label class="block text-sm">
                            <span class="font-medium text-zinc-700">手动模型</span>
                            <input class="mt-1 w-full rounded-lg border border-zinc-200 px-4 py-3 text-sm outline-none focus:border-zinc-400" name="manual_model" type="text" placeholder="例如 gpt-image-1" data-ai-manual-model>
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
                                <p class="mt-1 text-xs leading-5 text-zinc-500">开启后请求以流式传输，并非所有服务商和网关都支持。官方接口在流式模式下不发送心跳，数量大于 1 时会拆分为并发单图。</p>
                            </div>
                            <label class="relative inline-flex cursor-pointer items-center">
                                <input class="peer sr-only" type="checkbox" name="stream" value="1" data-stream-toggle>
                                <span class="h-6 w-11 rounded-full bg-zinc-200 after:absolute after:left-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow after:transition peer-checked:bg-zinc-900 peer-checked:after:translate-x-5"></span>
                            </label>
                        </div>

                        <label class="block text-sm">
                            <span class="font-medium text-zinc-700">请求中间步骤图像数</span>
                            <input class="mt-1 w-full rounded-lg border border-zinc-200 px-4 py-3 text-sm" name="partial_images" type="number" min="0" max="3" value="2">
                            <span class="mt-1 block text-xs leading-5 text-zinc-500">对应 partial_images 参数 0-3。建议设为 2 或 3，以减少长时间无数据导致的断开风险。</span>
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
                <div class="flex min-h-0 items-center justify-center bg-zinc-950 p-4">
                    <img class="max-h-full max-w-full rounded-lg object-contain" src="" alt="" data-detail-image>
                </div>
                <div class="min-h-0 overflow-y-auto border-l border-zinc-200 bg-white">
                    <div class="sticky top-0 z-10 flex items-center justify-between border-b border-zinc-200 bg-white px-5 py-4">
                        <h2 class="text-base font-semibold">图像任务详情</h2>
                        <div class="flex items-center gap-2">
                            <a class="rounded-full bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-800" href="#" download data-detail-download>下载</a>
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
            接口设置在右上角齿轮里。开启流式后，生成中会显示中间预览；失败时仍可点击任务下载已返回的中间图。
        </div>

        <script>
            (() => {
                const root = document.querySelector('[data-ai-image-workbench]');
                if (!root) return;

                const form = root.querySelector('[data-ai-image-form]');
                const status = root.querySelector('[data-ai-status]');
                const endpointInput = root.querySelector('[data-ai-endpoint]');
                const apiKeyInput = root.querySelector('[data-ai-key]');
                const modelSelect = root.querySelector('[data-ai-model-select]');
                const manualModel = root.querySelector('[data-ai-manual-model]');
                const promptInput = root.querySelector('[data-ai-prompt]');
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
                const csrf = form.querySelector('input[name="_token"]')?.value ?? document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

                const tasks = new Map();
                let referenceItems = [];
                let activeReferenceIndex = null;
                let modelFetchTimer = null;

                const setStatus = (message, tone = 'zinc') => {
                    status.textContent = message;
                    status.className = `mt-3 text-xs ${tone === 'red' ? 'text-red-600' : tone === 'green' ? 'text-emerald-600' : 'text-zinc-500'}`;
                };

                const activeModel = () => manualModel.value.trim() || modelSelect.value;

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

                const autoGrowPrompt = () => {
                    promptInput.style.height = 'auto';
                    promptInput.style.height = `${Math.min(promptInput.scrollHeight, 176)}px`;
                };

                promptInput.addEventListener('input', autoGrowPrompt);
                autoGrowPrompt();

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
                            editNote: '',
                            maskFile: null,
                            previewUrl: URL.createObjectURL(file),
                        }));

                    referenceItems = [...referenceItems, ...incomingItems].slice(0, 6);
                    referenceInput.value = '';
                    renderReferencePreview();
                });

                const renderReferencePreview = () => {
                    referencePreview.innerHTML = '';
                    referencePreview.classList.toggle('hidden', referenceItems.length === 0);

                    referenceItems.forEach((reference, index) => {
                        const item = document.createElement('div');
                        item.className = 'group relative h-16 w-16 shrink-0 overflow-hidden rounded-lg border border-zinc-200 bg-white shadow-sm';
                        const url = reference.previewUrl;
                        item.innerHTML = `
                            <img class="aspect-square w-full object-cover" src="${escapeAttribute(url)}" alt="">
                            <span class="absolute right-1 top-1 hidden rounded-full bg-zinc-950/70 px-2 py-1 text-[10px] text-white group-hover:block">移除</span>
                        `;
                        item.addEventListener('click', () => {
                            removeReference(index);
                            referenceInput.value = '';
                            renderReferencePreview();
                        });
                        item.querySelector('img')?.addEventListener('load', () => {}, { once: true });
                        item.innerHTML = `
                            <button class="block h-full w-full" type="button" data-edit-reference="${index}" aria-label="编辑参考图">
                                <img class="h-full w-full object-cover" src="${escapeAttribute(reference.previewUrl)}" alt="">
                                <span class="absolute bottom-1 left-1 rounded-full bg-zinc-950/75 px-1.5 py-0.5 text-[10px] font-semibold text-white">${index + 1}</span>
                                ${reference.maskFile ? '<span class="absolute bottom-1 right-1 rounded-full bg-emerald-600 px-1.5 py-0.5 text-[10px] font-semibold text-white">遮罩</span>' : ''}
                                ${reference.editNote ? '<span class="absolute inset-x-0 bottom-0 h-1 bg-blue-500"></span>' : ''}
                            </button>
                            <button class="absolute right-1 top-1 hidden h-5 w-5 items-center justify-center rounded-full bg-zinc-950/75 text-xs font-bold leading-none text-white group-hover:flex" type="button" data-remove-reference="${index}" aria-label="删除参考图">×</button>
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

                    URL.revokeObjectURL(item.previewUrl);
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
                    if (!endpointInput.value.trim()) {
                        setStatus('请先在右上角设置里填写 API URL。', 'red');
                        openSettings();
                        return;
                    }

                    const payload = new FormData();
                    payload.append('endpoint', endpointInput.value);
                    payload.append('api_key', apiKeyInput.value);
                    setStatus('正在获取可用图片模型...');

                    try {
                        const response = await fetch(root.dataset.modelsUrl, {
                            method: 'POST',
                            body: payload,
                            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                        });
                        const data = await response.json();

                        if (!response.ok) throw new Error(data.message || '模型列表获取失败。');

                        modelSelect.innerHTML = '';
                        if (!data.models?.length) {
                            modelSelect.insertAdjacentHTML('beforeend', '<option value="">没有识别到模型，请手动填写</option>');
                        } else {
                            data.models.forEach((model) => {
                                const option = document.createElement('option');
                                option.value = model.id;
                                option.textContent = model.name || model.id;
                                modelSelect.appendChild(option);
                            });
                        }

                        setStatus(`已获取 ${data.models?.length ?? 0} 个模型。`, 'green');
                    } catch (error) {
                        setStatus(error.message || '模型列表获取失败。', 'red');
                    }
                };

                const scheduleModelFetch = () => {
                    window.clearTimeout(modelFetchTimer);
                    if (!endpointInput.value.trim()) return;
                    modelFetchTimer = window.setTimeout(fetchModels, 700);
                };

                root.querySelector('[data-fetch-models]')?.addEventListener('click', fetchModels);
                endpointInput.addEventListener('change', fetchModels);
                endpointInput.addEventListener('blur', fetchModels);
                apiKeyInput.addEventListener('change', scheduleModelFetch);
                apiKeyInput.addEventListener('blur', scheduleModelFetch);

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
                    payload.delete('manual_model');
                    payload.delete('reference_images[]');
                    payload.delete('mask_image');

                    referenceItems.forEach((reference) => payload.append('reference_images[]', reference.file));

                    const maskReference = referenceItems.find((reference) => reference.maskFile);
                    if (maskReference?.maskFile) {
                        payload.set('mask_image', maskReference.maskFile);
                    }

                    if (countOverride !== null) {
                        payload.set('count', String(countOverride));
                    }

                    return payload;
                };

                form.addEventListener('submit', async (event) => {
                    event.preventDefault();

                    if (!promptInput.value.trim()) {
                        setStatus('请先填写提示词。', 'red');
                        return;
                    }

                    try {
                        const count = Math.max(1, Math.min(8, Number(form.count.value || 1)));
                        const stream = form.stream?.checked ?? false;
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
                });

                const generateNormal = async () => {
                    const count = Math.max(1, Math.min(8, Number(form.count.value || 1)));
                    const placeholders = Array.from({ length: count }, () => createTask({ status: 'running' }));
                    renderTasks();
                    placeholders.forEach((task) => startTimer(task.id));
                    setStatus('正在生成图片，请稍候...');

                    try {
                        const response = await fetch(root.dataset.generateUrl, {
                            method: 'POST',
                            body: buildPayload(),
                            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                        });
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
                            const response = await fetch(root.dataset.streamUrl, {
                                method: 'POST',
                                body: buildPayload(1),
                                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'text/event-stream' },
                            });

                            if (!response.ok || !response.body) {
                                const errorData = await response.json().catch(() => ({}));
                                throw new Error(errorData.message || '流式生成失败。');
                            }

                            await readStream(response.body, task.id);
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

                const createTask = ({ status: taskStatus, stream = false }) => {
                    const id = `task-${Date.now()}-${Math.random().toString(16).slice(2)}`;
                    const config = captureConfig();
                    const task = {
                        id,
                        status: taskStatus,
                        stream,
                        prompt: composedPrompt(),
                        references: referenceItems.map((reference) => ({
                            name: reference.file.name,
                            url: URL.createObjectURL(reference.file),
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
                    return task;
                };

                const captureConfig = () => ({
                    source: endpointInput.value ? new URL(endpointInput.value).host : '未设置',
                    model: activeModel(),
                    sizeMode: form.size_mode.value,
                    ratio: form.ratio.value,
                    requestedSize: requestedSizeLabel(),
                    quality: form.quality.value,
                    format: form.output_format.value,
                    count: Number(form.count.value || 1),
                    width: Number(form.width.value || 0),
                    height: Number(form.height.value || 0),
                    transparent: form.transparent.value === '1',
                    partialImages: Number(form.partial_images.value || 0),
                    timeout: Number(form.timeout_seconds.value || 600),
                });

                const requestedSizeLabel = () => {
                    if (form.size_mode.value === 'auto') return 'auto';
                    if (form.size_mode.value === 'custom') return `${form.width.value}x${form.height.value}`;
                    return form.ratio.value;
                };

                const startTimer = (taskId) => {
                    const task = tasks.get(taskId);
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
                    enrichImageDimensions(taskId);
                };

                const failTask = (taskId, message) => {
                    const task = tasks.get(taskId);
                    if (!task) return;

                    window.clearInterval(task.timer);
                    task.status = 'failed';
                    task.error = message;
                    renderTasks();
                };

                const renderTasks = () => {
                    const query = gallerySearch.value.trim().toLowerCase();
                    const selectedStatus = statusFilter.value;
                    const filtered = Array.from(tasks.values()).filter((task) => {
                        const matchesStatus = selectedStatus === 'all' || task.status === selectedStatus;
                        const haystack = `${task.prompt} ${task.config.model} ${task.config.requestedSize} ${task.config.quality}`.toLowerCase();

                        return matchesStatus && (!query || haystack.includes(query));
                    });

                    results.innerHTML = '';

                    if (!filtered.length) {
                        results.innerHTML = '<div class="col-span-full rounded-lg border border-dashed border-zinc-300 bg-white px-6 py-16 text-center text-sm text-zinc-500">暂无匹配任务。</div>';
                        return;
                    }

                    filtered.forEach((task) => {
                        const image = task.images[0] ?? task.partials.at(-1) ?? null;
                        const src = image?.url || image?.data_url || '';
                        const article = document.createElement('article');
                        article.className = 'grid min-h-48 overflow-hidden rounded-lg border border-zinc-200 bg-white shadow-sm transition hover:shadow-md sm:grid-cols-[40%_1fr]';
                        article.dataset.taskId = task.id;
                        article.innerHTML = `
                            <button class="relative min-h-48 bg-zinc-100 text-left" type="button" data-open-task="${escapeAttribute(task.id)}">
                                ${src ? `<img class="h-full w-full object-cover" src="${escapeAttribute(src)}" alt="">` : '<div class="flex h-full min-h-48 items-center justify-center text-sm text-zinc-400">生成中</div>'}
                                <span class="absolute left-2 top-2 rounded-md bg-zinc-950/65 px-2 py-1 text-xs font-semibold text-white" data-task-badge>${task.status === 'running' ? formatSeconds(task.elapsedMs) : taskDimensionLabel(task)}</span>
                                <span class="absolute left-2 top-10 rounded-md bg-zinc-950/55 px-2 py-1 text-xs font-semibold text-white">${escapeHtml(taskRatioLabel(task))}</span>
                            </button>
                            <div class="flex min-w-0 flex-col p-4">
                                <p class="line-clamp-3 text-base leading-7 text-zinc-700">${escapeHtml(task.prompt)}</p>
                                <div class="mt-4 inline-flex w-fit rounded-lg bg-zinc-100 px-2.5 py-1 text-xs text-zinc-500">&lt;/&gt; ${escapeHtml(task.config.model || '默认')}</div>
                                ${task.error ? `<p class="mt-3 text-xs leading-5 text-red-600">${escapeHtml(task.error)}</p>` : ''}
                                <div class="mt-auto flex justify-end gap-2 pt-4 text-zinc-400">
                                    <button class="rounded-full p-1.5 hover:bg-zinc-100 hover:text-zinc-700" type="button" title="收藏" aria-label="收藏"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m12 3 2.7 5.5 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.8 1-6.1-4.4-4.3 6.1-.9L12 3Z"/></svg></button>
                                    <button class="rounded-full p-1.5 hover:bg-zinc-100 hover:text-zinc-700" type="button" data-reuse-task="${escapeAttribute(task.id)}" title="复用提示词" aria-label="复用提示词"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 14 4 9l5-5"/><path d="M4 9h10a6 6 0 0 1 0 12h-2"/></svg></button>
                                    <button class="rounded-full p-1.5 hover:bg-zinc-100 hover:text-zinc-700" type="button" data-open-task="${escapeAttribute(task.id)}" title="编辑详情" aria-label="编辑详情"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg></button>
                                    <button class="rounded-full p-1.5 hover:bg-zinc-100 hover:text-red-600" type="button" data-delete-task="${escapeAttribute(task.id)}" title="删除" aria-label="删除"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="m19 6-1 14H6L5 6"/></svg></button>
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
                    };
                    probe.src = src;
                };

                const taskDimensionLabel = (task) => {
                    if (task.actualWidth && task.actualHeight) return `${task.actualWidth}×${task.actualHeight}`;
                    if (task.config.requestedSize && task.config.requestedSize !== 'auto') return task.config.requestedSize.replace('x', '×');
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
                    const downloadLink = detailPanel.querySelector('[data-detail-download]');
                    detailImage.src = src;
                    detailImage.alt = task.status === 'failed' ? '失败任务的中间步骤图' : '生成图片';
                    downloadLink.href = src || '#';
                    downloadLink.download = `ai-image-${task.id}.png`;
                    downloadLink.classList.toggle('pointer-events-none', !src);
                    downloadLink.classList.toggle('opacity-40', !src);
                    detailPanel.querySelector('[data-detail-prompt]').textContent = task.prompt;

                    const referencesSection = detailPanel.querySelector('[data-detail-references-section]');
                    const references = detailPanel.querySelector('[data-detail-references]');
                    references.innerHTML = '';
                    referencesSection.classList.toggle('hidden', task.references.length === 0);
                    task.references.forEach((reference) => {
                        references.insertAdjacentHTML('beforeend', `<img class="aspect-square rounded-lg object-cover" src="${escapeAttribute(reference.url)}" alt="${escapeAttribute(reference.name)}">`);
                    });

                    const meta = detailPanel.querySelector('[data-detail-meta]');
                    const actualSize = task.actualWidth && task.actualHeight ? `${task.actualWidth}×${task.actualHeight}` : '未知';
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
                    const deleteButton = event.target.closest('[data-delete-task]');

                    if (openButton) {
                        openTask(openButton.dataset.openTask);
                    } else if (reuseButton) {
                        const task = tasks.get(reuseButton.dataset.reuseTask);
                        if (task) {
                            promptInput.value = task.prompt;
                            autoGrowPrompt();
                            setStatus('已复用提示词。', 'green');
                        }
                    } else if (deleteButton) {
                        tasks.delete(deleteButton.dataset.deleteTask);
                        renderTasks();
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

                const formatSeconds = (milliseconds) => `${Math.max(0, milliseconds / 1000).toFixed(1)}s`;
                const formatDate = (date) => new Intl.DateTimeFormat('zh-CN', { dateStyle: 'medium', timeStyle: 'short' }).format(date);

                const escapeHtml = (value) => String(value)
                    .replaceAll('&', '&amp;')
                    .replaceAll('<', '&lt;')
                    .replaceAll('>', '&gt;')
                    .replaceAll('"', '&quot;')
                    .replaceAll("'", '&#039;');

                const escapeAttribute = (value) => escapeHtml(value).replaceAll('`', '&#096;');
            })();
        </script>
    </section>
</x-layouts.app>
