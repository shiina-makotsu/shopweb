<?php

namespace App\Support;

class ChinaRegions
{
    /**
     * @return array<string, string>
     */
    public static function provinceOptions(): array
    {
        return collect(self::provinces())->mapWithKeys(fn (string $province): array => [$province => $province])->all();
    }

    /**
     * @return array<string, string>
     */
    public static function presetOptions(): array
    {
        return [
            'northwest' => '西北地区',
            'xinjiang_tibet' => '新疆西藏地区',
            'northeast' => '东北地区',
            'inland' => '内陆地区',
            'east' => '华东地区',
            'south' => '华南地区',
            'southwest' => '西南地区',
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function provincesForPreset(?string $preset): array
    {
        return match ($preset) {
            'northwest' => ['陕西', '甘肃', '宁夏', '青海', '内蒙古'],
            'xinjiang_tibet' => ['新疆', '西藏'],
            'northeast' => ['黑龙江', '吉林', '辽宁'],
            'inland' => ['河南', '湖北', '湖南', '江西', '安徽', '山西'],
            'east' => ['上海', '江苏', '浙江', '山东', '福建', '台湾'],
            'south' => ['广东', '广西', '海南', '香港', '澳门'],
            'southwest' => ['重庆', '四川', '贵州', '云南'],
            default => [],
        };
    }

    public static function normalizeProvince(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        foreach (self::provinces() as $province) {
            if ($value === $province || str_contains($value, $province)) {
                return $province;
            }
        }

        $normalized = preg_replace('/(省|市|自治区|壮族|回族|维吾尔|特别行政区)$/u', '', $value);
        $normalized = trim((string) $normalized);

        return in_array($normalized, self::provinces(), true) ? $normalized : $value;
    }

    public static function guessProvinceFromAddress(?string $address): ?string
    {
        $address = trim((string) $address);

        if ($address === '') {
            return null;
        }

        foreach (self::provinces() as $province) {
            if (str_contains($address, $province)) {
                return $province;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    public static function provinces(): array
    {
        return [
            '北京',
            '天津',
            '河北',
            '山西',
            '内蒙古',
            '辽宁',
            '吉林',
            '黑龙江',
            '上海',
            '江苏',
            '浙江',
            '安徽',
            '福建',
            '江西',
            '山东',
            '河南',
            '湖北',
            '湖南',
            '广东',
            '广西',
            '海南',
            '重庆',
            '四川',
            '贵州',
            '云南',
            '西藏',
            '陕西',
            '甘肃',
            '青海',
            '宁夏',
            '新疆',
            '香港',
            '澳门',
            '台湾',
        ];
    }
}
