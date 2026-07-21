<?php

namespace App\View\Components\Support;

use App\Models\SupportContactMethod;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\View\Component;

class ContactMethods extends Component
{
    /** @var Collection<int, array{name:string, account:string, url:string, icon:string}> */
    public Collection $items;

    public function __construct(?iterable $methods = null, public bool $compact = false)
    {
        $source = $methods === null
            ? SupportContactMethod::query()->active()->orderBy('sort_order')->orderBy('id')->get()
            : collect($methods);

        $this->items = collect($source)
            ->map(fn (mixed $method): ?array => $method instanceof SupportContactMethod
                ? $method->linkData()
                : SupportContactMethod::normalizeLinkData($method))
            ->filter()
            ->values();
    }

    public function render(): View
    {
        return view('components.support.contact-methods');
    }
}
