<x-filament-panels::page>
    <form wire:submit="save" class="space-y-4">
        {{ $this->form }}

        <div class="shop-settings-form-actions">
            <x-filament::button type="submit">
                保存设置
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
