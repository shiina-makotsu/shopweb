<?php

namespace App\Services;

use App\Models\AiWorkflow;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class AiWorkflowRunner
{
    /**
     * Execute a saved workflow definition and return a normalized trace.
     *
     * Real model calls are intentionally routed through this service later, so
     * support AI, guide AI, chat and image generation can share one workflow
     * contract instead of each feature owning a separate chain.
     *
     * @param  array<string, mixed>  $input
     * @return array{workflow:string, output:mixed, steps:array<int, array<string, mixed>>}
     */
    public function run(AiWorkflow $workflow, array $input = []): array
    {
        $nodes = $this->orderedNodes($workflow);
        $context = [
            'input' => $input,
            'text' => (string) ($input['prompt'] ?? $input['message'] ?? ''),
            'images' => Arr::wrap($input['images'] ?? []),
            'models' => [],
            'loras' => [],
        ];
        $steps = [];

        foreach ($nodes as $node) {
            $result = $this->executeNode($node, $context);
            $context = array_replace_recursive($context, $result['context']);
            $steps[] = [
                'id' => $node['node_id'] ?? null,
                'type' => $node['type'] ?? null,
                'title' => $node['title'] ?? null,
                'result' => $result['summary'],
            ];
        }

        return [
            'workflow' => $workflow->slug,
            'output' => $context['output'] ?? $context['text'],
            'steps' => $steps,
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function orderedNodes(AiWorkflow $workflow): Collection
    {
        return collect($workflow->nodes ?? [])
            ->filter(fn (mixed $node): bool => is_array($node))
            ->sortBy(fn (array $node): int => (int) ($node['order'] ?? 0))
            ->values();
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $context
     * @return array{context: array<string, mixed>, summary: string}
     */
    private function executeNode(array $node, array $context): array
    {
        $type = (string) ($node['type'] ?? 'language_model');
        $promptTemplate = (string) ($node['prompt_template'] ?? '');
        $model = (string) ($node['model_id'] ?? '');

        return match ($type) {
            'input' => [
                'context' => $context,
                'summary' => '读取用户输入',
            ],
            'lora_loader' => [
                'context' => [
                    'loras' => array_values(array_unique([
                        ...Arr::wrap($context['loras'] ?? []),
                        ...Arr::wrap($node['lora_name'] ?? $node['lora_ids'] ?? []),
                    ])),
                ],
                'summary' => '加载 LoRA：'.implode('、', Arr::wrap($node['lora_name'] ?? $node['lora_ids'] ?? [])),
            ],
            'checkpoint_loader', 'image_model' => [
                'context' => [
                    'models' => array_merge($context['models'] ?? [], [$model]),
                    'checkpoint' => $model,
                ],
                'summary' => '加载画图模型：'.($model ?: '未指定'),
            ],
            'clip_text_encode', 'chat_prompt' => [
                'context' => [
                    'conditioning' => $this->renderPrompt($promptTemplate, $context) ?: ($context['text'] ?? ''),
                    'text' => $this->renderPrompt($promptTemplate, $context) ?: ($context['text'] ?? ''),
                ],
                'summary' => '编码提示词',
            ],
            'load_image' => [
                'context' => [
                    'images' => array_values(array_filter([
                        ...Arr::wrap($context['images'] ?? []),
                        $node['image_path'] ?? null,
                    ])),
                ],
                'summary' => '加载图片：'.($node['image_path'] ?? '未指定'),
            ],
            'empty_latent_image' => [
                'context' => [
                    'latent' => [
                        'width' => (int) ($node['width'] ?? 1024),
                        'height' => (int) ($node['height'] ?? 1024),
                        'batch_size' => (int) ($node['batch_size'] ?? 1),
                    ],
                ],
                'summary' => '创建空潜空间',
            ],
            'vae_encode' => [
                'context' => [
                    'latent' => [
                        'source' => 'image',
                        'images' => Arr::wrap($context['images'] ?? []),
                    ],
                ],
                'summary' => 'VAE 编码',
            ],
            'k_sampler' => [
                'context' => [
                    'latent' => array_merge(Arr::wrap($context['latent'] ?? []), [
                        'sampled' => true,
                        'seed' => (int) ($node['seed'] ?? 0),
                        'steps' => (int) ($node['steps'] ?? 20),
                        'cfg' => (float) ($node['cfg'] ?? 7),
                        'sampler' => $node['sampler_name'] ?? 'euler',
                        'scheduler' => $node['scheduler'] ?? 'normal',
                        'denoise' => (float) ($node['denoise'] ?? 1),
                    ]),
                ],
                'summary' => 'K 采样',
            ],
            'controlnet_loader' => [
                'context' => [
                    'control_net' => $model,
                ],
                'summary' => '加载 ControlNet：'.($model ?: '未指定'),
            ],
            'controlnet_apply' => [
                'context' => [
                    'conditioning' => array_merge(Arr::wrap($context['conditioning'] ?? []), [
                        'control_net' => $context['control_net'] ?? null,
                        'images' => Arr::wrap($context['images'] ?? []),
                    ]),
                ],
                'summary' => '应用 ControlNet',
            ],
            'upscale_model_loader' => [
                'context' => [
                    'upscale_model' => $model,
                ],
                'summary' => '加载放大模型：'.($model ?: '未指定'),
            ],
            'image_upscale' => [
                'context' => [
                    'output' => [
                        'kind' => 'image',
                        'upscale_model' => $context['upscale_model'] ?? null,
                        'upscale_by' => (float) ($node['upscale_by'] ?? 2),
                        'images' => Arr::wrap($context['images'] ?? $context['output'] ?? []),
                    ],
                ],
                'summary' => '图像放大',
            ],
            'image_scale' => [
                'context' => [
                    'output' => [
                        'kind' => 'image',
                        'width' => (int) ($node['width'] ?? 1024),
                        'height' => (int) ($node['height'] ?? 1024),
                        'images' => Arr::wrap($context['images'] ?? $context['output'] ?? []),
                    ],
                ],
                'summary' => '图像缩放',
            ],
            'preview_image' => [
                'context' => [
                    'preview' => Arr::wrap($context['images'] ?? $context['output'] ?? []),
                ],
                'summary' => '预览图片',
            ],
            'mask_load' => [
                'context' => [
                    'mask' => $node['image_path'] ?? null,
                ],
                'summary' => '加载遮罩：'.($node['image_path'] ?? '未指定'),
            ],
            'vae_decode' => [
                'context' => [
                    'output' => [
                        'kind' => 'image',
                        'model' => $context['checkpoint'] ?? null,
                        'latent' => $context['latent'] ?? null,
                        'loras' => Arr::wrap($context['loras'] ?? []),
                    ],
                ],
                'summary' => 'VAE 解码',
            ],
            'save_image' => [
                'context' => [
                    'output' => array_merge(Arr::wrap($context['output'] ?? []), [
                        'save_prefix' => $node['save_prefix'] ?? 'ai/output',
                    ]),
                ],
                'summary' => '保存图片',
            ],
            'search_model', 'rank_model', 'reply_model', 'language_model' => [
                'context' => [
                    'models' => array_merge($context['models'] ?? [], [$model]),
                    'text' => $this->renderPrompt($promptTemplate, $context) ?: ($context['text'] ?? ''),
                    'output' => $this->renderPrompt($promptTemplate, $context) ?: ($context['text'] ?? ''),
                ],
                'summary' => '准备语言节点：'.($model ?: '未指定'),
            ],
            'output' => [
                'context' => [
                    'output' => $context['output'] ?? $context['text'] ?? null,
                ],
                'summary' => '输出结果',
            ],
            default => [
                'context' => $context,
                'summary' => '跳过未知节点：'.$type,
            ],
        };
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function renderPrompt(string $template, array $context): string
    {
        if ($template === '') {
            return '';
        }

        return strtr($template, [
            '{{input}}' => (string) ($context['text'] ?? ''),
            '{{prompt}}' => (string) ($context['text'] ?? ''),
        ]);
    }
}
