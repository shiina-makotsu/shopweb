<?php

namespace App\Support;

use Illuminate\Support\HtmlString;

class LoadingPage
{
    /**
     * @param array<string, mixed>|null $config
     * @return array<string, mixed>
     */
    public static function normalize(?array $config): array
    {
        $defaults = self::defaults();
        $config = is_array($config) ? $config : [];
        $components = collect($config['components'] ?? [])
            ->filter(fn ($component): bool => is_array($component))
            ->map(fn (array $component): array => self::normalizeComponent($component))
            ->values()
            ->all();

        return [
            'title' => self::text($config['title'] ?? null, $defaults['title']),
            'subtitle' => self::text($config['subtitle'] ?? null, $defaults['subtitle']),
            'status_text' => self::text($config['status_text'] ?? null, $defaults['status_text']),
            'done_text' => self::text($config['done_text'] ?? null, $defaults['done_text']),
            'skip_text' => self::text($config['skip_text'] ?? null, $defaults['skip_text']),
            'symbol' => in_array(($config['symbol'] ?? null), array_keys(self::symbolOptions()), true) ? $config['symbol'] : $defaults['symbol'],
            'progress_style' => in_array(($config['progress_style'] ?? null), array_keys(self::progressStyleOptions()), true) ? $config['progress_style'] : $defaults['progress_style'],
            'layout_columns' => max(4, min(12, (int) ($config['layout_columns'] ?? $defaults['layout_columns']))),
            'components' => $components !== [] ? $components : $defaults['components'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'title' => '正在为你准备页面',
            'subtitle' => '首次访问需要加载站点配置、菜单和商品缓存。请稍等片刻，准备好后会自动进入页面。',
            'status_text' => '正在初始化访问环境...',
            'done_text' => '准备完成，正在进入页面...',
            'skip_text' => '直接进入',
            'symbol' => 'fishcake',
            'progress_style' => 'soft_gradient',
            'layout_columns' => 6,
            'components' => [
                ['type' => 'symbol', 'label' => '首次访问初始化', 'x' => 1, 'y' => 1, 'w' => 6, 'h' => 1, 'align' => 'left'],
                ['type' => 'title', 'x' => 1, 'y' => 2, 'w' => 6, 'h' => 1, 'align' => 'left'],
                ['type' => 'subtitle', 'x' => 1, 'y' => 3, 'w' => 6, 'h' => 1, 'align' => 'left'],
                ['type' => 'progress', 'x' => 1, 'y' => 4, 'w' => 6, 'h' => 1, 'align' => 'stretch'],
                ['type' => 'status', 'x' => 1, 'y' => 5, 'w' => 6, 'h' => 1, 'align' => 'left'],
                ['type' => 'steps', 'x' => 1, 'y' => 6, 'w' => 6, 'h' => 1, 'align' => 'left'],
                ['type' => 'skip', 'x' => 1, 'y' => 7, 'w' => 6, 'h' => 1, 'align' => 'left'],
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function componentOptions(): array
    {
        return [
            'symbol' => '加载符号',
            'title' => '标题文本',
            'subtitle' => '说明文本',
            'progress' => '进度条',
            'status' => '状态文本',
            'steps' => '步骤列表',
            'skip' => '直接进入链接',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function symbolOptions(): array
    {
        return [
            'fishcake' => '鱼板 Emoji（🍥）',
            'ring' => '圆环旋转',
            'pulse' => '圆点呼吸',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function progressStyleOptions(): array
    {
        return [
            'soft_gradient' => '浅蓝粉渐变',
            'solid_blue' => '浅蓝色',
            'solid_pink' => '浅粉色',
            'minimal' => '极简灰色',
        ];
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function render(array $config, string $target, string $prepareUrl, string $storeName): HtmlString
    {
        $config = self::normalize($config);
        $targetAttribute = e($target);
        $targetJson = json_encode($target, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
        $prepareUrlJson = json_encode($prepareUrl, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
        $title = e($storeName);
        $body = self::bodyHtml($config, $targetAttribute, true);
        $css = self::css($config);
        $doneTextJson = json_encode($config['done_text'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

        return new HtmlString(<<<HTML
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>正在准备 - {$title}</title>
    <style>
        {$css}
    </style>
</head>
<body>
    {$body}
    <script>
        const target = {$targetJson};
        const status = document.getElementById('loading-status');
        const progress = document.querySelector('[data-loading-progress]');
        const percent = document.querySelector('[data-loading-percent]');
        let currentProgress = 14;
        const setProgress = (next) => {
            currentProgress = Math.max(currentProgress, Math.min(100, next));
            if (progress) progress.style.width = currentProgress + '%';
            if (percent) percent.textContent = Math.round(currentProgress) + '%';
        };
        const skip = document.querySelector('[data-loading-skip]');
        const timer = setInterval(() => {
            if (currentProgress < 88) setProgress(currentProgress + Math.max(1, (92 - currentProgress) * 0.12));
        }, 180);
        const minimumDelay = new Promise(resolve => setTimeout(resolve, 900));
        const prepare = fetch({$prepareUrlJson}, { headers: { "Accept": "application/json" }, credentials: "same-origin" })
            .then(response => response.ok ? response.json() : null)
            .catch(() => null);
        Promise.allSettled([minimumDelay, prepare]).then(() => {
            clearInterval(timer);
            setProgress(100);
            if (status) status.textContent = {$doneTextJson};
            setTimeout(() => window.location.replace(target), 180);
        });
        setTimeout(() => {
            clearInterval(timer);
            setProgress(100);
            if (skip) skip.hidden = false;
            window.location.replace(target);
        }, 5000);
    </script>
</body>
</html>
HTML);
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function preview(array $config): HtmlString
    {
        $config = self::normalize($config);

        return new HtmlString('<style>'.self::css($config).'</style>'.self::bodyHtml($config, '#', false));
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function bodyHtml(array $config, string $targetAttribute, bool $fullPage): string
    {
        $components = collect($config['components'])
            ->map(fn (array $component): string => self::componentHtml($component, $config, $targetAttribute))
            ->implode('');
        $class = $fullPage ? 'shop-loading-body' : 'shop-loading-body shop-loading-preview';
        $columns = (int) $config['layout_columns'];

        return <<<HTML
<div class="{$class}">
    <main class="shop-loading-card" style="--loading-columns: {$columns};">
        {$components}
    </main>
</div>
HTML;
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function css(array $config): string
    {
        return <<<'CSS'
.shop-loading-body { color-scheme:light; --primary:#2563eb; --accent:#f43f5e; --ink:#0f172a; --muted:#64748b; --line:#cbd5e1; --paper:#ffffff; --soft:#f8fafc; min-height:100vh; margin:0; display:grid; place-items:center; background:linear-gradient(135deg,#eef6ff 0%,#fff 48%,#fff1f4 100%); color:var(--ink); font-family:ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif; }
.shop-loading-body, .shop-loading-body * { box-sizing:border-box; }
.shop-loading-preview { min-height:560px; border:1px dashed #bfdbfe; }
.shop-loading-card { width:min(92vw,560px); display:grid; grid-template-columns:repeat(var(--loading-columns), minmax(0, 1fr)); gap:12px; border:1px solid var(--line); background:rgba(255,255,255,.94); padding:28px; box-shadow:0 22px 60px rgba(15,23,42,.12); }
.loading-item { min-width:0; align-self:center; }
.align-left { justify-self:start; text-align:left; }
.align-center { justify-self:center; text-align:center; }
.align-right { justify-self:end; text-align:right; }
.align-stretch { justify-self:stretch; }
.loader { display:flex; align-items:center; gap:14px; }
.loader small { color:#1d4ed8; font-size:12px; font-weight:700; letter-spacing:.02em; }
.fishcake { display:inline-flex; width:58px; height:58px; flex:0 0 auto; align-items:center; justify-content:center; border:3px solid #bfdbfe; border-radius:999px; background:#fff; box-shadow:0 10px 26px rgba(37,99,235,.16); font-size:38px; line-height:1; animation:spin 1.2s linear infinite; }
.ring { width:58px; height:58px; border:5px solid #bfdbfe; border-top-color:#f43f5e; border-right-color:#60a5fa; border-radius:999px; animation:spin 1s linear infinite; }
.pulse { width:58px; height:58px; border-radius:999px; background:radial-gradient(circle,#f43f5e 0 22%,#fff 23% 44%,#60a5fa 45% 68%,#fff 69% 100%); animation:pulse 1.2s ease-in-out infinite; }
.shop-loading-card h1 { margin:0; font-size:24px; line-height:1.25; overflow-wrap:anywhere; }
.shop-loading-card p { margin:0; color:var(--muted); line-height:1.7; font-size:14px; overflow-wrap:anywhere; }
.progress-wrap { display:grid; gap:6px; }
.bar { height:8px; overflow:hidden; border:1px solid #dbeafe; background:#f8fafc; }
.bar span { display:block; height:100%; width:14%; transition:width .28s ease; }
.progress-soft-gradient span { background:linear-gradient(90deg,#cfe9ff 0%,#f9d7e1 100%); opacity:.9; }
.progress-solid-blue span { background:#bfdbfe; opacity:.94; }
.progress-solid-pink span { background:#f9c8d6; opacity:.94; }
.progress-minimal { border-color:#e2e8f0; background:#f8fafc; }
.progress-minimal span { background:#cbd5e1; opacity:.82; }
.progress-percent { justify-self:end; color:#64748b; font-size:12px; font-variant-numeric:tabular-nums; line-height:1; }
.steps { display:grid; gap:8px; color:#334155; font-size:13px; }
.steps div { display:flex; gap:8px; align-items:center; }
.dot { width:7px; height:7px; border-radius:99px; background:var(--primary); flex:0 0 auto; }
.skip { display:inline-flex; color:#1d4ed8; text-decoration:none; font-size:13px; font-weight:600; }
@keyframes spin { to { transform:rotate(360deg); } }
@keyframes pulse { 0%,100% { transform:scale(.92); opacity:.78; } 50% { transform:scale(1); opacity:1; } }
@media (prefers-reduced-motion: reduce) { .fishcake, .ring, .pulse { animation-duration:2.4s; } .bar span { transition-duration:.8s; } }
.dark .shop-loading-preview { color-scheme:dark; --ink:#e5e7eb; --muted:#94a3b8; --line:#334155; --paper:#111827; --soft:#0f172a; background:linear-gradient(135deg,#07111f 0%,#0b1120 48%,#1f1424 100%); border-color:#334155; color:var(--ink); }
.dark .shop-loading-preview .shop-loading-card { border-color:#334155; background:rgba(17,24,39,.94); color:var(--ink); box-shadow:0 22px 60px rgba(0,0,0,.3); }
.dark .shop-loading-preview :is(h1,p,.steps) { color:var(--ink); }
.dark .shop-loading-preview :is(.loader small,.skip) { color:#93c5fd; }
.dark .shop-loading-preview :is(.fishcake,.pulse) { border-color:#334155; background:#0f172a; box-shadow:0 10px 26px rgba(96,165,250,.18); }
.dark .shop-loading-preview .ring { border-color:#334155; border-top-color:#f472b6; border-right-color:#60a5fa; }
.dark .shop-loading-preview .bar { border-color:#334155; background:#0f172a; }
.dark .shop-loading-preview .progress-percent { color:var(--muted); }
CSS;
    }

    /**
     * @param array<string, mixed> $component
     * @param array<string, mixed> $config
     */
    private static function componentHtml(array $component, array $config, string $targetAttribute): string
    {
        $type = $component['type'];
        $align = 'align-'.$component['align'];
        $style = self::gridStyle($component);
        $label = e($component['label'] ?? '首次访问初始化');

        $html = match ($type) {
            'symbol' => '<div class="loader">'.self::symbolHtml((string) $config['symbol']).'<small>'.$label.'</small></div>',
            'title' => '<h1>'.e($config['title']).'</h1>',
            'subtitle' => '<p>'.e($config['subtitle']).'</p>',
            'progress' => self::progressHtml((string) $config['progress_style']),
            'status' => '<p id="loading-status">'.e($config['status_text']).'</p>',
            'steps' => '<div class="steps" aria-label="加载步骤"><div><span class="dot"></span><span>读取站点配置</span></div><div><span class="dot"></span><span>预热商品与菜单缓存</span></div><div><span class="dot"></span><span>进入目标页面</span></div></div>',
            'skip' => '<a class="skip" href="'.$targetAttribute.'" data-loading-skip hidden>'.e($config['skip_text']).'</a>',
            default => '',
        };

        return '<div class="loading-item '.$align.'" style="'.$style.'">'.$html.'</div>';
    }

    private static function symbolHtml(string $symbol): string
    {
        return match ($symbol) {
            'fishcake' => '<span class="fishcake" role="img" aria-label="鱼板加载图标">🍥</span>',
            'ring' => '<span class="ring" role="img" aria-label="正在加载"></span>',
            'pulse' => '<span class="pulse" role="img" aria-label="正在加载"></span>',
            default => '<span class="fishcake" role="img" aria-label="鱼板加载图标">🍥</span>',
        };
    }

    private static function progressHtml(string $style): string
    {
        $style = str_replace('_', '-', $style);

        return '<div class="progress-wrap"><div class="bar progress-'.$style.'" aria-hidden="true"><span data-loading-progress></span></div><span class="progress-percent" data-loading-percent>14%</span></div>';
    }

    /**
     * @param array<string, mixed> $component
     */
    private static function gridStyle(array $component): string
    {
        return sprintf(
            'grid-column:%d / span %d;grid-row:%d / span %d;',
            (int) $component['x'],
            (int) $component['w'],
            (int) $component['y'],
            (int) $component['h'],
        );
    }

    /**
     * @param array<string, mixed> $component
     * @return array<string, mixed>
     */
    private static function normalizeComponent(array $component): array
    {
        return [
            'type' => in_array(($component['type'] ?? null), array_keys(self::componentOptions()), true) ? $component['type'] : 'title',
            'label' => self::text($component['label'] ?? null, '首次访问初始化'),
            'x' => max(1, min(12, (int) ($component['x'] ?? 1))),
            'y' => max(1, min(12, (int) ($component['y'] ?? 1))),
            'w' => max(1, min(12, (int) ($component['w'] ?? 6))),
            'h' => max(1, min(4, (int) ($component['h'] ?? 1))),
            'align' => in_array(($component['align'] ?? null), ['left', 'center', 'right', 'stretch'], true) ? $component['align'] : 'left',
        ];
    }

    private static function text(mixed $value, string $fallback): string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : $fallback;
    }
}
