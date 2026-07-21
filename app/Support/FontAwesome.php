<?php

namespace App\Support;

class FontAwesome
{
    private const STYLES = [
        'solid' => 'fa-solid',
        'regular' => 'fa-regular',
        'brands' => 'fa-brands',
    ];

    public static function contactIconOptions(): array
    {
        return [
            'fa-solid fa-comments' => '通用聊天',
            'fa-solid fa-headset' => '客服',
            'fa-solid fa-envelope' => '电子邮件',
            'fa-solid fa-phone' => '电话',
            'fa-solid fa-link' => '链接',
            'fa-brands fa-weixin' => '微信',
            'fa-brands fa-qq' => 'QQ',
            'fa-brands fa-telegram' => 'Telegram',
            'fa-brands fa-discord' => 'Discord',
            'fa-brands fa-whatsapp' => 'WhatsApp',
            'fa-brands fa-line' => 'LINE',
            'fa-brands fa-weibo' => '微博',
            'fa-brands fa-facebook' => 'Facebook',
            'fa-brands fa-instagram' => 'Instagram',
            'fa-brands fa-x-twitter' => 'X / Twitter',
        ];
    }

    public static function normalizeClasses(mixed $classes): string
    {
        $classes = strtolower(trim((string) $classes));

        return preg_match('/^fa-(?:solid|regular|brands) fa-[a-z0-9][a-z0-9-]*$/', $classes) === 1
            ? $classes
            : '';
    }

    public static function icon(string $name, string $style = 'solid', ?string $label = null, string $extraClass = ''): string
    {
        $name = self::normalizeName($name);
        $styleClass = self::STYLES[strtolower($style)] ?? self::STYLES['solid'];

        if ($name === '') {
            return '';
        }

        $classes = trim($styleClass.' fa-'.$name.' fa-fw '.$extraClass);
        $label = trim((string) $label);
        $accessibility = $label !== ''
            ? ' role="img" aria-label="'.e($label).'"'
            : ' aria-hidden="true"';

        return '<i class="'.e($classes).'"'.$accessibility.'></i>';
    }

    public static function extractShortcodes(?string $markdown): array
    {
        $icons = [];
        $prepared = preg_replace_callback(
            '/\[fa:(?:(solid|regular|brands):)?([a-z0-9][a-z0-9-]*)(?:\s+([^\]]{1,80}))?\]/i',
            function (array $matches) use (&$icons): string {
                $key = '@@SHOPWEB_FA_ICON_'.count($icons).'@@';
                $icons[$key] = self::icon($matches[2], $matches[1] ?? 'solid', $matches[3] ?? null, 'markdown-icon');

                return $key;
            },
            $markdown ?? '',
        );

        return [$prepared ?? ($markdown ?? ''), $icons];
    }

    private static function normalizeName(string $name): string
    {
        $name = strtolower(trim($name));

        return preg_match('/^[a-z0-9][a-z0-9-]*$/', $name) === 1 ? $name : '';
    }
}
