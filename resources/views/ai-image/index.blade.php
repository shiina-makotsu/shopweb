<x-layouts.app title="AI 生图" :wide="true">
    <section
        class="rounded-sm border border-slate-300 bg-white"
        data-ai-image-workbench
        data-models-url="{{ route('ai-image.models') }}"
        data-generate-url="{{ route('ai-image.generate') }}"
    >
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-slate-100 px-4 py-3">
            <div>
                <h1 class="text-lg font-semibold">AI 生图</h1>
                <p class="mt-1 text-sm text-slate-600">填写兼容 OpenAI 图片接口的 URL 和 Key，选择模型后用提示词、参考图和尺寸参数生成图片。</p>
            </div>
            <a class="rounded-sm border border-slate-300 bg-white px-3 py-2 text-sm text-slate-700 hover:bg-blue-50 hover:text-blue-800" href="{{ route('home') }}">回到首页</a>
        </div>

        <div class="grid gap-px bg-slate-200 lg:grid-cols-[380px_1fr]">
            <form class="space-y-4 bg-white p-4" data-ai-image-form>
                @csrf

                <section class="space-y-3">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-base font-semibold">接口连接</h2>
                        <button class="rounded-sm border border-blue-700 bg-blue-700 px-3 py-2 text-xs font-medium text-white hover:bg-blue-800" type="button" data-fetch-models>
                            获取模型
                        </button>
                    </div>

                    <label class="block text-sm">
                        <span class="font-medium text-slate-700">API URL</span>
                        <input class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2 text-sm" name="endpoint" type="url" placeholder="https://api.openai.com/v1" data-ai-endpoint required>
                    </label>

                    <label class="block text-sm">
                        <span class="font-medium text-slate-700">API Key</span>
                        <input class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2 text-sm" name="api_key" type="password" autocomplete="off" placeholder="sk-..." data-ai-key>
                    </label>

                    <label class="block text-sm">
                        <span class="font-medium text-slate-700">图片模型</span>
                        <div class="mt-1 grid gap-2 sm:grid-cols-[1fr_auto]">
                            <select class="min-w-0 rounded-sm border border-slate-300 px-3 py-2 text-sm" name="model" data-ai-model-select required>
                                <option value="">先获取模型或手动填写</option>
                            </select>
                            <button class="rounded-sm border border-slate-300 bg-white px-3 py-2 text-xs hover:bg-slate-50" type="button" data-toggle-manual-model>手动</button>
                        </div>
                        <input class="mt-2 hidden w-full rounded-sm border border-slate-300 px-3 py-2 text-sm" name="manual_model" type="text" placeholder="例如 gpt-image-1" data-ai-manual-model>
                    </label>
                </section>

                <section class="space-y-3 border-t border-slate-200 pt-4">
                    <h2 class="text-base font-semibold">提示词</h2>
                    <label class="block text-sm">
                        <span class="font-medium text-slate-700">描述词</span>
                        <textarea class="mt-1 min-h-36 w-full rounded-sm border border-slate-300 px-3 py-2 text-sm leading-6" name="prompt" placeholder="写清主体、风格、镜头、光照、构图、材质、背景和用途" data-ai-prompt required></textarea>
                    </label>
                    <label class="block text-sm">
                        <span class="font-medium text-slate-700">反向词</span>
                        <textarea class="mt-1 min-h-20 w-full rounded-sm border border-slate-300 px-3 py-2 text-sm leading-6" name="negative_prompt" placeholder="不希望出现的元素，例如：低清晰度、畸形文字、额外手指"></textarea>
                    </label>
                </section>

                <section class="space-y-3 border-t border-slate-200 pt-4">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-base font-semibold">参考图</h2>
                        <button class="rounded-sm border border-slate-300 bg-white px-3 py-2 text-xs hover:bg-slate-50" type="button" data-clear-references>清空</button>
                    </div>
                    <input class="block w-full text-sm file:mr-3 file:rounded-sm file:border-0 file:bg-blue-700 file:px-3 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-blue-800" type="file" name="reference_images[]" accept="image/png,image/jpeg,image/gif,image/webp" multiple data-reference-input>
                    <div class="grid grid-cols-3 gap-2" data-reference-preview></div>
                </section>

                <section class="space-y-3 border-t border-slate-200 pt-4">
                    <h2 class="text-base font-semibold">生成参数</h2>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="block text-sm">
                            <span class="font-medium text-slate-700">生成张数</span>
                            <input class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2 text-sm" name="count" type="number" min="1" max="8" value="1">
                        </label>
                        <label class="block text-sm">
                            <span class="font-medium text-slate-700">尺寸模式</span>
                            <select class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2 text-sm" name="size_mode" data-size-mode>
                                <option value="ratio">按比例</option>
                                <option value="custom">自定义宽高</option>
                            </select>
                        </label>
                    </div>

                    <div data-ratio-panel>
                        <label class="block text-sm">
                            <span class="font-medium text-slate-700">画面比例</span>
                            <select class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2 text-sm" name="ratio">
                                <option value="1:1">1:1 方图</option>
                                <option value="4:3">4:3 横图</option>
                                <option value="3:4">3:4 竖图</option>
                                <option value="16:9">16:9 宽屏</option>
                                <option value="9:16">9:16 手机竖屏</option>
                                <option value="21:9">21:9 超宽</option>
                            </select>
                        </label>
                    </div>

                    <div class="hidden grid gap-3 sm:grid-cols-2" data-custom-size-panel>
                        <label class="block text-sm">
                            <span class="font-medium text-slate-700">宽度</span>
                            <input class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2 text-sm" name="width" type="number" min="256" max="4096" step="64" value="1024">
                        </label>
                        <label class="block text-sm">
                            <span class="font-medium text-slate-700">高度</span>
                            <input class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2 text-sm" name="height" type="number" min="256" max="4096" step="64" value="1024">
                        </label>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="block text-sm">
                            <span class="font-medium text-slate-700">质量</span>
                            <select class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2 text-sm" name="quality">
                                <option value="auto">自动</option>
                                <option value="standard">标准</option>
                                <option value="hd">高清</option>
                                <option value="high">高</option>
                                <option value="medium">中</option>
                                <option value="low">低</option>
                            </select>
                        </label>
                        <label class="block text-sm">
                            <span class="font-medium text-slate-700">风格</span>
                            <select class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2 text-sm" name="style">
                                <option value="auto">自动</option>
                                <option value="vivid">鲜明</option>
                                <option value="natural">自然</option>
                            </select>
                        </label>
                        <label class="block text-sm">
                            <span class="font-medium text-slate-700">背景</span>
                            <select class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2 text-sm" name="background">
                                <option value="auto">自动</option>
                                <option value="opaque">不透明</option>
                                <option value="transparent">透明</option>
                            </select>
                        </label>
                        <label class="block text-sm">
                            <span class="font-medium text-slate-700">输出格式</span>
                            <select class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2 text-sm" name="output_format">
                                <option value="auto">自动</option>
                                <option value="png">PNG</option>
                                <option value="jpeg">JPEG</option>
                                <option value="webp">WebP</option>
                            </select>
                        </label>
                        <label class="block text-sm">
                            <span class="font-medium text-slate-700">返回格式</span>
                            <select class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2 text-sm" name="response_format">
                                <option value="auto">自动</option>
                                <option value="url">URL</option>
                                <option value="b64_json">Base64</option>
                            </select>
                        </label>
                        <label class="block text-sm">
                            <span class="font-medium text-slate-700">Seed</span>
                            <input class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2 text-sm" name="seed" type="number" min="0" max="4294967295" placeholder="可选">
                        </label>
                        <label class="block text-sm">
                            <span class="font-medium text-slate-700">步数</span>
                            <input class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2 text-sm" name="steps" type="number" min="1" max="150" placeholder="可选">
                        </label>
                        <label class="block text-sm">
                            <span class="font-medium text-slate-700">引导强度</span>
                            <input class="mt-1 w-full rounded-sm border border-slate-300 px-3 py-2 text-sm" name="guidance_scale" type="number" min="0" max="30" step="0.1" placeholder="可选">
                        </label>
                    </div>
                </section>

                <button class="w-full rounded-sm border border-blue-700 bg-blue-700 px-4 py-3 text-sm font-semibold text-white hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-60" type="submit" data-generate-button>
                    生成图片
                </button>
            </form>

            <section class="min-h-[720px] bg-slate-50 p-4">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold">生成结果</h2>
                        <p class="mt-1 text-sm text-slate-600" data-ai-status>等待填写参数后生成。</p>
                    </div>
                    <button class="rounded-sm border border-slate-300 bg-white px-3 py-2 text-xs hover:bg-slate-50" type="button" data-clear-results>清空结果</button>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3" data-ai-results>
                    <div class="col-span-full rounded-sm border border-dashed border-slate-300 bg-white px-4 py-12 text-center text-sm text-slate-500">
                        生成后的图片会显示在这里，可打开原图或下载。
                    </div>
                </div>
            </section>
        </div>
    </section>

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
            const referenceInput = root.querySelector('[data-reference-input]');
            const referencePreview = root.querySelector('[data-reference-preview]');
            const results = root.querySelector('[data-ai-results]');
            const generateButton = root.querySelector('[data-generate-button]');
            const sizeMode = root.querySelector('[data-size-mode]');
            const ratioPanel = root.querySelector('[data-ratio-panel]');
            const customSizePanel = root.querySelector('[data-custom-size-panel]');
            const csrf = form.querySelector('input[name="_token"]')?.value ?? document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
            let modelFetchTimer = null;

            const setStatus = (message, tone = 'slate') => {
                status.textContent = message;
                status.className = `mt-1 text-sm ${tone === 'red' ? 'text-red-700' : tone === 'green' ? 'text-emerald-700' : 'text-slate-600'}`;
            };

            const activeModel = () => manualModel.classList.contains('hidden') ? modelSelect.value : manualModel.value.trim();

            const updateSizeMode = () => {
                const custom = sizeMode.value === 'custom';
                ratioPanel.classList.toggle('hidden', custom);
                customSizePanel.classList.toggle('hidden', !custom);
            };

            sizeMode.addEventListener('change', updateSizeMode);

            root.querySelector('[data-toggle-manual-model]')?.addEventListener('click', () => {
                manualModel.classList.toggle('hidden');
                modelSelect.toggleAttribute('disabled', !manualModel.classList.contains('hidden'));
            });

            root.querySelector('[data-clear-references]')?.addEventListener('click', () => {
                referenceInput.value = '';
                referencePreview.innerHTML = '';
            });

            referenceInput.addEventListener('change', () => {
                referencePreview.innerHTML = '';
                Array.from(referenceInput.files ?? []).slice(0, 6).forEach((file) => {
                    const url = URL.createObjectURL(file);
                    const item = document.createElement('div');
                    item.className = 'overflow-hidden rounded-sm border border-slate-200 bg-white';
                    item.innerHTML = `<img class="aspect-square w-full object-cover" src="${url}" alt=""><p class="truncate px-2 py-1 text-xs text-slate-600"></p>`;
                    item.querySelector('p').textContent = file.name;
                    item.querySelector('img').addEventListener('load', () => URL.revokeObjectURL(url), { once: true });
                    referencePreview.appendChild(item);
                });
            });

            const fetchModels = async () => {
                if (!endpointInput.value.trim()) {
                    setStatus('请先填写 API URL。', 'red');
                    return;
                }

                const payload = new FormData();
                payload.append('endpoint', form.endpoint.value);
                payload.append('api_key', form.api_key.value);

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
                        manualModel.classList.remove('hidden');
                        modelSelect.setAttribute('disabled', 'disabled');
                    } else {
                        data.models.forEach((model) => {
                            const option = document.createElement('option');
                            option.value = model.id;
                            option.textContent = model.name || model.id;
                            modelSelect.appendChild(option);
                        });
                        manualModel.classList.add('hidden');
                        modelSelect.removeAttribute('disabled');
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

            root.querySelector('[data-clear-results]')?.addEventListener('click', () => {
                results.innerHTML = '<div class="col-span-full rounded-sm border border-dashed border-slate-300 bg-white px-4 py-12 text-center text-sm text-slate-500">生成后的图片会显示在这里，可打开原图或下载。</div>';
                setStatus('结果已清空。');
            });

            form.addEventListener('submit', async (event) => {
                event.preventDefault();

                const model = activeModel();
                if (!model) {
                    setStatus('请先选择或手动填写图片模型。', 'red');
                    return;
                }

                const payload = new FormData(form);
                payload.set('model', model);
                payload.delete('manual_model');

                generateButton.disabled = true;
                setStatus('正在生成图片，请稍候...');

                try {
                    const response = await fetch(root.dataset.generateUrl, {
                        method: 'POST',
                        body: payload,
                        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                    });
                    const data = await response.json();

                    if (!response.ok) throw new Error(data.message || '图片生成失败。');

                    renderImages(data.images ?? []);
                    setStatus(`生成完成，共 ${data.images?.length ?? 0} 张。`, 'green');
                } catch (error) {
                    setStatus(error.message || '图片生成失败。', 'red');
                } finally {
                    generateButton.disabled = false;
                }
            });

            const renderImages = (images) => {
                results.innerHTML = '';

                if (!images.length) {
                    results.innerHTML = '<div class="col-span-full rounded-sm border border-dashed border-slate-300 bg-white px-4 py-12 text-center text-sm text-slate-500">没有返回图片。</div>';
                    return;
                }

                images.forEach((image, index) => {
                    const src = image.url || image.data_url;
                    if (!src) return;

                    const safeSrc = escapeAttribute(src);
                    const article = document.createElement('article');
                    article.className = 'overflow-hidden rounded-sm border border-slate-300 bg-white';
                    article.innerHTML = `
                        <img class="aspect-square w-full bg-slate-100 object-contain" src="${safeSrc}" alt="AI 生成图片 ${index + 1}">
                        <div class="space-y-2 border-t border-slate-200 p-3 text-sm">
                            <div class="flex flex-wrap gap-2">
                                <a class="rounded-sm border border-blue-700 bg-blue-700 px-3 py-2 text-xs font-medium text-white hover:bg-blue-800" href="${safeSrc}" target="_blank" rel="noopener noreferrer">打开原图</a>
                                <a class="rounded-sm border border-slate-300 bg-white px-3 py-2 text-xs hover:bg-slate-50" href="${safeSrc}" download="ai-image-${index + 1}.png">下载</a>
                            </div>
                            ${image.revised_prompt ? `<p class="text-xs leading-5 text-slate-600">${escapeHtml(image.revised_prompt)}</p>` : ''}
                        </div>
                    `;
                    results.appendChild(article);
                });
            };

            const escapeHtml = (value) => String(value)
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');

            const escapeAttribute = (value) => escapeHtml(value).replaceAll('`', '&#096;');
        })();
    </script>
</x-layouts.app>
