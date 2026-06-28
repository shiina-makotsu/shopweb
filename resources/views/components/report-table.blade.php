@props([
    'title',
    'rows',
    'columns',
    'exportUrl' => null,
])

<section class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
    <div class="flex items-center justify-between gap-3 border-b border-gray-200 px-4 py-3 dark:border-gray-800">
        <h2 class="text-sm font-semibold text-gray-950 dark:text-white">{{ $title }}</h2>
        @if($exportUrl)
            <a
                href="{{ $exportUrl }}"
                target="_blank"
                class="inline-flex min-h-9 items-center rounded-sm border border-gray-300 px-3 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800"
            >
                CSV
            </a>
        @endif
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
            <thead class="bg-gray-50 dark:bg-gray-950/40">
                <tr>
                    @foreach ($columns as $label)
                        <th class="whitespace-nowrap px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">{{ $label }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($rows as $row)
                    @php($children = collect($row['children'] ?? [])->values())
                    @php($rowId = 'report-row-'.md5($title.'|'.($row[array_key_first($columns)] ?? '').'|'.$loop->index))
                    <tr>
                        @foreach ($columns as $key => $label)
                            <td class="whitespace-nowrap px-4 py-3 text-gray-900 dark:text-gray-100">
                                @if($loop->first && $children->isNotEmpty())
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-2 rounded-sm text-left font-medium text-gray-900 hover:text-blue-700 dark:text-gray-100 dark:hover:text-blue-300"
                                        data-report-row-toggle="{{ $rowId }}"
                                        aria-expanded="false"
                                    >
                                        <span class="text-xs" data-report-row-chevron>▶</span>
                                        <span>{{ $row[$key] ?? '-' }}</span>
                                    </button>
                                @else
                                    {{ $row[$key] ?? '-' }}
                                @endif
                            </td>
                        @endforeach
                    </tr>
                    @if($children->isNotEmpty())
                        <tr id="{{ $rowId }}" class="hidden bg-gray-50/80 dark:bg-gray-950/40" data-report-row-children>
                            <td class="px-4 py-3" colspan="{{ count($columns) }}">
                                <div class="overflow-x-auto rounded-md border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
                                    <table class="min-w-full divide-y divide-gray-100 text-xs dark:divide-gray-800">
                                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                            @foreach($children as $child)
                                                <tr>
                                                    @foreach($columns as $key => $label)
                                                        <td class="whitespace-nowrap px-3 py-2 text-gray-700 dark:text-gray-200">{{ $child[$key] ?? '-' }}</td>
                                                    @endforeach
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr>
                        <td class="px-4 py-6 text-center text-gray-500 dark:text-gray-400" colspan="{{ count($columns) }}">暂无数据</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

@once
    <script>
        (() => {
            const bindReportRowToggles = () => {
                document.querySelectorAll('[data-report-row-toggle]').forEach((button) => {
                    if (button.dataset.reportToggleBound === 'true') {
                        return;
                    }

                    button.dataset.reportToggleBound = 'true';

                    button.addEventListener('click', () => {
                        const id = button.getAttribute('data-report-row-toggle');
                        const target = id ? document.getElementById(id) : null;
                        const chevron = button.querySelector('[data-report-row-chevron]');

                        if (! target) {
                            return;
                        }

                        const expanded = target.classList.contains('hidden');
                        target.classList.toggle('hidden', ! expanded);
                        button.setAttribute('aria-expanded', expanded ? 'true' : 'false');

                        if (chevron) {
                            chevron.textContent = expanded ? '▼' : '▶';
                        }
                    });
                });
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', bindReportRowToggles);
            } else {
                bindReportRowToggles();
            }

            document.addEventListener('livewire:navigated', bindReportRowToggles);
        })();
    </script>
@endonce
