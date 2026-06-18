<?php

namespace App\Filament\Resources\AfterSalesRequestResource\Pages;

use App\Filament\Resources\AfterSalesRequestResource;
use App\Models\AfterSalesRequest;
use App\Models\Coupon;
use App\Models\UserCoupon;
use App\Support\AdminAccess;
use App\Services\CouponService;
use App\Filament\Resources\Pages\EditRecord;

class EditAfterSalesRequest extends EditRecord
{
    protected static string $resource = AfterSalesRequestResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (($data['status'] ?? null) === AfterSalesRequest::STATUS_RESOLVED && blank($this->record->resolved_at)) {
            $data['resolved_at'] = now();
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $this->record->refresh()->loadMissing('user');

        if ($this->record->status !== AfterSalesRequest::STATUS_RESOLVED || ! $this->record->coupon_id || ! $this->record->user) {
            return;
        }

        $coupon = Coupon::query()->find($this->record->coupon_id);

        if (! $coupon) {
            return;
        }

        if (! AdminAccess::canAction('coupons.issue')) {
            return;
        }

        app(CouponService::class)->issueToUser(
            $coupon,
            $this->record->user,
            UserCoupon::SOURCE_AFTER_SALES,
            auth()->user(),
            $this->record->id,
            '售后补偿',
        );
    }
}
