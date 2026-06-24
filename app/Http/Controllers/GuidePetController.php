<?php

namespace App\Http\Controllers;

use App\Models\AiWorkflow;
use App\Models\SiteSetting;
use App\Services\AiWorkflowRunner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class GuidePetController extends Controller
{
    public function chat(Request $request, AiWorkflowRunner $workflowRunner): JsonResponse
    {
        $settings = SiteSetting::query()->first();

        if (! $settings?->guide_pet_enabled) {
            return response()->json(['message' => '导购助手当前未启用。'], 403);
        }

        $data = $request->validate([
            'message' => ['required', 'string', 'max:1200'],
            'page' => ['nullable', 'array'],
            'page.url' => ['nullable', 'string', 'max:500'],
            'page.title' => ['nullable', 'string', 'max:200'],
            'page.type' => ['nullable', 'string', 'max:80'],
            'page.summary' => ['nullable', 'string', 'max:1600'],
        ]);

        $message = trim((string) $data['message']);
        $page = $this->pageContext($data['page'] ?? []);

        if (filled($settings->guide_ai_workflow_slug)) {
            $workflow = AiWorkflow::query()
                ->where('slug', $settings->guide_ai_workflow_slug)
                ->where('is_active', true)
                ->first();

            if ($workflow) {
                $result = $workflowRunner->run($workflow, [
                    'message' => $message,
                    'prompt' => $this->prompt($settings, $message, $page),
                    'page' => $page,
                ]);

                return response()->json([
                    'message' => $this->normalizeReply($result['output'] ?? null) ?: $this->fallbackReply($message, $page),
                    'source' => 'workflow',
                    'workflow' => $workflow->slug,
                ]);
            }
        }

        $remote = $this->chatWithConfiguredModel($settings, $message, $page);

        if ($remote !== null) {
            return response()->json([
                'message' => $remote,
                'source' => 'remote',
            ]);
        }

        return response()->json([
            'message' => $this->fallbackReply($message, $page),
            'source' => 'local-fallback',
        ]);
    }

    /**
     * @param  array<string, mixed>  $page
     * @return array<string, string>
     */
    private function pageContext(array $page): array
    {
        return [
            'url' => Str::limit(trim((string) ($page['url'] ?? '')), 500, ''),
            'title' => Str::limit(trim((string) ($page['title'] ?? '')), 200, ''),
            'type' => Str::limit(trim((string) ($page['type'] ?? 'storefront')), 80, ''),
            'summary' => Str::limit(trim((string) ($page['summary'] ?? '')), 1600, ''),
        ];
    }

    /**
     * @param  array<string, string>  $page
     */
    private function chatWithConfiguredModel(SiteSetting $settings, string $message, array $page): ?string
    {
        $endpoint = trim((string) $settings->guide_pet_api_endpoint);
        $apiKey = trim((string) $settings->guide_pet_api_key);
        $model = trim((string) $settings->guide_pet_model);

        if ($endpoint === '' || $apiKey === '' || $model === '') {
            return null;
        }

        try {
            $response = Http::timeout(45)
                ->connectTimeout(15)
                ->acceptJson()
                ->withHeaders([
                    'Authorization' => 'Bearer '.$apiKey,
                    'X-API-Key' => $apiKey,
                ])
                ->post($this->apiBaseUrl($endpoint).'/chat/completions', [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'system', 'content' => $this->systemPrompt($settings)],
                        ['role' => 'user', 'content' => $this->prompt($settings, $message, $page)],
                    ],
                    'temperature' => 0.6,
                ]);

            if ($response->failed()) {
                return null;
            }

            return $this->normalizeReply(data_get($response->json(), 'choices.0.message.content'));
        } catch (Throwable) {
            return null;
        }
    }

    private function apiBaseUrl(string $endpoint): string
    {
        $endpoint = rtrim(trim($endpoint), '/');

        return preg_replace('#/(models|responses|chat/completions)$#', '', $endpoint) ?: $endpoint;
    }

    /**
     * @param  array<string, string>  $page
     */
    private function prompt(SiteSetting $settings, string $message, array $page): string
    {
        return implode("\n", array_filter([
            $this->systemPrompt($settings),
            '当前页面：'.($page['title'] ?: '未知页面'),
            '页面类型：'.($page['type'] ?: 'storefront'),
            '页面链接：'.($page['url'] ?: '未知'),
            $page['summary'] !== '' ? '页面摘要：'.$page['summary'] : null,
            '用户问题：'.$message,
        ]));
    }

    private function systemPrompt(SiteSetting $settings): string
    {
        $configured = trim((string) $settings->guide_pet_system_prompt);

        return $configured !== ''
            ? $configured
            : '你是网站前台的 AI 导购助手。回答要简短、友好、基于当前页面内容帮助用户找到商品、订单、客服、论坛或 AI 功能。不要编造价格、库存和承诺。';
    }

    private function normalizeReply(mixed $value): string
    {
        if (is_array($value) || is_object($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return Str::limit(trim((string) $value), 1200, '');
    }

    /**
     * @param  array<string, string>  $page
     */
    private function fallbackReply(string $message, array $page): string
    {
        $type = $page['type'] ?: 'storefront';

        return match ($type) {
            'product' => '我可以帮你看当前商品的规格、库存、价格和购买流程。你也可以问我“这个怎么下单”或“有什么类似商品”。',
            'cart' => '你现在在购物车页面，可以确认商品数量、优惠码和结算方式。遇到付款问题时可以切换付款方式或联系客服。',
            'checkout' => '这里是结算页面，请先确认地址、商品和付款方式。付款码加载慢或支付失败时，可以使用备用方式或联系客服。',
            'forum' => '这里是论坛页面，可以浏览帖子、发布内容或搜索交流话题。需要我帮你找入口的话，直接说想做什么就行。',
            'ai' => '这里是 AI 页面，可以进行聊天或生图。你可以告诉我想生成什么，或问我怎么设置模型和参考图。',
            'support' => '这里是客服页面，可以发起会话或提交工单。你可以把订单号和问题描述发给客服。',
            default => '我在这里，可以帮你找商品、说明购买流程、指路到订单/客服/论坛/AI 页面。你刚才问的是：“'.$message.'”。',
        };
    }
}
