@if($items->isNotEmpty())
    <div {{ $attributes->class(['flex flex-wrap gap-2', 'text-xs' => $compact, 'text-sm' => ! $compact]) }}>
        @foreach($items as $item)
            <a
                class="inline-flex min-w-0 items-center gap-2 rounded-full border border-sky-200 bg-white px-3 py-2 font-medium text-sky-900 shadow-sm transition hover:border-pink-300 hover:bg-pink-50 hover:text-pink-900"
                href="{{ $item['url'] }}"
                target="_blank"
                rel="noopener noreferrer"
                title="打开 {{ $item['name'] }}"
            >
                <i class="{{ $item['icon'] }} fa-fw shrink-0" aria-hidden="true"></i>
                <span class="truncate">{{ $item['name'] }}</span>
                @if($item['account'] !== '')
                    <span class="truncate font-normal text-slate-500">{{ $item['account'] }}</span>
                @endif
                <i class="fa-solid fa-arrow-up-right-from-square shrink-0 text-[0.75em] opacity-60" aria-hidden="true"></i>
            </a>
        @endforeach
    </div>
@endif
