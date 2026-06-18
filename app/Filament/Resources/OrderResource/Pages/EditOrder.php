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

        if ($this->manualChanges !== [] && $this->manualUpdateNote === '') {
            throw ValidationException::withMessages([
                'data.manual_update_note' => '订单信息发生变化时必须填写本次修改备注。',
            ]);
        }

        $record->update($data);

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

        if (is_int($value) || is_float($value) || is_bool($value)) {
            return $value;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
