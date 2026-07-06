<?php

namespace App\Support;

class FontAwesome
{
    private const STYLES = [
        'solid' => 'fa-solid',
        'regular' => 'fa-regular',
        'brands' => 'fa-brands',
    ];

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
