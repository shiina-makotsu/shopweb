<?php

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\CsvImportService;
use Illuminate\Support\Facades\File;

it('imports product sku rows and records stock movements', function (): void {
    $path = storage_path('framework/testing/products.csv');

    File::ensureDirectoryExists(dirname($path));
    writeCsv($path, [
        ['商品标题', '分类', '商品状态', '交付类型', 'SKU', '规格', 'SKU图片', '售价(分)', '划线价(分)', '库存', '低库存阈值', 'SKU启用'],
        ['导入商品', '导入分类', 'published', 'contact_only', 'IMP-001', '颜色: 红 / 尺寸: L', 'https://cdn.example.com/import-red.jpg', '12345', '15000', '8', '2', '是'],
    ]);

    $result = app(CsvImportService::class)->importProducts($path);

    expect($result['processed'])->toBe(1)
        ->and($result['created'])->toBe(1)
        ->and($result['updated'])->toBe(0)
        ->and($result['skipped'])->toBe(0);

    $variant = ProductVariant::query()->where('sku', 'IMP-001')->firstOrFail();

    expect($variant->product->title)->toBe('导入商品')
        ->and($variant->product->category->name)->toBe('导入分类')
        ->and($variant->product->status)->toBe(Product::STATUS_PUBLISHED)
        ->and($variant->product->fulfillment_type)->toBe(Product::FULFILLMENT_CONTACT)
        ->and($variant->price_cents)->toBe(12345)
        ->and($variant->compare_at_price_cents)->toBe(15000)
        ->and($variant->stock)->toBe(8)
        ->and($variant->low_stock_threshold)->toBe(2)
        ->and($variant->is_active)->toBeTrue()
        ->and($variant->image_path)->toBe('https://cdn.example.com/import-red.jpg')
        ->and($variant->specs)->toBe(['颜色' => '红', '尺寸' => 'L']);

    $this->assertDatabaseHas('inventory_movements', [
        'product_variant_id' => $variant->id,
        'delta' => 8,
        'stock_after' => 8,
        'reason' => 'csv_import',
    ]);

    writeCsv($path, [
        ['title', 'category', 'status', 'fulfillment_type', 'sku', 'specs', 'image_path', 'price_cents', 'stock'],
        ['导入商品更新', '导入分类', 'draft', 'shipping_required', 'IMP-001', '{"版本":"2026"}', 'products/import-2026.webp', '13000', '5'],
    ]);

    $updateResult = app(CsvImportService::class)->importProducts($path);
    $variant->refresh();

    expect($updateResult['processed'])->toBe(1)
        ->and($updateResult['created'])->toBe(0)
        ->and($updateResult['updated'])->toBe(1)
        ->and($variant->product->title)->toBe('导入商品更新')
        ->and($variant->product->status)->toBe(Product::STATUS_DRAFT)
        ->and($variant->product->fulfillment_type)->toBe(Product::FULFILLMENT_SHIPPING)
        ->and($variant->price_cents)->toBe(13000)
        ->and($variant->stock)->toBe(5)
        ->and($variant->image_path)->toBe('products/import-2026.webp')
        ->and($variant->specs)->toBe(['版本' => '2026']);

    $this->assertDatabaseHas('inventory_movements', [
        'product_variant_id' => $variant->id,
        'delta' => -3,
        'stock_after' => 5,
        'reason' => 'csv_import',
    ]);

    expect(InventoryMovement::query()->where('product_variant_id', $variant->id)->count())->toBe(2);
});

it('imports customers by email and skips admin accounts', function (): void {
    $admin = User::factory()->create([
        'email' => 'admin@example.com',
        'role' => 'admin',
    ]);
    $customer = User::factory()->create([
        'email' => 'old@example.com',
        'name' => '旧姓名',
        'role' => 'customer',
    ]);

    $path = storage_path('framework/testing/customers.csv');

    File::ensureDirectoryExists(dirname($path));
    writeCsv($path, [
        ['姓名', '邮箱'],
        ['新客户', 'new@example.com'],
        ['更新客户', 'old@example.com'],
        ['误导管理员', 'admin@example.com'],
    ]);

    $result = app(CsvImportService::class)->importCustomers($path);

    expect($result['processed'])->toBe(3)
        ->and($result['created'])->toBe(1)
        ->and($result['updated'])->toBe(1)
        ->and($result['skipped'])->toBe(1)
        ->and($result['errors'][0])->toContain('后台用户');

    $this->assertDatabaseHas('users', [
        'name' => '新客户',
        'email' => 'new@example.com',
        'role' => 'customer',
    ]);
    $this->assertDatabaseHas('users', [
        'id' => $customer->id,
        'name' => '更新客户',
        'email' => 'old@example.com',
        'role' => 'customer',
    ]);

    expect($admin->fresh()->role)->toBe('admin')
        ->and($admin->fresh()->name)->not->toBe('误导管理员');
});

function writeCsv(string $path, array $rows): void
{
    $handle = fopen($path, 'w');

    foreach ($rows as $row) {
        fputcsv($handle, $row);
    }

    fclose($handle);
}
