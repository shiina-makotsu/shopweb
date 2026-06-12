<?php

namespace App\Filament\Resources\CouponResource\Pages;

use App\Filament\Resources\CouponResource;
use App\Models\Coupon;
use Filament\Resources\Pages\CreateRecord;

class CreateCoupon extends CreateRecord
{
    protected static string $resource = CouponResource::class;

    protected function afterCreate(): void
    {
        $this->syncLegacyProductId();
    }

    private function syncLegacyProductId(): void
    {
        /** @var Coupon $record */
        $record = $this->record;

        if ($record->scope !== Coupon::SCOPE_PRODUCT) {
            $record->forceFill(['product_id' => null])->saveQuietly();

            return;
        }

        $firstProductId = $record->products()->value('products.id');

        if ($firstProductId && (int) $record->product_id !== (int) $firstProductId) {
            $record->forceFill(['product_id' => $firstProductId])->saveQuietly();
        }
    }
}
