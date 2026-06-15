<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminAccess;
use App\Support\Money;
use App\Support\ProfitMetrics;
use App\Support\ReportMetrics;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportController extends Controller
{
    public function productSales(ReportMetrics $metrics): StreamedResponse
    {
        abort_unless(AdminAccess::can('reports'), 403);

        return $this->download('report-product-sales.csv', [
            '商品',
            'SKU',
            '销售数量',
            '销售金额',
        ], $metrics->productSales(500)->map(fn (array $row): array => [
            $row['product'],
            $row['sku'],
            $row['quantity'],
            $row['total'],
        ])->all());
    }

    public function categorySales(ReportMetrics $metrics): StreamedResponse
    {
        abort_unless(AdminAccess::can('reports'), 403);

        return $this->download('report-category-sales.csv', [
            '分类',
            '订单商品数',
            '销售数量',
            '销售金额',
        ], $metrics->categorySales(500)->map(fn (array $row): array => [
            $row['category'],
            $row['items'],
            $row['quantity'],
            $row['total'],
        ])->all());
    }

    public function profitOverview(Request $request, ProfitMetrics $metrics): StreamedResponse
    {
        abort_unless(AdminAccess::can('finance'), 403);

        $dateFrom = $this->parseDate($request->query('date_from'));
        $dateTo = $this->parseDate($request->query('date_to'));
        $summary = $metrics->summary($dateFrom, $dateTo);
        $warehouseRows = collect($metrics->warehouseBreakdown($dateFrom, $dateTo))
            ->map(fn (array $row): array => [
                $row['warehouse_name'],
                Money::format((int) $row['sales_cents']),
                Money::format((int) $row['cost_cents']),
                Money::format((int) $row['profit_cents']),
                $this->formatRate($row['profit_rate']),
                $row['orders_count'],
            ])
            ->all();
        $fulfillmentRows = collect($metrics->fulfillmentBreakdown($dateFrom, $dateTo))
            ->map(fn (array $row): array => [
                $row['label'],
                Money::format((int) $row['sales_cents']),
                Money::format((int) $row['purchase_cost_cents']),
                Money::format((int) $row['cost_cents']),
                Money::format((int) $row['gross_profit_cents']),
                Money::format((int) $row['profit_cents']),
                Money::format((int) $row['formula_profit_cents']),
                $this->formatRate($row['profit_rate']),
                $row['orders_count'],
            ])
            ->all();

        return $this->download('report-profit-overview.csv', [
            '类型',
            '名称',
            '销售额',
            '采购成本',
            '毛利润',
            '毛利润率',
            '总成本',
            '总利润',
            '总利润率',
            '完成订单',
        ], [
            [
                '汇总',
                $this->dateRangeLabel($dateFrom, $dateTo),
                Money::format($summary['sales_cents']),
                Money::format($summary['purchase_cost_cents']),
                Money::format($summary['gross_profit_cents']),
                $this->formatRate($summary['gross_profit_rate']),
                Money::format($summary['cost_cents']),
                Money::format($summary['profit_cents']),
                $this->formatRate($summary['profit_rate']),
                $summary['completed_orders'],
            ],
            [],
            ['仓库利润', '仓库', '销售额', '成本', '利润', '利润率', '完成订单'],
            ['交付类型利润', '交付类型', '销售额', '采购成本', '总成本', '毛利润', '默认利润', '公式利润', '利润率', '完成订单'],
            ...$fulfillmentRows,
            [],
            ...$warehouseRows,
        ]);
    }

    /**
     * @param  array<int, array<int, string|int>>  $rows
     */
    private function download(string $filename, array $header, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows): void {
            $output = fopen('php://output', 'w');

            echo "\xEF\xBB\xBF";
            fputcsv($output, $header);

            foreach ($rows as $row) {
                fputcsv($output, $row);
            }
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function parseDate(mixed $date): ?Carbon
    {
        if (! is_string($date) || trim($date) === '') {
            return null;
        }

        try {
            return Carbon::parse($date);
        } catch (\Throwable) {
            return null;
        }
    }

    private function formatRate(?float $rate): string
    {
        return $rate === null ? '-' : number_format($rate * 100, 2).'%';
    }

    private function dateRangeLabel(?Carbon $dateFrom, ?Carbon $dateTo): string
    {
        if (! $dateFrom && ! $dateTo) {
            return '全部日期';
        }

        return ($dateFrom?->toDateString() ?: '开始').' 至 '.($dateTo?->toDateString() ?: '结束');
    }
}
