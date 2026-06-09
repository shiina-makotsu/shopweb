<?php

namespace App\Services;

use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CsvImportService
{
    /**
     * @return array{processed:int,created:int,updated:int,skipped:int,errors:array<int,string>}
     */
    public function importProducts(string $path, ?int $userId = null): array
    {
        return $this->readRows($path, 'products', function (array $row, int $line) use ($userId): string {
            $sku = $this->string($row, 'sku');

            if ($sku === '') {
                throw new CsvImportSkipException("第 {$line} 行缺少 SKU");
            }

            return DB::transaction(function () use ($row, $sku, $userId): string {
                $variant = ProductVariant::query()
                    ->where('sku', $sku)
                    ->lockForUpdate()
                    ->first();

                $product = $this->resolveProduct($row, $variant);
                $oldStock = $variant?->stock ?? 0;
                $newStock = max(0, $this->nullableInt($row, 'stock') ?? $oldStock);
                $compareAtPriceCents = $this->has($row, 'compare_at_price_cents')
                    ? $this->nullableInt($row, 'compare_at_price_cents')
                    : $variant?->compare_at_price_cents;
                $lowStockThreshold = $this->has($row, 'low_stock_threshold')
                    ? $this->nullableInt($row, 'low_stock_threshold')
                    : $variant?->low_stock_threshold;

                $variantData = [
                    'product_id' => $product->id,
                    'sku' => $sku,
                    'specs' => $this->specs($row, $variant?->specs ?? []),
                    'price_cents' => $this->requiredInt($row, 'price_cents', $variant?->price_cents),
                    'compare_at_price_cents' => $compareAtPriceCents === null ? null : max(0, $compareAtPriceCents),
                    'stock' => $newStock,
                    'low_stock_threshold' => max(0, $lowStockThreshold ?? $variant?->low_stock_threshold ?? 5),
                    'is_active' => $this->bool($row, 'is_active', $variant?->is_active ?? true),
                ];

                if ($variant) {
                    $variant->update($variantData);
                    $result = 'updated';
                } else {
                    $variant = ProductVariant::query()->create($variantData);
                    $result = 'created';
                }

                $delta = $newStock - $oldStock;

                if ($delta !== 0) {
                    InventoryMovement::query()->create([
                        'product_variant_id' => $variant->id,
                        'user_id' => $userId,
                        'delta' => $delta,
                        'stock_after' => $variant->stock,
                        'reason' => 'csv_import',
                        'note' => 'CSV import',
                    ]);
                }

                return $result;
            });
        });
    }

    /**
     * @return array{processed:int,created:int,updated:int,skipped:int,errors:array<int,string>}
     */
    public function importCustomers(string $path): array
    {
        return $this->readRows($path, 'customers', function (array $row, int $line): string {
            $email = Str::lower($this->string($row, 'email'));

            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new CsvImportSkipException("第 {$line} 行邮箱无效");
            }

            $name = $this->string($row, 'name') ?: Str::before($email, '@');

            return DB::transaction(function () use ($email, $name, $line): string {
                $user = User::query()
                    ->where('email', $email)
                    ->lockForUpdate()
                    ->first();

                if ($user && $user->role !== 'customer') {
                    throw new CsvImportSkipException("第 {$line} 行邮箱已属于后台用户，已跳过");
                }

                if ($user) {
                    $user->update(['name' => $name]);

                    return 'updated';
                }

                User::query()->create([
                    'name' => $name,
                    'email' => $email,
                    'password' => Str::password(32),
                    'role' => 'customer',
                ]);

                return 'created';
            });
        });
    }

    private function resolveProduct(array $row, ?ProductVariant $variant): Product
    {
        $productId = $this->nullableInt($row, 'product_id');
        $title = $this->string($row, 'title');

        $product = $variant?->product;

        if (! $product && $productId) {
            $product = Product::query()->find($productId);
        }

        if (! $product && $title !== '') {
            $product = Product::query()->where('title', $title)->first();
        }

        if (! $product && $title !== '') {
            $product = Product::query()->where('slug', $this->uniqueSlugBase($title, 'product'))->first();
        }

        if (! $product && $title === '') {
            throw new CsvImportSkipException('新 SKU 缺少商品标题');
        }

        $category = $this->resolveCategory($row);
        $title = $title ?: $product?->title;

        $data = [
            'category_id' => $category?->id ?? $product?->category_id,
            'title' => $title,
            'status' => $this->status($row, $product?->status ?? Product::STATUS_DRAFT),
            'fulfillment_type' => $this->fulfillment($row, $product?->fulfillment_type ?? Product::FULFILLMENT_SHIPPING),
        ];

        if ($product) {
            if ($title && $title !== $product->title) {
                $data['slug'] = $this->uniqueSlug($title, 'product', Product::class, $product->id);
            }

            $product->update($data);

            return $product;
        }

        return Product::query()->create($data + [
            'slug' => $this->uniqueSlug($title, 'product', Product::class),
            'summary' => null,
            'description' => null,
            'is_featured' => false,
            'sort_order' => 0,
        ]);
    }

    private function resolveCategory(array $row): ?Category
    {
        $name = $this->string($row, 'category');

        if ($name === '') {
            return null;
        }

        $category = Category::query()->where('name', $name)->first();

        if ($category) {
            return $category;
        }

        return Category::query()->create([
            'name' => $name,
            'slug' => $this->uniqueSlug($name, 'category', Category::class),
            'sort_order' => 0,
            'is_active' => true,
        ]);
    }

    /**
     * @return array{processed:int,created:int,updated:int,skipped:int,errors:array<int,string>}
     */
    private function readRows(string $path, string $type, callable $handler): array
    {
        if (! is_readable($path)) {
            throw new \RuntimeException("CSV 文件不可读取：{$path}");
        }

        $result = [
            'processed' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new \RuntimeException("CSV 文件无法打开：{$path}");
        }

        try {
            $header = fgetcsv($handle);

            if (! is_array($header)) {
                return $result;
            }

            $keys = $this->headers($header, $type);
            $line = 1;

            while (($values = fgetcsv($handle)) !== false) {
                $line++;

                if ($this->blankRow($values)) {
                    continue;
                }

                $result['processed']++;
                $row = $this->combine($keys, $values);

                try {
                    $status = $handler($row, $line);

                    if ($status === 'created') {
                        $result['created']++;
                    } elseif ($status === 'updated') {
                        $result['updated']++;
                    }
                } catch (CsvImportSkipException $exception) {
                    $result['skipped']++;
                    $result['errors'][] = $exception->getMessage();
                }
            }
        } finally {
            fclose($handle);
        }

        return $result;
    }

    /**
     * @param  array<int, string|null>  $headers
     * @return array<int, string>
     */
    private function headers(array $headers, string $type): array
    {
        return array_map(function (?string $header) use ($type): string {
            $header = $this->clean((string) $header);

            if ($type === 'customers') {
                return match ($header) {
                    '客户ID', 'customer_id', 'user_id', 'id' => 'customer_id',
                    '姓名', '客户姓名', 'customer_name', 'name' => 'name',
                    '邮箱', 'email' => 'email',
                    default => Str::snake($header),
                };
            }

            return match ($header) {
                '商品ID', 'product_id', 'id' => 'product_id',
                '商品标题', '标题', 'title', 'product_title', 'name' => 'title',
                '分类', 'category', 'category_name' => 'category',
                '商品状态', '状态', 'status' => 'status',
                '交付类型', 'fulfillment_type', 'delivery_type' => 'fulfillment_type',
                'SKU', 'sku' => 'sku',
                '规格', 'specs', 'options' => 'specs',
                '售价(分)', '价格(分)', 'price_cents', 'price' => 'price_cents',
                '划线价(分)', 'compare_at_price_cents', 'compare_at_price' => 'compare_at_price_cents',
                '库存', 'stock' => 'stock',
                '低库存阈值', 'low_stock_threshold' => 'low_stock_threshold',
                'SKU启用', '启用', 'is_active', 'active' => 'is_active',
                default => Str::snake($header),
            };
        }, $headers);
    }

    /**
     * @param  array<int, string>  $keys
     * @param  array<int, string|null>  $values
     * @return array<string, string>
     */
    private function combine(array $keys, array $values): array
    {
        $row = [];

        foreach ($keys as $index => $key) {
            if ($key === '') {
                continue;
            }

            $row[$key] = $this->clean((string) ($values[$index] ?? ''));
        }

        return $row;
    }

    /**
     * @param  array<int, string|null>  $values
     */
    private function blankRow(array $values): bool
    {
        foreach ($values as $value) {
            if ($this->clean((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function string(array $row, string $key): string
    {
        return $this->clean((string) ($row[$key] ?? ''));
    }

    private function nullableInt(array $row, string $key): ?int
    {
        $value = $this->string($row, $key);

        if ($value === '') {
            return null;
        }

        $value = str_replace([',', '，', ' '], '', $value);

        if (! preg_match('/^-?\d+$/', $value)) {
            return null;
        }

        return (int) $value;
    }

    private function requiredInt(array $row, string $key, ?int $fallback = null): int
    {
        $value = $this->nullableInt($row, $key);

        if ($value !== null) {
            return max(0, $value);
        }

        if ($fallback !== null) {
            return $fallback;
        }

        throw new CsvImportSkipException("缺少必要数值：{$key}");
    }

    private function bool(array $row, string $key, bool $default): bool
    {
        $value = Str::lower($this->string($row, $key));

        if ($value === '') {
            return $default;
        }

        if (in_array($value, ['1', 'true', 'yes', 'y', 'on', '是', '启用', 'enabled', 'active'], true)) {
            return true;
        }

        if (in_array($value, ['0', 'false', 'no', 'n', 'off', '否', '禁用', 'disabled', 'inactive'], true)) {
            return false;
        }

        return $default;
    }

    private function status(array $row, string $default): string
    {
        $value = Str::lower($this->string($row, 'status'));

        return match ($value) {
            'published', 'publish', 'active', 'online', '发布', '已发布', '上架', '现货' => Product::STATUS_PUBLISHED,
            'concept', 'idea', '概念', '概念品' => Product::STATUS_CONCEPT,
            'presale', 'pre_sale', '预售' => Product::STATUS_PRESALE,
            'incoming', '进货中', '采购中' => Product::STATUS_INCOMING,
            'sold_out', 'soldout', '售罄', '无货' => Product::STATUS_SOLD_OUT,
            'draft', 'inactive', 'offline', '草稿', '下架' => Product::STATUS_DRAFT,
            default => $default,
        };
    }

    private function fulfillment(array $row, string $default): string
    {
        $value = Str::lower($this->string($row, 'fulfillment_type'));

        return match ($value) {
            'online', 'contact_only', 'contact', 'virtual', 'digital', '线上交付', '仅联系方式', '无需收货', '不需要收货地址' => Product::FULFILLMENT_ONLINE,
            'logistics', 'shipping_required', 'shipping', 'ship', '物流交付', '需要收货地址', '实物发货' => Product::FULFILLMENT_LOGISTICS,
            'in_person', 'face_to_face', 'offline', '当面交付', '线下交付' => Product::FULFILLMENT_IN_PERSON,
            default => $default,
        };
    }

    /**
     * @return array<string, string>
     */
    private function specs(array $row, array $fallback = []): array
    {
        if (! $this->has($row, 'specs')) {
            return $fallback;
        }

        $value = $this->string($row, 'specs');

        if ($value === '' || $value === '默认规格') {
            return [];
        }

        if (Str::startsWith($value, ['{', '['])) {
            $decoded = json_decode($value, true);

            if (is_array($decoded)) {
                return collect($decoded)
                    ->mapWithKeys(fn ($item, $key): array => [(string) $key => (string) $item])
                    ->all();
            }
        }

        $specs = [];
        $parts = preg_split('/\s*(?:\/|;|；|\|)\s*/u', $value) ?: [];

        foreach ($parts as $index => $part) {
            if ($part === '') {
                continue;
            }

            $pair = preg_split('/\s*(?:[:：=])\s*/u', $part, 2);

            if (is_array($pair) && count($pair) === 2) {
                $specs[$this->clean($pair[0])] = $this->clean($pair[1]);
            } else {
                $specs['规格'.($index + 1)] = $this->clean($part);
            }
        }

        return array_filter($specs, fn (string $value): bool => $value !== '');
    }

    private function clean(string $value): string
    {
        return trim(preg_replace('/^\xEF\xBB\xBF/u', '', $value) ?? $value);
    }

    private function has(array $row, string $key): bool
    {
        return array_key_exists($key, $row);
    }

    private function uniqueSlugBase(string $value, string $prefix): string
    {
        return Str::slug($value) ?: ($prefix.'-'.Str::lower(Str::random(8)));
    }

    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelClass
     */
    private function uniqueSlug(string $value, string $prefix, string $modelClass, ?int $ignoreId = null): string
    {
        $base = $this->uniqueSlugBase($value, $prefix);
        $slug = $base;
        $suffix = 2;

        while ($modelClass::query()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}

class CsvImportSkipException extends \RuntimeException
{
}
