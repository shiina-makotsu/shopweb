@props([
    'src',
    'alt' => '付款二维码',
    'size' => 'primary',
])

@php
    $buttonClass = $size === 'secondary'
        ? 'group relative h-32 w-32 overflow-hidden rounded-lg border border-amber-200 bg-white shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2'
        : 'group relative mb-4 h-40 w-40 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2';
@endphp

<button
    type="button"
    class="{{ $buttonClass }}"
    data-payment-code-open
    data-payment-code-src="{{ $src }}"
    data-payment-code-alt="{{ $alt }}"
    aria-label="放大查看{{ $alt }}"
    title="放大查看{{ $alt }}"
>
    <img class="h-full w-full object-contain" src="{{ $src }}" alt="{{ $alt }}" loading="eager" fetchpriority="high" decoding="async">
    <span class="absolute bottom-1.5 right-1.5 inline-flex h-7 w-7 items-center justify-center rounded-full bg-slate-950/70 text-xs text-white opacity-90 transition group-hover:bg-blue-700" aria-hidden="true">
        <i class="fa-solid fa-magnifying-glass-plus"></i>
    </span>
</button>
