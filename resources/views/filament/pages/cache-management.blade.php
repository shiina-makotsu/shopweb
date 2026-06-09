<x-filament-panels::page>
    <div class="shop-settings-page">
        <div class="shop-settings-header">
            <span class="shop-settings-icon shop-settings-icon-blue">
                <x-filament::icon icon="heroicon-o-bolt" class="h-6 w-6" />
            </span>
            <div>
                <h2>缓存管理</h2>
                <p>清理运行缓存，或为生产环境重新生成 Laravel 缓存。</p>
            </div>
        </div>

        <div class="shop-settings-table">
            @foreach ($this->overview() as $row)
                <div class="shop-settings-row">
                    <span>{{ $row['label'] }}</span>
                    <strong>{{ $row['value'] }}</strong>
                    <span class="shop-settings-state {{ $row['status'] === false ? 'is-warning' : '' }}">
                        @if ($row['status'] === true)
                            正常
                        @elseif ($row['status'] === false)
                            注意
                        @else
                            信息
                        @endif
                    </span>
                </div>
            @endforeach
        </div>

        <div class="shop-cache-actions">
            <button type="button" wire:click="clearRuntime" class="shop-settings-secondary-btn">
                清理运行缓存
            </button>
            <button type="button" wire:click="clearAll" class="shop-settings-secondary-btn">
                清理全部缓存
            </button>
            <button type="button" wire:click="optimize" class="shop-settings-primary-btn">
                生成生产缓存
            </button>
        </div>

        @if ($lastResult)
            <pre class="shop-cache-result">{{ $lastResult }}</pre>
        @endif
    </div>
</x-filament-panels::page>
