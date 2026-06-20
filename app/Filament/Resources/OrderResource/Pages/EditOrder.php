<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use App\Services\AdminActivityLogger;
use App\Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    /**
     * @var array<string, array{old:mixed,new:mixed}>
     */
    private array $manualChanges = [];

    private string $manualUpdateNote = '';

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Order $record */
        $this->manualUpdateNote = trim((string) data_get($this->form->getRawState(), 'manual_update_note', ''));
        $this->manualChanges = $this->changedOrderFields($record, $data);
        $digitalDeliveryChanged = $this->hasDigitalDeliveryChanges();
        $shouldResendDigitalDelivery = $digitalDeliveryChanged && $this->hasDigitalDeliveryPayload($data);

        if ($this->manualChanges !== [] && $this->manualUpdateNote === '') {
            throw ValidationException::withMessages([
                'data.manual_update_note' => '订单信息发生变化时必须填写本次修改备注。',
            ]);
        }

        if ($shouldResendDigitalDelivery && ($data['status'] ?? $record->status) !== Order::STATUS_CANCELLED) {
            $data['status'] = Order::STATUS_AWAITING_RECEIPT;
            $data['digital_delivery_sent_at'] = now();
            $data['digital_delivery_viewed_at'] = null;
            $data['digital_delivery_completed_at'] = null;
            $data['shipped_at'] = now();
            $data['delivered_at'] = null;
            $data['fulfilled_at'] = null;

            if ($record->status !== Order::STATUS_AWAITING_RECEIPT) {
                $this->manualChanges['status'] = [
                    'old' => $this->normalizeValue($record->status),
                    'new' => Order::STATUS_AWAITING_RECEIPT,
                ];
            }
        }

        $record->update($data);

        if ($shouldResendDigitalDelivery && $record->status === Order::STATUS_AWAITING_RECEIPT) {
            $record->items()->update(['status' => Order::STATUS_AWAITING_RECEIPT]);

            app(AdminActivityLogger::class)->log(
                'order_digital_delivery_sent',
                $record->fresh(),
                $this->manualUpdateNote ?: $record->order_number,
                [
                    'status' => Order::STATUS_AWAITING_RECEIPT,
                    'attachment_count' => count($record->digital_delivery_attachment_paths ?: []),
                ],
                auth()->user(),
            );
        }

        if ($this->manualChanges !== []) {
            app(AdminActivityLogger::class)->log(
                'order_manually_updated',
                $record->fresh(),
                $this->manualUpdateNote,
                [
                    'order_number' => $record->order_number,
                    'note' => $this->manualUpdateNote,
                    'changes' => $this->manualChanges,
                ],
                auth()->user(),
            );
        }

        return $record;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, array{old:mixed,new:mixed}>
     */
    private function changedOrderFields(Order $record, array $data): array
    {
        $fields = [
            'status',
            'payment_status',
            'contact_name',
            'contact_phone',
            'contact_email',
            'shipping_address',
            'shipping_province',
            'shipping_city',
            'shipping_district',
            'shipping_street',
            'shipping_detail',
            'shipping_carrier_id',
            'tracking_number',
            'tracking_url',
            'digital_delivery_content',
            'digital_delivery_code',
            'digital_delivery_attachment_paths',
            'admin_note',
        ];

        $changes = [];

        foreach ($fields as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            $old = $this->normalizeValue($record->getAttribute($field));
            $new = $this->normalizeValue($data[$field]);

            if ($old === $new) {
                continue;
            }

            $changes[$field] = [
                'old' => $old,
                'new' => $new,
            ];
        }

        return $changes;
    }

    private function normalizeValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            $value = array_values(array_filter($value, fn ($item): bool => filled($item)));

            return $value === []
                ? null
                : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        if (is_int($value) || is_float($value) || is_bool($value)) {
            return $value;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function hasDigitalDeliveryChanges(): bool
    {
        foreach (['digital_delivery_content', 'digital_delivery_code', 'digital_delivery_attachment_paths'] as $field) {
            if (array_key_exists($field, $this->manualChanges)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function hasDigitalDeliveryPayload(array $data): bool
    {
        return filled($data['digital_delivery_content'] ?? null)
            || filled($data['digital_delivery_code'] ?? null)
            || ! empty(array_filter((array) ($data['digital_delivery_attachment_paths'] ?? []), fn ($path): bool => filled($path)));
    }
}
