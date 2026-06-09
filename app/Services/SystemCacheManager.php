<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;

class SystemCacheManager
{
    /**
     * @return array<int, array{label:string,value:string,status:?bool}>
     */
    public function overview(): array
    {
        return [
            [
                'label' => '缓存驱动',
                'value' => (string) config('cache.default', 'file'),
                'status' => null,
            ],
            [
                'label' => '配置缓存',
                'value' => app()->configurationIsCached() ? '已生成' : '未生成',
                'status' => app()->configurationIsCached(),
            ],
            [
                'label' => '路由缓存',
                'value' => app()->routesAreCached() ? '已生成' : '未生成',
                'status' => app()->routesAreCached(),
            ],
            [
                'label' => '视图缓存目录',
                'value' => is_dir(storage_path('framework/views')) ? '可用' : '缺失',
                'status' => is_dir(storage_path('framework/views')),
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    public function clearAll(): array
    {
        return [
            $this->run('optimize:clear'),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function clearRuntime(): array
    {
        return [
            $this->run('cache:clear'),
            $this->run('view:clear'),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function optimize(): array
    {
        return [
            $this->run('optimize'),
        ];
    }

    private function run(string $command): string
    {
        Artisan::call($command);

        $output = trim(Artisan::output());

        return $output !== '' ? $output : "{$command} completed.";
    }
}
