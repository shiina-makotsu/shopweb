@props([
    'position' => null,
])

@php
    use Filament\Actions\Action;
    use Filament\Enums\UserMenuPosition;
    use Illuminate\Support\Arr;

    $user = filament()->auth()->user();

    $items = $this->getUserMenuItems();

    $itemsBeforeAndAfterThemeSwitcher = collect($items)
        ->groupBy(fn (Action $item): bool => $item->getSort() < 0, preserveKeys: true)
        ->all();
    $itemsBeforeThemeSwitcher = $itemsBeforeAndAfterThemeSwitcher[true] ?? collect();
    $itemsAfterThemeSwitcher = $itemsBeforeAndAfterThemeSwitcher[false] ?? collect();

    $hasProfileHeader = $itemsBeforeThemeSwitcher->has('profile') &&
        blank(($item = Arr::first($itemsBeforeThemeSwitcher))->getUrl()) &&
        (! $item->hasAction());

    if ($itemsBeforeThemeSwitcher->has('profile')) {
        $itemsBeforeThemeSwitcher = $itemsBeforeThemeSwitcher->prepend($itemsBeforeThemeSwitcher->pull('profile'), 'profile');
    }

    $position ??= filament()->getUserMenuPosition();

    $isSidebarCollapsibleOnDesktop = filament()->isSidebarCollapsibleOnDesktop();
    $roleLabel = match ($user?->role) {
        'admin' => '管理员',
        'operator' => '运营',
        'finance' => '财务',
        'warehouse' => '仓库',
        default => '用户',
    };
@endphp

{{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::USER_MENU_BEFORE) }}

<div
    x-data="{ open: false }"
    x-init="document.addEventListener('livewire:navigate', () => open = false)"
    x-on:click.outside="open = false"
    x-on:keydown.escape.window="open = false"
    {{
        \Filament\Support\prepare_inherited_attributes($attributes)
            ->class(['fi-user-menu', 'shop-admin-user-menu'])
    }}
>
    <button
        aria-haspopup="menu"
        aria-label="{{ __('filament-panels::layout.actions.open_user_menu.label') }}"
        type="button"
        x-bind:aria-expanded="open.toString()"
        x-on:click="open = ! open"
        class="fi-user-menu-trigger shop-admin-user-menu-trigger"
    >
        <x-filament-panels::avatar.user :user="$user" loading="lazy" />

        <span
            @if ($position !== UserMenuPosition::Topbar && $isSidebarCollapsibleOnDesktop)
                x-show="$store.sidebar.isOpen"
            @endif
            class="shop-admin-user-menu-trigger-text"
        >
            <span class="shop-admin-user-menu-name">
                {{ filament()->getUserName($user) }}
            </span>
            <span class="shop-admin-user-menu-role">
                {{ $roleLabel }}
            </span>
        </span>

        {{
            \Filament\Support\generate_icon_html(\Filament\Support\Icons\Heroicon::ChevronDown, attributes: new \Illuminate\View\ComponentAttributeBag([
                'x-show' => ($position !== UserMenuPosition::Topbar && $isSidebarCollapsibleOnDesktop) ? '$store.sidebar.isOpen' : null,
                'x-bind:class' => "{ 'shop-admin-user-menu-chevron-open': open }",
            ]))
        }}
    </button>

    <div
        x-cloak
        x-show="open"
        x-transition:enter-start="fi-opacity-0"
        x-transition:leave-end="fi-opacity-0"
        class="fi-dropdown-panel shop-admin-user-menu-panel"
        role="menu"
    >
        @if ($hasProfileHeader)
            @php
                $item = $itemsBeforeThemeSwitcher['profile'];
                $itemColor = $item->getColor();
                $itemIcon = $item->getIcon();

                unset($itemsBeforeThemeSwitcher['profile']);
            @endphp

            {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::USER_MENU_PROFILE_BEFORE) }}

            <x-filament::dropdown.header :color="$itemColor" :icon="$itemIcon">
                {{ $item->getLabel() }}
            </x-filament::dropdown.header>

            {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::USER_MENU_PROFILE_AFTER) }}
        @endif

        @if ($itemsBeforeThemeSwitcher->isNotEmpty())
            <x-filament::dropdown.list>
                @foreach ($itemsBeforeThemeSwitcher as $key => $item)
                    @if ($key === 'profile')
                        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::USER_MENU_PROFILE_BEFORE) }}

                        {{ $item }}

                        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::USER_MENU_PROFILE_AFTER) }}
                    @else
                        {{ $item }}
                    @endif
                @endforeach
            </x-filament::dropdown.list>
        @endif

        @if (filament()->hasDarkMode() && (! filament()->hasDarkModeForced()))
            <x-filament::dropdown.list>
                <x-filament-panels::theme-switcher />
            </x-filament::dropdown.list>
        @endif

        @if ($itemsAfterThemeSwitcher->isNotEmpty())
            <x-filament::dropdown.list>
                @foreach ($itemsAfterThemeSwitcher as $key => $item)
                    @if ($key === 'profile')
                        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::USER_MENU_PROFILE_BEFORE) }}

                        {{ $item }}

                        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::USER_MENU_PROFILE_AFTER) }}
                    @else
                        {{ $item }}
                    @endif
                @endforeach
            </x-filament::dropdown.list>
        @endif
    </div>
</div>

{{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::USER_MENU_AFTER) }}
