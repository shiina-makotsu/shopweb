@props(['label' => '插入图标'])

<button
    type="button"
    {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:border-blue-300 hover:bg-blue-50 hover:text-blue-800']) }}
    data-fa-textarea-target
>
    <i class="fa-solid fa-icons" aria-hidden="true"></i>
    <span>{{ $label }}</span>
</button>
