<x-filament-panels::page>
    <div class="shop-settings-page">
        <div class="shop-settings-header">
            <span class="shop-settings-icon shop-settings-icon-blue">
                <x-filament::icon icon="heroicon-o-bolt" class="h-6 w-6" />
            </span>
            <div>
                <h2>缓存管理</h2>
                <p>清理运行缓存，或为生产环境重新生成 Laravel 缓存并预热访问数据。</p>
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
            <button type="button" wire:click="prewarm" class="shop-settings-secondary-btn">
                预热访问缓存
            </button>
            <button type="button" wire:click="optimize" class="shop-settings-primary-btn">
                生成生产缓存
            </button>
        </div>

        <div class="shop-settings-panel" style="margin-top: 1rem;">
            <div class="shop-settings-header">
                <span class="shop-settings-icon shop-settings-icon-blue">
                    <x-filament::icon icon="heroicon-o-arrow-path" class="h-6 w-6" />
                </span>
                <div>
                    <h2>代码更新与回滚</h2>
                    <p>检查 Git 上游仓库；有更新时拉取代码并自动执行前端资源构建。回滚会切换到选定历史版本并重新构建资源。</p>
                </div>
            </div>

            <div class="shop-cache-actions">
                <button
                    type="button"
                    wire:click="pullUpdates"
                    wire:loading.attr="disabled"
                    wire:target="pullUpdates,rollback"
                    class="shop-settings-primary-btn"
                >
                    检查并拉取更新
                </button>
            </div>

            <div class="shop-settings-row" style="align-items: center; gap: 0.75rem;">
                <span>回滚版本</span>
                <select wire:model="rollbackCommit" class="shop-settings-select" style="min-width: min(100%, 32rem);">
                    <option value="">选择最近版本</option>
                    @foreach ($this->rollbackOptions() as $hash => $label)
                        <option value="{{ $hash }}">{{ $label }}</option>
                    @endforeach
                </select>
                <button
                    type="button"
                    wire:click="rollback"
                    wire:loading.attr="disabled"
                    wire:target="pullUpdates,rollback"
                    class="shop-settings-secondary-btn"
                >
                    回滚并重构资源
                </button>
            </div>

            <p style="margin-top: 0.75rem; color: #64748b;">
                为避免覆盖生产临时改动，执行更新或回滚前会检查 Git 工作区必须保持干净；如 package.json 或 package-lock.json 变化，会先安装前端依赖再构建。
            </p>
        </div>

        @if ($lastResult)
            <pre class="shop-cache-result">{{ $lastResult }}</pre>
        @endif
    </div>
</x-filament-panels::page>
