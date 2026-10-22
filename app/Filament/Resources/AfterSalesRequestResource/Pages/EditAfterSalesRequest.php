<?php

namespace App\Filament\Resources\AfterSalesRequestResource\Pages;

use App\Filament\Resources\AfterSalesRequestResource;
use App\Filament\Resources\Pages\EditRecord;
use App\Models\AfterSalesRequest;
use App\Models\Coupon;
use App\Models\UserCoupon;
use App\Services\AfterSalesResolutionService;
use App\Services\CouponService;
use App\Support\AdminAccess;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EditAfterSalesRequest extends EditRecord
{
    protected static string $resource = AfterSalesRequestResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return DB::transaction(function () use ($record, $data): Model {
            $record = AfterSalesRequest::query()->lockForUpdate()->findOrFail($record->id);

            if ($record->refund_status === AfterSalesRequest::REFUND_APPROVED
                && (($data['status'] ?? $record->status) !== $record->status
                    || ($data['resolution_type'] ?? $record->resolution_type) !== $record->resolution_type
                    || (int) ($data['refund_amount_cents'] ?? $record->refund_amount_cents) !== (int) $record->refund_amount_cents)) {
                throw ValidationException::withMessages(['data.refund_amount_cents' => '已审批退款不可修改，请新建售后申请。']);
            }

            if (($data['resolution_type'] ?? null) === AfterSalesRequest::RESOLUTION_REFUND
                && ($data['status'] ?? null) === AfterSalesRequest::STATUS_RESOLVED) {
                try {
                    app(AfterSalesResolutionService::class)->resolve($record, $data, auth()->user());
                } catch (ValidationException $exception) {
                    throw ValidationException::withMessages(collect($exception->errors())
                        ->mapWithKeys(fn (array $messages, string $field): array => ['data.'.$field => $messages])->all());
                }

                return $record->refresh();
            }

            if ((($data['resolution_type'] ?? null) === AfterSalesRequest::RESOLUTION_REFUND
                    && $record->resolution_type !== AfterSalesRequest::RESOLUTION_REFUND)
                || (array_key_exists('refund_amount_cents', $data)
                    && (int) $data['refund_amount_cents'] !== (int) $record->refund_amount_cents)) {
                abort_unless(AdminAccess::canAction('after_sales.refund'), 403);
            }

            $record->update($data);

            return $record;
        });
    }

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

        if ($this->record->status !== AfterSalesRequest::STATUS_RESOLVED
            || $this->record->resolution_type !== AfterSalesRequest::RESOLUTION_COUPON
            || ! $this->record->coupon_id || ! $this->record->user) {
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
