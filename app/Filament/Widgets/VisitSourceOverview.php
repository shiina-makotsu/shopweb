<?php

namespace App\Filament\Widgets;

use App\Models\AnalyticsEvent;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class VisitSourceOverview extends Widget
{
    protected static bool $isLazy = false;

    protected ?string $pollingInterval = '60s';

    protected string $view = 'filament.widgets.visit-source-overview';

    protected int | string | array $columnSpan = 'full';

    protected function getViewData(): array
    {
        if (! Schema::hasTable('analytics_events')) {
            return $this->emptyData();
        }

        $base = AnalyticsEvent::query()
            ->where('event', AnalyticsEvent::PAGE_VIEW)
            ->where('created_at', '>=', now()->startOfDay());

        return [
            'total' => (clone $base)->count(),
            'uniqueSessions' => (clone $base)->whereNotNull('session_id')->distinct('session_id')->count('session_id'),
            'surfaces' => $this->counts(clone $base, 'surface', [
                'frontend' => '前台访问',
                'admin' => '后台访问',
            ]),
            'visitors' => $this->counts(clone $base, 'visitor_type', [
                'guest' => '游客',
                'customer' => '前台用户',
                'staff' => '后台用户',
            ]),
            'devices' => $this->counts(clone $base, 'device_type', [
                'desktop' => 'PC 端',
                'mobile' => '移动端',
                'tablet' => '平板',
                'unknown' => '未知设备',
            ]),
            'regionBars' => $this->regionBars(clone $base, 10),
        ];
    }

    /**
     * @param  array<string, string>  $labels
     * @return Collection<int, array{key:string,label:string,count:int,percent:float}>
     */
    private function counts($query, string $column, array $labels = [], int $limit = 12): Collection
    {
        $total = max(1, (clone $query)->count());

        return $query
            ->selectRaw("coalesce({$column}, ?) as bucket", ['未知'])
            ->selectRaw('count(*) as aggregate')
            ->groupBy('bucket')
            ->orderByDesc('aggregate')
            ->limit($limit)
            ->get()
            ->map(fn ($row): array => [
                'key' => (string) $row->bucket,
                'label' => $labels[(string) $row->bucket] ?? (string) $row->bucket,
                'count' => (int) $row->aggregate,
                'percent' => round(((int) $row->aggregate / $total) * 100, 1),
            ]);
    }

    /**
     * @return Collection<int, array{region:string,total:int,guest:int,customer:int,staff:int,guest_percent:float,customer_percent:float,staff_percent:float}>
     */
    private function regionBars($query, int $limit): Collection
    {
        return $query
            ->selectRaw("coalesce(ip_region, '未知地区') as region_name")
            ->selectRaw('count(*) as total_count')
            ->selectRaw("sum(case when coalesce(visitor_type, 'guest') = 'guest' then 1 else 0 end) as guest_count")
            ->selectRaw("sum(case when coalesce(visitor_type, 'guest') = 'customer' then 1 else 0 end) as customer_count")
            ->selectRaw("sum(case when coalesce(visitor_type, 'guest') = 'staff' then 1 else 0 end) as staff_count")
            ->groupBy('region_name')
            ->orderByDesc('total_count')
            ->limit($limit)
            ->get()
            ->map(function ($row): array {
                $total = max(1, (int) $row->total_count);
                $guest = (int) $row->guest_count;
                $customer = (int) $row->customer_count;
                $staff = (int) $row->staff_count;

                return [
                    'region' => (string) $row->region_name,
                    'total' => $total,
                    'guest' => $guest,
                    'customer' => $customer,
                    'staff' => $staff,
                    'guest_percent' => round(($guest / $total) * 100, 1),
                    'customer_percent' => round(($customer / $total) * 100, 1),
                    'staff_percent' => round(($staff / $total) * 100, 1),
                ];
            });
    }

    private function emptyData(): array
    {
        return [
            'total' => 0,
            'uniqueSessions' => 0,
            'surfaces' => collect(),
            'visitors' => collect(),
            'devices' => collect(),
            'regionBars' => collect(),
        ];
    }
}
