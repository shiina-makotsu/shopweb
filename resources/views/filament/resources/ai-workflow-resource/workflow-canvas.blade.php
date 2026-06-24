@php
    $initialNodes = collect($get('nodes') ?? $record?->nodes ?? [])->filter(fn ($node) => is_array($node))->values()->all();
    $initialEdges = collect($get('edges') ?? $record?->edges ?? [])->filter(fn ($edge) => is_array($edge))->values()->all();
    $nodeTypes = \App\Filament\Resources\AiWorkflowResource::nodeTypeOptions();
@endphp

<div
    x-data="shopwebAiWorkflowCanvas({
        nodes: @js($initialNodes),
        edges: @js($initialEdges),
        nodeTypes: @js($nodeTypes),
        nodesStatePath: 'data.nodes',
        edgesStatePath: 'data.edges',
    })"
    x-init="init()"
    x-on:mousemove.window="dragMove($event); edgeMove($event)"
    x-on:mouseup.window="dragEnd(); edgeEnd()"
    x-on:keydown.escape.window="libraryOpen = false; contextMenu.open = false"
    class="overflow-hidden rounded-xl border border-gray-200 bg-gray-950 text-gray-100 shadow-sm dark:border-gray-700"
>
    <div class="flex items-center justify-between border-b border-gray-800 bg-gray-900 px-4 py-3">
        <div class="flex items-center gap-2">
            <button type="button" x-on:click.prevent.stop="openLibrary()" class="rounded-lg border border-gray-600 bg-gray-800 px-3 py-2 text-sm text-gray-100 hover:bg-gray-700">
                组件库
            </button>
            <div>
                <div class="text-sm font-semibold">工作流画布</div>
                <div class="text-xs text-gray-300">拖入组件、右键添加组件，点击输出端口再点击输入端口即可连线。</div>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2 text-xs">
            <button type="button" x-on:click="zoomOut()" class="rounded-md border border-gray-700 px-2 py-1 hover:bg-gray-800">-</button>
            <span class="w-14 text-center text-gray-300" x-text="Math.round(zoom * 100) + '%'"></span>
            <button type="button" x-on:click="zoomIn()" class="rounded-md border border-gray-700 px-2 py-1 hover:bg-gray-800">+</button>
            <button type="button" x-on:click="resetZoom()" class="rounded-md border border-gray-700 px-2 py-1 hover:bg-gray-800">1:1</button>
            <select x-model.number="canvasPreset" x-on:change="applyCanvasPreset()" class="rounded-md border border-gray-700 bg-gray-950 px-2 py-1 text-gray-100">
                <option value="2400">2400x1400</option>
                <option value="3600">3600x2200</option>
                <option value="5200">5200x3200</option>
            </select>
            <button type="button" x-on:click="autoLayout()" class="rounded-md border border-gray-700 px-2 py-1 hover:bg-gray-800">自动排列</button>
            <button type="button" x-on:click="removeSelected()" class="rounded-md border border-red-500/40 px-2 py-1 text-red-200 hover:bg-red-500/10">删除选中</button>
            <button type="button" x-on:click="resetCanvas()" class="rounded-md border border-gray-700 px-2 py-1 hover:bg-gray-800">清空</button>
            <span class="rounded-full bg-gray-800 px-2 py-1 text-gray-300"><span x-text="nodes.length"></span> 节点</span>
            <span class="rounded-full bg-gray-800 px-2 py-1 text-gray-300"><span x-text="edges.length"></span> 连线</span>
        </div>
    </div>

    <div class="relative min-h-[720px]">
        <aside
            x-bind:class="libraryOpen ? 'flex' : 'hidden'"
            class="absolute left-4 top-4 z-40 max-h-[660px] w-80 flex-col overflow-hidden rounded-xl border border-slate-600 bg-slate-900 text-slate-100 shadow-2xl"
        >
            <div class="flex items-center justify-between border-b border-slate-700 p-4">
                <div>
                    <div class="text-sm font-semibold">组件库</div>
                    <div class="mt-1 text-xs text-slate-300">拖入画布或点击添加。</div>
                </div>
                <button type="button" x-on:click="libraryOpen = false" class="rounded-md px-2 py-1 text-slate-300 hover:bg-slate-700 hover:text-white">×</button>
            </div>

            <div class="flex-1 space-y-3 overflow-y-auto p-3">
                <template x-for="group in componentTree()" :key="group.name">
                    <details class="rounded-lg border border-slate-700 bg-slate-950/40" open>
                        <summary class="flex cursor-pointer select-none items-center justify-between rounded-lg px-3 py-2 text-sm font-semibold text-slate-100 hover:bg-slate-800">
                            <span x-text="group.name"></span>
                            <span class="text-xs font-normal text-slate-400" x-text="group.children.length + ' 类'"></span>
                        </summary>

                        <div class="space-y-2 border-t border-slate-800 p-2">
                            <template x-for="child in group.children" :key="child.name">
                                <details class="rounded-md bg-slate-900/70" open>
                                    <summary class="flex cursor-pointer select-none items-center justify-between px-2 py-1.5 text-xs font-semibold text-slate-300 hover:text-white">
                                        <span x-text="child.name"></span>
                                        <span class="font-normal text-slate-500" x-text="child.items.length + ' 个'"></span>
                                    </summary>

                                    <div class="space-y-2 px-2 pb-2">
                                        <template x-for="component in child.items" :key="component.type">
                                            <button
                                                type="button"
                                                draggable="true"
                                                x-on:dragstart="sidebarDragStart($event, component.type)"
                                                x-on:click="addNode(component.type); libraryOpen = false"
                                                class="flex w-full items-start gap-3 rounded-lg border border-slate-700 bg-slate-800 p-3 text-left text-slate-100 transition hover:border-blue-400 hover:bg-slate-700"
                                            >
                                                <span class="mt-0.5 h-3 w-3 rounded-full" :class="component.color"></span>
                                                <span class="min-w-0">
                                                    <span class="block text-sm font-medium" x-text="component.label"></span>
                                                    <span class="mt-1 block text-xs text-slate-300" x-text="component.hint"></span>
                                                </span>
                                            </button>
                                        </template>
                                    </div>
                                </details>
                            </template>
                        </div>
                    </details>
                </template>
            </div>
        </aside>

        <div
            x-ref="viewport"
            x-on:dragover.prevent
            x-on:drop.prevent="canvasDrop($event)"
            x-on:contextmenu.prevent="openContextMenu($event)"
            x-on:mousedown.self="selectNode(null)"
            class="h-[720px] overflow-auto bg-gray-950"
        >
            <div class="relative" :style="`width:${canvasWidth * zoom}px; height:${canvasHeight * zoom}px;`">
                <div
                    x-ref="plane"
                    class="workflow-canvas-grid absolute left-0 top-0"
                    :style="`width:${canvasWidth}px; height:${canvasHeight}px; transform: scale(${zoom}); transform-origin: 0 0;`"
                >
                    <svg class="pointer-events-none absolute left-0 top-0" :style="`width:${canvasWidth}px; height:${canvasHeight}px;`">
                        <template x-for="edge in edges" :key="edge.id">
                            <path
                                :d="edgePath(edge)"
                                fill="none"
                                stroke-width="3"
                                :stroke="selectedEdgeId === edge.id ? '#60a5fa' : '#64748b'"
                                class="pointer-events-auto cursor-pointer"
                                x-on:mousedown.stop="selectEdge(edge.id)"
                            ></path>
                        </template>
                        <path
                            x-show="connecting"
                            :d="temporaryEdgePath()"
                            fill="none"
                            stroke="#93c5fd"
                            stroke-width="3"
                            stroke-dasharray="8 6"
                        ></path>
                    </svg>

                    <template x-for="node in nodes" :key="node.node_id">
                        <section
                            class="absolute w-80 rounded-xl border bg-slate-800 text-slate-100 shadow-xl"
                            :class="selectedNodeId === node.node_id ? 'border-blue-400 ring-2 ring-blue-400/30' : nodeBorder(node.type)"
                            :style="`left:${node.x}px; top:${node.y}px;`"
                            x-on:mousedown.stop="selectNode(node.node_id)"
                        >
                            <header
                                class="flex cursor-move items-center justify-between gap-2 rounded-t-xl border-b border-slate-700 bg-slate-900 px-3 py-2"
                                x-on:mousedown.prevent.stop="dragStart($event, node.node_id)"
                            >
                                <div class="min-w-0">
                                    <input
                                        type="text"
                                        x-model="node.title"
                                        x-on:input="sync()"
                                        class="w-full min-w-0 border-0 bg-transparent p-0 text-sm font-semibold text-slate-100 placeholder:text-slate-500 focus:ring-0"
                                        :placeholder="labelFor(node.type)"
                                    >
                                    <div class="truncate text-xs text-slate-400" x-text="node.node_id"></div>
                                </div>
                                <div class="flex shrink-0 items-center gap-1">
                                    <span class="rounded-full px-2 py-0.5 text-xs" :class="nodeBadge(node.type)" x-text="labelFor(node.type)"></span>
                                    <button type="button" title="删除组件" x-on:click.stop="deleteNode(node.node_id)" class="rounded px-1.5 py-0.5 text-xs text-red-300 hover:bg-red-500/10">×</button>
                                </div>
                            </header>

                            <div class="relative space-y-3 p-3 text-xs">
                                <div class="grid grid-cols-2 gap-3 rounded-md bg-slate-900 p-2 text-[11px]">
                                    <div class="space-y-1">
                                        <div class="mb-1 text-slate-400">输入</div>
                                        <template x-for="port in inputPorts(node.type)" :key="port.key">
                                            <button
                                                type="button"
                                                class="workflow-port-row workflow-port-row-in"
                                                :title="`输入：${port.label}`"
                                                x-on:click.stop="portClick('input', node.node_id, port.key)"
                                                x-on:mouseup.stop="finishConnection(node.node_id, port.key)"
                                            >
                                                <span class="workflow-port-dot workflow-port-dot-in"></span>
                                                <span x-text="port.label"></span>
                                            </button>
                                        </template>
                                        <div x-show="! inputPorts(node.type).length" class="text-slate-500">无</div>
                                    </div>
                                    <div class="space-y-1 text-right">
                                        <div class="mb-1 text-slate-400">输出</div>
                                        <template x-for="port in outputPorts(node.type)" :key="port.key">
                                            <button
                                                type="button"
                                                class="workflow-port-row workflow-port-row-out"
                                                :title="`输出：${port.label}`"
                                                x-on:click.stop="portClick('output', node.node_id, port.key)"
                                                x-on:mousedown.stop.prevent="startConnection($event, node.node_id, 'drag', port.key)"
                                            >
                                                <span x-text="port.label"></span>
                                                <span class="workflow-port-dot workflow-port-dot-out"></span>
                                            </button>
                                        </template>
                                        <div x-show="! outputPorts(node.type).length" class="text-slate-500">无</div>
                                    </div>
                                </div>

                                <template x-if="shows(node.type, 'model')">
                                    <label class="block text-slate-300">
                                        模型
                                        <input type="text" x-model="node.model_id" x-on:input="sync()" class="mt-1 w-full rounded-md border border-slate-600 bg-slate-950 px-2 py-1.5 text-slate-100 placeholder:text-slate-500 focus:border-blue-400 focus:ring-blue-400" :placeholder="modelPlaceholder(node.type)">
                                    </label>
                                </template>

                                <template x-if="shows(node.type, 'lora')">
                                    <label class="block text-slate-300">
                                        LoRA
                                        <input type="text" x-model="node.lora_name" x-on:input="sync()" class="mt-1 w-full rounded-md border border-slate-600 bg-slate-950 px-2 py-1.5 text-slate-100 placeholder:text-slate-500 focus:border-blue-400 focus:ring-blue-400" placeholder="选择或填写 LoRA 名称">
                                    </label>
                                </template>

                                <template x-if="shows(node.type, 'lora_strength')">
                                    <div class="grid grid-cols-2 gap-2">
                                        <label class="block text-slate-300">
                                            模型强度
                                            <input type="number" step="0.05" x-model.number="node.strength_model" x-on:input="sync()" class="mt-1 w-full rounded-md border border-slate-600 bg-slate-950 px-2 py-1.5 text-slate-100 focus:border-blue-400 focus:ring-blue-400">
                                        </label>
                                        <label class="block text-slate-300">
                                            CLIP 强度
                                            <input type="number" step="0.05" x-model.number="node.strength_clip" x-on:input="sync()" class="mt-1 w-full rounded-md border border-slate-600 bg-slate-950 px-2 py-1.5 text-slate-100 focus:border-blue-400 focus:ring-blue-400">
                                        </label>
                                    </div>
                                </template>

                                <template x-if="shows(node.type, 'text')">
                                    <label class="block text-slate-300">
                                        文本 / 提示词
                                        <textarea x-model="node.prompt_template" x-on:input="sync()" rows="4" class="mt-1 w-full rounded-md border border-slate-600 bg-slate-950 px-2 py-1.5 text-slate-100 placeholder:text-slate-500 focus:border-blue-400 focus:ring-blue-400"></textarea>
                                    </label>
                                </template>

                                <template x-if="shows(node.type, 'image_path')">
                                    <label class="block text-slate-300">
                                        图片路径 / URL
                                        <input type="text" x-model="node.image_path" x-on:input="sync()" class="mt-1 w-full rounded-md border border-slate-600 bg-slate-950 px-2 py-1.5 text-slate-100 placeholder:text-slate-500 focus:border-blue-400 focus:ring-blue-400" placeholder="storage 路径或 URL">
                                    </label>
                                </template>

                                <template x-if="shows(node.type, 'save_path')">
                                    <label class="block text-slate-300">
                                        保存路径 / 文件名前缀
                                        <input type="text" x-model="node.save_prefix" x-on:input="sync()" class="mt-1 w-full rounded-md border border-slate-600 bg-slate-950 px-2 py-1.5 text-slate-100 placeholder:text-slate-500 focus:border-blue-400 focus:ring-blue-400" placeholder="ai/output">
                                    </label>
                                </template>

                                <template x-if="shows(node.type, 'latent_size')">
                                    <div class="grid grid-cols-3 gap-2">
                                        <label class="block text-slate-300">
                                            宽
                                            <input type="number" x-model.number="node.width" x-on:input="sync()" class="mt-1 w-full rounded-md border border-slate-600 bg-slate-950 px-2 py-1.5 text-slate-100 focus:border-blue-400 focus:ring-blue-400">
                                        </label>
                                        <label class="block text-slate-300">
                                            高
                                            <input type="number" x-model.number="node.height" x-on:input="sync()" class="mt-1 w-full rounded-md border border-slate-600 bg-slate-950 px-2 py-1.5 text-slate-100 focus:border-blue-400 focus:ring-blue-400">
                                        </label>
                                        <label class="block text-slate-300">
                                            批量
                                            <input type="number" x-model.number="node.batch_size" x-on:input="sync()" class="mt-1 w-full rounded-md border border-slate-600 bg-slate-950 px-2 py-1.5 text-slate-100 focus:border-blue-400 focus:ring-blue-400">
                                        </label>
                                    </div>
                                </template>

                                <template x-if="shows(node.type, 'scale_size')">
                                    <div class="grid grid-cols-2 gap-2">
                                        <label class="block text-slate-300">
                                            宽
                                            <input type="number" x-model.number="node.width" x-on:input="sync()" class="mt-1 w-full rounded-md border border-slate-600 bg-slate-950 px-2 py-1.5 text-slate-100 focus:border-blue-400 focus:ring-blue-400">
                                        </label>
                                        <label class="block text-slate-300">
                                            高
                                            <input type="number" x-model.number="node.height" x-on:input="sync()" class="mt-1 w-full rounded-md border border-slate-600 bg-slate-950 px-2 py-1.5 text-slate-100 focus:border-blue-400 focus:ring-blue-400">
                                        </label>
                                    </div>
                                </template>

                                <template x-if="shows(node.type, 'upscale')">
                                    <label class="block text-slate-300">
                                        放大倍数
                                        <input type="number" step="0.25" x-model.number="node.upscale_by" x-on:input="sync()" class="mt-1 w-full rounded-md border border-slate-600 bg-slate-950 px-2 py-1.5 text-slate-100 focus:border-blue-400 focus:ring-blue-400">
                                    </label>
                                </template>

                                <template x-if="shows(node.type, 'ksampler')">
                                    <div class="space-y-2">
                                        <div class="grid grid-cols-2 gap-2">
                                            <label class="block text-slate-300">
                                                Seed
                                                <input type="number" x-model.number="node.seed" x-on:input="sync()" class="mt-1 w-full rounded-md border border-slate-600 bg-slate-950 px-2 py-1.5 text-slate-100 focus:border-blue-400 focus:ring-blue-400">
                                            </label>
                                            <label class="block text-slate-300">
                                                Steps
                                                <input type="number" x-model.number="node.steps" x-on:input="sync()" class="mt-1 w-full rounded-md border border-slate-600 bg-slate-950 px-2 py-1.5 text-slate-100 focus:border-blue-400 focus:ring-blue-400">
                                            </label>
                                        </div>
                                        <div class="grid grid-cols-2 gap-2">
                                            <label class="block text-slate-300">
                                                CFG
                                                <input type="number" step="0.1" x-model.number="node.cfg" x-on:input="sync()" class="mt-1 w-full rounded-md border border-slate-600 bg-slate-950 px-2 py-1.5 text-slate-100 focus:border-blue-400 focus:ring-blue-400">
                                            </label>
                                            <label class="block text-slate-300">
                                                Denoise
                                                <input type="number" step="0.05" x-model.number="node.denoise" x-on:input="sync()" class="mt-1 w-full rounded-md border border-slate-600 bg-slate-950 px-2 py-1.5 text-slate-100 focus:border-blue-400 focus:ring-blue-400">
                                            </label>
                                        </div>
                                        <div class="grid grid-cols-2 gap-2">
                                            <label class="block text-slate-300">
                                                Sampler
                                                <input type="text" x-model="node.sampler_name" x-on:input="sync()" class="mt-1 w-full rounded-md border border-slate-600 bg-slate-950 px-2 py-1.5 text-slate-100 placeholder:text-slate-500 focus:border-blue-400 focus:ring-blue-400" placeholder="euler / dpmpp_2m">
                                            </label>
                                            <label class="block text-slate-300">
                                                Scheduler
                                                <input type="text" x-model="node.scheduler" x-on:input="sync()" class="mt-1 w-full rounded-md border border-slate-600 bg-slate-950 px-2 py-1.5 text-slate-100 placeholder:text-slate-500 focus:border-blue-400 focus:ring-blue-400" placeholder="normal / karras">
                                            </label>
                                        </div>
                                    </div>
                                </template>

                                <div x-show="! configurableFields(node.type).length" class="rounded-md bg-slate-900 p-2 text-slate-400">
                                    该节点无额外参数，只负责接收输入并输出结果。
                                </div>
                            </div>
                        </section>
                    </template>

                    <div
                        x-show="contextMenu.open"
                        x-transition
                        x-on:mousedown.outside="contextMenu.open = false"
                        class="absolute z-30 max-h-96 w-64 overflow-y-auto rounded-lg border border-slate-600 bg-slate-900 p-2 text-slate-100 shadow-2xl"
                        :style="`left:${contextMenu.x}px; top:${contextMenu.y}px;`"
                    >
                        <template x-for="group in componentTree()" :key="group.name">
                            <section class="mb-2 last:mb-0">
                                <div class="px-2 py-1 text-xs font-semibold text-slate-300" x-text="group.name"></div>
                                <template x-for="child in group.children" :key="child.name">
                                    <details class="mb-1 rounded-md bg-slate-950/40" open>
                                        <summary class="cursor-pointer select-none px-2 py-1 text-xs text-slate-400 hover:text-slate-100" x-text="child.name"></summary>
                                        <template x-for="component in child.items" :key="component.type">
                                            <button type="button" x-on:click="addNode(component.type, contextMenu.x, contextMenu.y); contextMenu.open = false" class="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-sm text-slate-100 hover:bg-slate-700">
                                                <span class="h-2.5 w-2.5 rounded-full" :class="component.color"></span>
                                                <span x-text="component.label"></span>
                                            </button>
                                        </template>
                                    </details>
                                </template>
                            </section>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .workflow-canvas-grid {
        background-color: #020617;
        background-image:
            linear-gradient(rgba(148, 163, 184, .12) 1px, transparent 1px),
            linear-gradient(90deg, rgba(148, 163, 184, .12) 1px, transparent 1px);
        background-size: 28px 28px;
    }

    .workflow-port-row {
        display: inline-flex;
        align-items: center;
        gap: .35rem;
        max-width: 100%;
        border-radius: .375rem;
        padding: .125rem .25rem;
        color: #cbd5e1;
    }

    .workflow-port-row:hover {
        background: rgba(96, 165, 250, .16);
        color: #bfdbfe;
    }

    .workflow-port-dot {
        width: 8px;
        height: 8px;
        border-radius: 999px;
        box-shadow: 0 0 0 1px rgba(15, 23, 42, .35);
    }

    .workflow-port-dot-in {
        background: #94a3b8;
    }

    .workflow-port-dot-out {
        background: #60a5fa;
    }
