<?php

namespace App\Filament\Resources\CouponResource\Pages;

use App\Filament\Resources\CouponResource;
use App\Models\Coupon;
use App\Filament\Resources\Pages\EditRecord;

class EditCoupon extends EditRecord
{
    protected static string $resource = CouponResource::class;

    protected function afterSave(): void
    {
        $this->syncLegacyProductId();
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Coupon $record */
        $record = $this->record;

        if ($record->scope === Coupon::SCOPE_PRODUCT && $record->product_id && ! $record->products()->whereKey($record->product_id)->exists()) {
            $record->products()->syncWithoutDetaching([$record->product_id]);
        }

        return $data;
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
