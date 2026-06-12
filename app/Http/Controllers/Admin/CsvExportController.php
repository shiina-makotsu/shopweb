<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvExportController extends Controller
{
    public function products(): StreamedResponse
    {
        $rows = ProductVariant::query()
            ->with('product.category')
            ->orderBy('id')
            ->lazyById();

        return $this->download('products-skus.csv', [
            '商品ID',
            '商品标题',
            '分类',
            '商品状态',
            '交付类型',
            'SKU',
            '规格',
            'SKU图片',
            '售价(分)',
            '划线价(分)',
            '库存',
            '低库存阈值',
            'SKU启用',
        ], function ($output) use ($rows): void {
            foreach ($rows as $variant) {
                $product = $variant->product;

                $this->putRow($output, [
                    $product?->id,
                    $product?->title,
                    $product?->category?->name,
                    $product?->status,
                    $product?->fulfillment_type,
                    $variant->sku,
                    $variant->specLabel(),
                    $variant->image_path,
                    $variant->price_cents,
                    $variant->compare_at_price_cents,
                    $variant->stock,
                    $variant->low_stock_threshold,
                    $variant->is_active ? '是' : '否',
                ]);
            }
        });
    }

    public function customers(): StreamedResponse
    {
        $rows = User::query()
            ->withCount('orders')
            ->withSum('orders as orders_total_cents', 'total_cents')
            ->where('role', 'customer')
            ->orderBy('id')
            ->lazyById();

        return $this->download('customers.csv', [
            '客户ID',
            '姓名',
            '邮箱',
            '订单数',
            '累计订单金额(分)',
            '注册时间',
            '更新时间',
        ], function ($output) use ($rows): void {
            foreach ($rows as $user) {
                $this->putRow($output, [
                    $user->id,
                    $user->name,
                    $user->email,
                    $user->orders_count,
                    $user->orders_total_cents ?? 0,
                    $user->created_at?->format('Y-m-d H:i:s'),
                    $user->updated_at?->format('Y-m-d H:i:s'),
                ]);
            }
        });
    }

    private function download(string $filename, array $header, callable $writer): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $writer): void {
            $output = fopen('php://output', 'w');

            echo "\xEF\xBB\xBF";
            $this->putRow($output, $header);
            $writer($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function putRow($output, array $row): void
    {
        fputcsv($output, $row);
    }
}
