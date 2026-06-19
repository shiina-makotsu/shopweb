<?php

namespace App\Support;

class Text
{
    /**
     * Repair text that was saved after UTF-8 bytes were accidentally decoded as GBK.
     */
    public static function display(?string $value, ?string $fallback = null): string
    {
        $text = trim((string) ($value ?? $fallback ?? ''));

        if ($text === '') {
            return trim((string) ($fallback ?? ''));
        }

        if (! self::looksMojibake($text)) {
            return $text;
        }

        $reencoded = @mb_convert_encoding($text, 'GBK', 'UTF-8');

        if (! is_string($reencoded) || ! mb_check_encoding($reencoded, 'UTF-8')) {
            return $text;
        }

        return $reencoded;
    }

    private static function looksMojibake(string $text): bool
    {
        return (bool) preg_match('/[鐢娆棣鍟瀹閽鎼鍏璐璁鐧娉锛歿馃]|鈫|鈥/u', $text);
    }
}