</style>

<script>
    window.shopwebAiWorkflowCanvas = window.shopwebAiWorkflowCanvas || ((config) => ({
        libraryOpen: false,
        zoom: 1,
        canvasPreset: 3600,
        canvasWidth: 3600,
        canvasHeight: 2200,
        nodes: Array.isArray(config.nodes) ? config.nodes.map((node, index) => ({
            node_id: node.node_id || `node_${Date.now()}_${index}`,
            type: node.type === 'image_model' ? 'checkpoint_loader' : (node.type || 'language_model'),
            title: node.title || '',
            order: Number(node.order || index + 1),
            model_id: node.model_id || '',
            lora_name: node.lora_name || (Array.isArray(node.lora_ids) ? (node.lora_ids[0] || '') : ''),
            strength_model: Number(node.strength_model ?? 1),
            strength_clip: Number(node.strength_clip ?? 1),
            prompt_template: node.prompt_template || '',
            image_path: node.image_path || '',
            save_prefix: node.save_prefix || 'ai/output',
            width: Number(node.width ?? 1024),
            height: Number(node.height ?? 1024),
            batch_size: Number(node.batch_size ?? 1),
            upscale_by: Number(node.upscale_by ?? 2),
            seed: Number(node.seed ?? 0),
            steps: Number(node.steps ?? 20),
            cfg: Number(node.cfg ?? 7),
            denoise: Number(node.denoise ?? 1),
            sampler_name: node.sampler_name || 'euler',
            scheduler: node.scheduler || 'normal',
            x: Number(node.x ?? (120 + index * 360)),
            y: Number(node.y ?? 100),
        })) : [],
        edges: Array.isArray(config.edges) ? config.edges.map((edge, index) => ({
            id: edge.id || `edge_${Date.now()}_${index}`,
            from: edge.from || '',
            to: edge.to || '',
            fromPort: edge.fromPort || edge.output || '',
            toPort: edge.toPort || edge.input || '',
            label: edge.label || '',
        })) : [],
        components: Object.entries(config.nodeTypes || {}).map(([type, label]) => ({
            type,
            label,
            hint: {
                input: '接收用户问题、提示词或参考图。',
                language_model: '加载并调用语言模型。',
                chat_prompt: '为语言模型整理聊天提示词。',
                checkpoint_loader: '只负责选择画图模型。',
                lora_loader: '只负责为模型加载 LoRA。',
                clip_text_encode: '把正向/负向提示词编码为条件。',
                load_image: '从资源库、URL 或路径载入图片。',
                save_image: '保存工作流输出图片。',
                empty_latent_image: '创建指定尺寸的空潜空间。',
                vae_encode: '把图片编码为潜空间。',
                vae_decode: '把潜空间解码为图片。',
                k_sampler: '按采样参数生成潜空间结果。',
                controlnet_loader: '只负责选择 ControlNet 模型。',
                controlnet_apply: '把 ControlNet 条件应用到提示词条件。',
                upscale_model_loader: '只负责选择放大模型。',
                image_upscale: '用放大模型放大图片。',
                image_scale: '按宽高缩放图片。',
                preview_image: '只负责预览图片。',
                mask_load: '只负责加载遮罩。',
                search_model: '负责联网搜索或检索。',
                rank_model: '对候选结果进行排序。',
                reply_model: '组织最终回复内容。',
                output: '返回给调用方的最终输出。',
            }[type] || '工作流节点。',
            color: {
                input: 'bg-gray-400',
                language_model: 'bg-blue-400',
                chat_prompt: 'bg-sky-400',
                checkpoint_loader: 'bg-pink-400',
                lora_loader: 'bg-amber-400',
                clip_text_encode: 'bg-indigo-400',
                load_image: 'bg-lime-400',
                save_image: 'bg-green-400',
                empty_latent_image: 'bg-fuchsia-400',
                vae_encode: 'bg-purple-400',
                vae_decode: 'bg-purple-300',
                k_sampler: 'bg-orange-400',
                controlnet_loader: 'bg-red-400',
                controlnet_apply: 'bg-red-300',
                upscale_model_loader: 'bg-teal-400',
                image_upscale: 'bg-teal-300',
                image_scale: 'bg-lime-300',
                preview_image: 'bg-green-300',
                mask_load: 'bg-rose-300',
                search_model: 'bg-cyan-400',
                rank_model: 'bg-violet-400',
                reply_model: 'bg-emerald-400',
                output: 'bg-green-400',
            }[type] || 'bg-gray-400',
        })),
        selectedNodeId: null,
        selectedEdgeId: null,
        dragging: null,
        connecting: null,
        tempPoint: { x: 0, y: 0 },
        contextMenu: { open: false, x: 0, y: 0 },
        nodesStatePath: config.nodesStatePath,
        edgesStatePath: config.edgesStatePath,

        init() {
            if (! this.nodes.length) {
                this.addNode('input', 120, 120);
                this.addNode('language_model', 520, 120);
                this.addNode('output', 920, 120);
                this.edges.push({ id: this.edgeId(), from: this.nodes[0].node_id, to: this.nodes[1].node_id, label: '' });
                this.edges.push({ id: this.edgeId(), from: this.nodes[1].node_id, to: this.nodes[2].node_id, label: '' });
            }

            this.sync();
        },

        labelFor(type) {
            return this.components.find((component) => component.type === type)?.label || type;
        },

        openLibrary() {
            this.libraryOpen = true;
        },

        toggleLibrary() {
            this.libraryOpen = ! this.libraryOpen;
        },

        componentTree() {
            const tree = [
                {
                    name: '基础',
                    children: [
                        { name: '输入输出', types: ['input', 'output'] },
                    ],
                },
                {
                    name: '文本与聊天',
                    children: [
                        { name: '模型加载', types: ['language_model'] },
                        { name: '提示词', types: ['chat_prompt'] },
                        { name: '检索与编排', types: ['search_model', 'rank_model', 'reply_model'] },
                    ],
                },
                {
                    name: '图像生成',
                    children: [
                        { name: '模型加载', types: ['checkpoint_loader', 'lora_loader'] },
                        { name: '条件编码', types: ['clip_text_encode', 'controlnet_loader', 'controlnet_apply'] },
                        { name: '采样', types: ['empty_latent_image', 'k_sampler'] },
                    ],
                },
                {
                    name: '图像处理',
                    children: [
                        { name: '图片输入输出', types: ['load_image', 'save_image', 'preview_image'] },
                        { name: 'VAE', types: ['vae_encode', 'vae_decode'] },
                        { name: '尺寸与放大', types: ['image_scale', 'upscale_model_loader', 'image_upscale'] },
                        { name: '遮罩', types: ['mask_load'] },
                    ],
                },
            ];

            return tree
                .map((group) => ({
                    name: group.name,
                    children: group.children
                        .map((child) => ({
                            name: child.name,
                            items: child.types
                                .map((type) => this.components.find((component) => component.type === type))
                                .filter(Boolean),
                        }))
                        .filter((child) => child.items.length),
                }))
                .filter((group) => group.children.length);
        },

        componentGroups() {
            return this.componentTree()
                .flatMap((group) => group.children.map((child) => ({
                    name: `${group.name} / ${child.name}`,
                    items: child.items,
                })));
        },

        configurableFields(type) {
            return {
                input: [],
                output: [],
                language_model: ['model'],
                chat_prompt: ['text'],
                checkpoint_loader: ['model'],
                lora_loader: ['lora', 'lora_strength'],
                clip_text_encode: ['text'],
                load_image: ['image_path'],
                save_image: ['save_path'],
                empty_latent_image: ['latent_size'],
                vae_encode: [],
                vae_decode: [],
                k_sampler: ['ksampler'],
                controlnet_loader: ['model'],
                controlnet_apply: [],
                upscale_model_loader: ['model'],
                image_upscale: ['upscale'],
                image_scale: ['scale_size'],
                preview_image: [],
                mask_load: ['image_path'],
                search_model: ['model', 'text'],
                rank_model: ['model', 'text'],
                reply_model: ['model', 'text'],
            }[type] || [];
        },

        shows(type, field) {
            return this.configurableFields(type).includes(field);
        },

        modelPlaceholder(type) {
            return {
                language_model: '例如 gpt-5.5 / local:language/model.gguf',
                checkpoint_loader: '例如 local:image/sd15.safetensors',
                controlnet_loader: '例如 local:image/controlnet-canny.safetensors',
                upscale_model_loader: '例如 local:image/4x-ultrasharp.pth',
                search_model: '搜索用模型',
                rank_model: '排序用模型',
                reply_model: '回复用模型',
            }[type] || '模型名称或本地模型路径';
        },

        inputPorts(type) {
            return {
                input: [],
                output: [{ key: 'result', label: 'RESULT' }],
                language_model: [{ key: 'prompt', label: 'PROMPT' }],
                chat_prompt: [{ key: 'message', label: 'MESSAGE' }],
                checkpoint_loader: [],
                lora_loader: [{ key: 'model', label: 'MODEL' }, { key: 'clip', label: 'CLIP' }],
                clip_text_encode: [{ key: 'clip', label: 'CLIP' }],
                load_image: [],
                save_image: [{ key: 'image', label: 'IMAGE' }],
                empty_latent_image: [],
                vae_encode: [{ key: 'image', label: 'IMAGE' }, { key: 'vae', label: 'VAE' }],
                vae_decode: [{ key: 'samples', label: 'LATENT' }, { key: 'vae', label: 'VAE' }],
                k_sampler: [
                    { key: 'model', label: 'MODEL' },
                    { key: 'positive', label: 'POSITIVE' },
                    { key: 'negative', label: 'NEGATIVE' },
                    { key: 'latent_image', label: 'LATENT' },
                ],
                controlnet_loader: [],
                controlnet_apply: [
                    { key: 'conditioning', label: 'CONDITIONING' },
                    { key: 'control_net', label: 'CONTROL_NET' },
                    { key: 'image', label: 'IMAGE' },
                ],
                upscale_model_loader: [],
                image_upscale: [{ key: 'upscale_model', label: 'UPSCALE_MODEL' }, { key: 'image', label: 'IMAGE' }],
                image_scale: [{ key: 'image', label: 'IMAGE' }],
                preview_image: [{ key: 'images', label: 'IMAGE' }],
                mask_load: [],
                search_model: [{ key: 'query', label: 'QUERY' }],
                rank_model: [{ key: 'candidates', label: 'CANDIDATES' }],
                reply_model: [{ key: 'context', label: 'CONTEXT' }],
            }[type] || [];
        },

        outputPorts(type) {
            return {
                input: [{ key: 'text', label: 'TEXT' }, { key: 'image', label: 'IMAGE' }],
                output: [],
                language_model: [{ key: 'text', label: 'TEXT' }],
                chat_prompt: [{ key: 'prompt', label: 'PROMPT' }],
                checkpoint_loader: [{ key: 'model', label: 'MODEL' }, { key: 'clip', label: 'CLIP' }, { key: 'vae', label: 'VAE' }],
                lora_loader: [{ key: 'model', label: 'MODEL' }, { key: 'clip', label: 'CLIP' }],
                clip_text_encode: [{ key: 'conditioning', label: 'CONDITIONING' }],
                load_image: [{ key: 'image', label: 'IMAGE' }],
                save_image: [{ key: 'saved_image', label: 'SAVED' }],
                empty_latent_image: [{ key: 'latent', label: 'LATENT' }],
                vae_encode: [{ key: 'latent', label: 'LATENT' }],
                vae_decode: [{ key: 'image', label: 'IMAGE' }],
                k_sampler: [{ key: 'samples', label: 'LATENT' }],
                controlnet_loader: [{ key: 'control_net', label: 'CONTROL_NET' }],
                controlnet_apply: [{ key: 'conditioning', label: 'CONDITIONING' }],
                upscale_model_loader: [{ key: 'upscale_model', label: 'UPSCALE_MODEL' }],
                image_upscale: [{ key: 'image', label: 'IMAGE' }],
                image_scale: [{ key: 'image', label: 'IMAGE' }],
                preview_image: [{ key: 'preview', label: 'PREVIEW' }],
                mask_load: [{ key: 'mask', label: 'MASK' }],
                search_model: [{ key: 'results', label: 'RESULTS' }],
                rank_model: [{ key: 'ranked', label: 'RANKED' }],
                reply_model: [{ key: 'reply', label: 'REPLY' }],
            }[type] || [];
        },

        nodeId(type) {
            return `${type}_${Math.random().toString(36).slice(2, 8)}`;
        },

        edgeId() {
            return `edge_${Math.random().toString(36).slice(2, 10)}`;
        },

        zoomIn() {
            this.zoom = Math.min(2, Number((this.zoom + 0.1).toFixed(2)));
        },

        zoomOut() {
            this.zoom = Math.max(0.35, Number((this.zoom - 0.1).toFixed(2)));
        },

        resetZoom() {
            this.zoom = 1;
        },

        applyCanvasPreset() {
            const width = Number(this.canvasPreset || 3600);
            this.canvasWidth = width;
            this.canvasHeight = width === 2400 ? 1400 : (width === 5200 ? 3200 : 2200);
        },

        sidebarDragStart(event, type) {
            event.dataTransfer.setData('application/x-shopweb-ai-node', type);
            event.dataTransfer.effectAllowed = 'copy';
        },

        canvasDrop(event) {
            const type = event.dataTransfer.getData('application/x-shopweb-ai-node');

            if (! type) {
                return;
            }

            const point = this.eventPoint(event);
            this.addNode(type, point.x, point.y);
            this.libraryOpen = false;
        },

        openContextMenu(event) {
            const point = this.eventPoint(event);
            this.contextMenu = { open: true, x: point.x, y: point.y };
        },

        addNode(type, x = null, y = null) {
            const id = this.nodeId(type);
            const index = this.nodes.length;
            const component = this.components.find((item) => item.type === type);

            this.nodes.push({
                node_id: id,
                type,
                title: component?.label || type,
                order: index + 1,
                model_id: '',
                lora_name: '',
                strength_model: 1,
                strength_clip: 1,
                prompt_template: '',
                image_path: '',
                save_prefix: 'ai/output',
                width: 1024,
                height: 1024,
                batch_size: 1,
                upscale_by: 2,
                seed: 0,
                steps: 20,
                cfg: 7,
                denoise: 1,
                sampler_name: 'euler',
                scheduler: 'normal',
                x: x ?? (160 + index * 36),
                y: y ?? (160 + index * 28),
            });

            this.selectNode(id);
            this.sync();
        },

        selectNode(id) {
            this.selectedNodeId = id;
            this.selectedEdgeId = null;
            this.contextMenu.open = false;
        },

        selectEdge(id) {
            this.selectedEdgeId = id;
            this.selectedNodeId = null;
        },

        dragStart(event, nodeId) {
            const node = this.nodes.find((item) => item.node_id === nodeId);

            if (! node) {
                return;
            }

            const point = this.eventPoint(event);
            this.dragging = {
                nodeId,
                offsetX: point.x - node.x,
                offsetY: point.y - node.y,
            };
        },

        dragMove(event) {
            if (! this.dragging) {
                return;
            }

            const node = this.nodes.find((item) => item.node_id === this.dragging.nodeId);

            if (! node) {
                return;
            }

            const point = this.eventPoint(event);
            node.x = Math.max(12, point.x - this.dragging.offsetX);
            node.y = Math.max(12, point.y - this.dragging.offsetY);
        },

        dragEnd() {
            if (! this.dragging) {
                return;
            }

            this.dragging = null;
            this.sync();
        },

        portClick(kind, nodeId, portKey = '') {
            if (kind === 'output') {
                this.connecting = { from: nodeId, fromPort: portKey, mode: 'click' };
                this.tempPoint = this.outputPoint(nodeId, portKey);
                return;
            }

            this.finishConnection(nodeId, portKey);
        },

        startConnection(event, nodeId, mode = 'drag', portKey = '') {
            this.connecting = { from: nodeId, fromPort: portKey, mode };
            this.tempPoint = this.eventPoint(event);
        },

        edgeMove(event) {
            if (! this.connecting) {
                return;
            }

            this.tempPoint = this.eventPoint(event);
        },

        finishConnection(nodeId, portKey = '') {
            if (! this.connecting || this.connecting.from === nodeId) {
                return;
            }

            const exists = this.edges.some((edge) => edge.from === this.connecting.from && edge.to === nodeId && edge.fromPort === this.connecting.fromPort && edge.toPort === portKey);

            if (! exists) {
                this.edges.push({
                    id: this.edgeId(),
                    from: this.connecting.from,
                    to: nodeId,
                    fromPort: this.connecting.fromPort || '',
                    toPort: portKey || '',
                    label: `${this.connecting.fromPort || 'OUT'} → ${portKey || 'IN'}`,
                });
            }

            this.connecting = null;
            this.sync();
        },

        edgeEnd() {
            if (this.connecting?.mode === 'drag') {
                this.connecting = null;
            }
        },

        removeSelected() {
            if (this.selectedNodeId) {
                this.deleteNode(this.selectedNodeId);
                return;
            }

            if (this.selectedEdgeId) {
                this.edges = this.edges.filter((edge) => edge.id !== this.selectedEdgeId);
                this.selectedEdgeId = null;
                this.sync();
            }
        },

        deleteNode(id) {
            this.nodes = this.nodes.filter((node) => node.node_id !== id);
            this.edges = this.edges.filter((edge) => edge.from !== id && edge.to !== id);

            if (this.selectedNodeId === id) {
                this.selectedNodeId = null;
            }

            this.sync();
        },

        resetCanvas() {
            this.nodes = [];
            this.edges = [];
            this.selectedNodeId = null;
            this.selectedEdgeId = null;
            this.sync();
        },

        autoLayout() {
            this.nodes
                .sort((a, b) => Number(a.order || 0) - Number(b.order || 0))
                .forEach((node, index) => {
                    node.x = 120 + (index % 4) * 400;
                    node.y = 120 + Math.floor(index / 4) * 360;
                    node.order = index + 1;
                });

            this.sync();
        },

        nodeBorder(type) {
            return {
                input: 'border-gray-600',
                language_model: 'border-blue-500/50',
                chat_prompt: 'border-sky-500/50',
                checkpoint_loader: 'border-pink-500/50',
                lora_loader: 'border-amber-500/50',
                clip_text_encode: 'border-indigo-500/50',
                load_image: 'border-lime-500/50',
                save_image: 'border-green-500/50',
                empty_latent_image: 'border-fuchsia-500/50',
                vae_encode: 'border-purple-500/50',
                vae_decode: 'border-purple-400/50',
                k_sampler: 'border-orange-500/50',
                controlnet_loader: 'border-red-500/50',
                controlnet_apply: 'border-red-400/50',
                upscale_model_loader: 'border-teal-500/50',
                image_upscale: 'border-teal-400/50',
                image_scale: 'border-lime-400/50',
                preview_image: 'border-green-400/50',
                mask_load: 'border-rose-400/50',
                search_model: 'border-cyan-500/50',
                rank_model: 'border-violet-500/50',
                reply_model: 'border-emerald-500/50',
                output: 'border-green-500/50',
            }[type] || 'border-gray-700';
        },

        nodeBadge(type) {
            return {
                input: 'bg-gray-700 text-gray-100',
                language_model: 'bg-blue-500/20 text-blue-100',
                chat_prompt: 'bg-sky-500/20 text-sky-100',
                checkpoint_loader: 'bg-pink-500/20 text-pink-100',
                lora_loader: 'bg-amber-500/20 text-amber-100',
                clip_text_encode: 'bg-indigo-500/20 text-indigo-100',
                load_image: 'bg-lime-500/20 text-lime-100',
                save_image: 'bg-green-500/20 text-green-100',
                empty_latent_image: 'bg-fuchsia-500/20 text-fuchsia-100',
                vae_encode: 'bg-purple-500/20 text-purple-100',
                vae_decode: 'bg-purple-400/20 text-purple-100',
                k_sampler: 'bg-orange-500/20 text-orange-100',
                controlnet_loader: 'bg-red-500/20 text-red-100',
                controlnet_apply: 'bg-red-400/20 text-red-100',
                upscale_model_loader: 'bg-teal-500/20 text-teal-100',
                image_upscale: 'bg-teal-400/20 text-teal-100',
                image_scale: 'bg-lime-400/20 text-lime-100',
                preview_image: 'bg-green-400/20 text-green-100',
                mask_load: 'bg-rose-400/20 text-rose-100',
                search_model: 'bg-cyan-500/20 text-cyan-100',
                rank_model: 'bg-violet-500/20 text-violet-100',
                reply_model: 'bg-emerald-500/20 text-emerald-100',
                output: 'bg-green-500/20 text-green-100',
            }[type] || 'bg-gray-700 text-gray-100';
        },

        inputPoint(nodeId, portKey = '') {
            const node = this.nodes.find((item) => item.node_id === nodeId);

            if (! node) {
                return { x: 0, y: 0 };
            }

            const index = Math.max(0, this.inputPorts(node.type).findIndex((port) => port.key === portKey));

            return { x: node.x, y: node.y + 86 + index * 24 };
        },

        outputPoint(nodeId, portKey = '') {
            const node = this.nodes.find((item) => item.node_id === nodeId);

            if (! node) {
                return { x: 0, y: 0 };
            }

            const index = Math.max(0, this.outputPorts(node.type).findIndex((port) => port.key === portKey));

            return { x: node.x + 320, y: node.y + 86 + index * 24 };
        },

        edgePath(edge) {
            const start = this.outputPoint(edge.from, edge.fromPort || '');
            const end = this.inputPoint(edge.to, edge.toPort || '');

            if (! start.x && ! end.x) {
                return '';
            }

            const curve = Math.max(90, Math.abs(end.x - start.x) / 2);
            return `M ${start.x} ${start.y} C ${start.x + curve} ${start.y}, ${end.x - curve} ${end.y}, ${end.x} ${end.y}`;
        },

        temporaryEdgePath() {
            if (! this.connecting) {
                return '';
            }

            const start = this.outputPoint(this.connecting.from, this.connecting.fromPort || '');
            const end = this.tempPoint;
            const curve = Math.max(90, Math.abs(end.x - start.x) / 2);

            return `M ${start.x} ${start.y} C ${start.x + curve} ${start.y}, ${end.x - curve} ${end.y}, ${end.x} ${end.y}`;
        },

        eventPoint(event) {
            const rect = this.$refs.viewport.getBoundingClientRect();

            return {
                x: (event.clientX - rect.left + this.$refs.viewport.scrollLeft) / this.zoom,
                y: (event.clientY - rect.top + this.$refs.viewport.scrollTop) / this.zoom,
            };
        },

        sync() {
            this.nodes.forEach((node, index) => {
                node.order = index + 1;
            });

            this.$wire.set(this.nodesStatePath, this.nodes, false);
            this.$wire.set(this.edgesStatePath, this.edges, false);
        },
    }));
</script>
