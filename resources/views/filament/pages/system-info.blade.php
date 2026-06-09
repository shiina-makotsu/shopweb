<x-filament-panels::page>
    <div class="space-y-6">
        @foreach ($this->sections() as $title => $rows)
            <section class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="border-b border-gray-200 px-4 py-3 dark:border-gray-800">
                    <h2 class="text-sm font-semibold text-gray-950 dark:text-white">{{ $title }}</h2>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($rows as $row)
                                <tr>
                                    <th class="w-48 whitespace-nowrap px-4 py-3 text-left font-medium text-gray-700 dark:text-gray-200">
                                        {{ $row['label'] }}
                                    </th>
                                    <td class="px-4 py-3 text-gray-900 dark:text-gray-100">
                                        <span class="break-all">{{ $row['value'] }}</span>
                                    </td>
                                    <td class="w-28 px-4 py-3 text-right">
                                        @if ($row['status'] === true)
                                            <span class="inline-flex rounded-md bg-green-50 px-2 py-1 text-xs font-medium text-green-700 ring-1 ring-green-600/20 dark:bg-green-950 dark:text-green-300">
                                                正常
                                            </span>
                                        @elseif ($row['status'] === false)
                                            <span class="inline-flex rounded-md bg-amber-50 px-2 py-1 text-xs font-medium text-amber-700 ring-1 ring-amber-600/20 dark:bg-amber-950 dark:text-amber-300">
                                                注意
                                            </span>
                                        @else
                                            <span class="inline-flex rounded-md bg-gray-50 px-2 py-1 text-xs font-medium text-gray-600 ring-1 ring-gray-500/10 dark:bg-gray-800 dark:text-gray-300">
                                                信息
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endforeach
    </div>
</x-filament-panels::page>
