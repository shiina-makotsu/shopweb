<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminAccess;
use App\Support\ReportMetrics;
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
}
