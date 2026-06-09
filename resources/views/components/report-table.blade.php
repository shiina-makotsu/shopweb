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
                    <tr>
                        @foreach ($columns as $key => $label)
                            <td class="whitespace-nowrap px-4 py-3 text-gray-900 dark:text-gray-100">{{ $row[$key] ?? '-' }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td class="px-4 py-6 text-center text-gray-500 dark:text-gray-400" colspan="{{ count($columns) }}">暂无数据</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
