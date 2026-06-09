<x-filament-panels::page>
    <div class="shop-settings-page">
        <div class="shop-settings-header">
            <span class="shop-settings-icon">
                <x-filament::icon icon="heroicon-o-building-storefront" class="h-6 w-6" />
            </span>
            <div>
                <h2>设置 - 商店信息</h2>
                <p>维护前台展示、联系信息和后台基础色彩。</p>
            </div>
        </div>

        <div class="shop-settings-table">
            <div class="shop-settings-row shop-settings-row-head">
                <span>说明</span>
                <span>价值</span>
                <span>命令</span>
            </div>

            @foreach ($this->settingRows() as $row)
                @php
                    $field = $row['field'];
                    $isEditing = $editingField === $field;
                    $isColor = $row['type'] === 'color';
                @endphp

                <div class="shop-settings-row {{ $isEditing ? 'is-editing' : '' }}">
                    <label for="setting_{{ $field }}">
                        <span class="shop-settings-label-pill">{{ $row['label'] }}</span>
                    </label>

                    <div class="shop-settings-value">
                        @if ($isEditing)
                            <form wire:submit="saveField('{{ $field }}')" class="shop-settings-inline-form">
                                @if (($row['multiline'] ?? false) === true)
                                    <textarea
                                        id="setting_{{ $field }}"
                                        wire:model="{{ $field }}"
                                        rows="3"
                                        placeholder="{{ $row['placeholder'] ?? '' }}"
                                    ></textarea>
                                @elseif ($isColor)
                                    <span class="shop-settings-color-field">
                                        <input id="setting_{{ $field }}" type="color" wire:model="{{ $field }}">
                                        <input type="text" wire:model="{{ $field }}">
                                    </span>
                                @else
                                    <input
                                        id="setting_{{ $field }}"
                                        type="{{ $row['type'] }}"
                                        wire:model="{{ $field }}"
                                        placeholder="{{ $row['placeholder'] ?? '' }}"
                                    >
                                @endif

                                <span class="shop-settings-inline-actions">
                                    <button type="submit" class="shop-settings-primary-btn">保存</button>
                                    <button type="button" wire:click="cancelField" class="shop-settings-secondary-btn">取消</button>
                                </span>
                            </form>

                            @error($field)
                                <p class="shop-settings-field-error">{{ $message }}</p>
                            @enderror
                        @else
                            @if ($isColor && $this->displayValue($field) !== '-')
                                <span class="shop-settings-color-preview" style="--preview-color: {{ $this->displayValue($field) }}"></span>
                            @endif
                            <span class="shop-settings-value-text">{{ $this->displayValue($field) }}</span>
                        @endif
                    </div>

                    <div class="shop-settings-command">
                        @unless ($isEditing)
                            <button
                                type="button"
                                wire:click="editField('{{ $field }}')"
                                class="shop-settings-icon-btn"
                                title="编辑{{ $row['label'] }}"
                                aria-label="编辑{{ $row['label'] }}"
                            >
                                <x-filament::icon icon="heroicon-o-pencil" class="h-4 w-4" />
                            </button>
                        @endunless
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-filament-panels::page>
